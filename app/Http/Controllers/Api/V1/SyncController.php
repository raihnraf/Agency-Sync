<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ExampleSyncJob;
use App\Models\JobStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SyncController extends ApiController
{
    public function dispatch(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'data' => 'sometimes|array',
        ]);

        $tenantId = $request->input('tenant_id');
        $data = $request->input('data', []);

        // Generate a unique job ID for tracking
        $jobId = (string) Str::uuid();

        // Create job status record
        $jobStatus = JobStatus::create([
            'job_id' => $jobId,
            'tenant_id' => $tenantId,
            'job_type' => ExampleSyncJob::class,
            'status' => 'pending',
            'payload' => ['data' => $data],
        ]);

        // Dispatch job to Redis queue (non-blocking)
        dispatch(new ExampleSyncJob($tenantId, $data));

        return response()->json([
            'data' => [
                'job_id' => $jobStatus->job_id,
                'status' => 'pending',
                'message' => 'Sync job dispatched successfully',
            ],
            'meta' => [],
        ], 202);
    }
}
