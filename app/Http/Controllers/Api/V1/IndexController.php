<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Jobs\ReindexTenantProductsJob;
use App\Models\JobStatus;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Index Controller
 * 
 * Manages async index operations with job status tracking.
 * Provides API endpoints for reindexing and job status visibility.
 */
class IndexController extends ApiController
{
    /**
     * Start async reindex of all tenant products
     * 
     * POST /api/v1/tenants/{tenantId}/reindex
     */
    public function reindex(Request $request, string $tenantId): JsonResponse
    {
        // Verify tenant exists and belongs to authenticated user
        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found or access denied', 404);
        }

        // Create job status record
        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $tenantId,
            'status' => 'pending',
            'payload' => [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name,
                'requested_by' => auth()->id(),
            ],
        ]);

        // Dispatch the job
        ReindexTenantProductsJob::dispatch($tenantId, $jobStatus->id);

        Log::info("IndexController: Reindex job dispatched", [
            'tenant_id' => $tenantId,
            'job_id' => $jobStatus->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'data' => [
                'job_id' => $jobStatus->id,
                'status' => 'pending',
                'message' => 'Reindexing started',
                'tenant_id' => $tenantId,
            ],
            'meta' => [],
        ], 202);
    }

    /**
     * Get job status
     * 
     * GET /api/v1/jobs/{jobId}/status
     */
    public function status(Request $request, string $jobId): JsonResponse
    {
        $job = JobStatus::find($jobId);

        if (!$job) {
            return $this->error('Job not found', 404);
        }

        // Verify user has access to this tenant's job
        $hasAccess = Tenant::where('id', $job->tenant_id)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->exists();

        if (!$hasAccess) {
            return $this->error('Job not found', 404);
        }

        return $this->success([
            'job_id' => $job->id,
            'status' => $job->status,
            'job_type' => $job->job_type,
            'payload' => $job->payload,
            'error_message' => $job->error_message,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }

    /**
     * List recent jobs for a tenant
     * 
     * GET /api/v1/tenants/{tenantId}/jobs
     */
    public function list(Request $request, string $tenantId): JsonResponse
    {
        // Verify tenant exists and belongs to authenticated user
        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->first();

        if (!$tenant) {
            return $this->error('Tenant not found or access denied', 404);
        }

        $jobs = JobStatus::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                return [
                    'job_id' => $job->id,
                    'status' => $job->status,
                    'job_type' => $job->job_type,
                    'created_at' => $job->created_at,
                    'completed_at' => $job->completed_at,
                ];
            });

        return $this->success([
            'jobs' => $jobs,
        ]);
    }
}
