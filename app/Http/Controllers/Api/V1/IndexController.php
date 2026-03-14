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
 * @group Index Management
 *
 * API endpoints for managing Elasticsearch index operations (async)
 */
class IndexController extends ApiController
{
    /**
     * Start async reindex of all tenant products.
     *
     * Triggers an asynchronous reindex job for the tenant's product catalog.
     *
     * @authenticated
     *
     * @urlParam tenantId string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 202 {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "pending",
     *     "message": "Reindexing started",
     *     "tenant_id": "uuid"
     *   },
     *   "meta": {}
     * }
     * @response 404 {
     *   "message": "Tenant not found or access denied"
     * }
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
     * Get job status.
     *
     * Returns the current status and progress of an index job.
     *
     * @authenticated
     *
     * @urlParam jobId string required Job UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @responseField data.job_id string Job UUID
     * @responseField data.status string Job status (pending, running, completed, failed)
     * @responseField data.job_type string Job type identifier
     * @responseField data.progress integer Progress percentage (0-100)
     * @responseField data.started_at timestamp Job start time
     * @responseField data.completed_at timestamp Job completion time
     * @responseField data.error_message string Error message (null if success)
     *
     * @response {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "running",
     *     "job_type": "reindex_tenant_products",
     *     "payload": {
     *       "tenant_id": "uuid",
     *       "tenant_name": "My Store",
     *       "requested_by": "uuid"
     *     },
     *     "error_message": null,
     *     "created_at": "2026-03-15T10:00:00Z",
     *     "started_at": "2026-03-15T10:00:05Z",
     *     "completed_at": null
     *   }
     * }
     * @response 404 {
     *   "message": "Job not found"
     * }
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
     * List recent jobs for a tenant.
     *
     * Returns a list of recent index operations for the specified tenant.
     *
     * @authenticated
     *
     * @urlParam tenantId string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @queryParam status string optional Filter by status (pending, running, completed, failed). Example: completed
     * @queryParam page integer Page number (default: 1). Example: 1
     *
     * @responseField data.jobs{0}.job_id string Job UUID
     * @responseField data.jobs{0}.status string Job status
     * @responseField data.jobs{0}.job_type string Job type
     * @responseField data.jobs{0}.created_at timestamp Job creation time
     * @responseField data.jobs{0}.completed_at timestamp Job completion time
     *
     * @response {
     *   "data": {
     *     "jobs": [
     *       {
     *         "job_id": "uuid",
     *         "status": "completed",
     *         "job_type": "reindex_tenant_products",
     *         "created_at": "2026-03-15T10:00:00Z",
     *         "completed_at": "2026-03-15T10:05:00Z"
     *       }
     *     ]
     *   }
     * }
     * @response 404 {
     *   "message": "Tenant not found or access denied"
     * }
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
