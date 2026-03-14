<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Jobs\ExportSyncLogs;
use App\Jobs\ExportProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function dispatchSyncLogsExport(Request $request)
    {
        $request->validate([
            'filters' => 'sometimes|array',
            'filters.start_date' => 'sometimes|date',
            'filters.end_date' => 'sometimes|date',
            'filters.tenant_id' => 'sometimes|uuid|exists:tenants,id',
            'filters.status' => 'sometimes|in:completed,failed,partially_failed,running,pending',
            'format' => 'required|in:csv,xlsx'
        ]);

        $user = $request->user();
        $filters = $request->input('filters', []);
        $format = $request->input('format', 'csv');

        // Get tenant_id from filters or use first user tenant
        $tenantId = $filters['tenant_id'] ?? $user->tenants()->first()->id;

        // Create JobStatus
        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'status' => 'pending',
            'job_type' => 'export_sync_logs'
        ]);

        // Dispatch job
        ExportSyncLogs::dispatch($tenantId, $jobStatus->id, $filters, $format);

        return response()->json([
            'data' => [
                'job_uuid' => $jobStatus->uuid,
                'status' => 'pending',
                'message' => 'Export job queued'
            ]
        ], 202);
    }

    public function dispatchProductExport(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id'
        ]);

        $user = $request->user();
        $tenantId = $request->input('tenant_id');

        // Verify tenant access
        $tenant = $user->tenants()->where('id', $tenantId)->firstOrFail();

        // Create JobStatus
        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'status' => 'pending',
            'job_type' => 'export_product_catalog'
        ]);

        // Dispatch job
        ExportProductCatalog::dispatch($tenantId, $jobStatus->id);

        return response()->json([
            'data' => [
                'job_uuid' => $jobStatus->uuid,
                'status' => 'pending',
                'message' => 'Export job queued'
            ]
        ], 202);
    }

    public function download(Request $request, string $uuid)
    {
        $jobStatus = JobStatus::where('uuid', $uuid)->firstOrFail();

        if ($jobStatus->status !== 'completed') {
            return response()->json(['errors' => [
                ['message' => 'Export not ready']
            ]], 404);
        }

        $filepath = $jobStatus->result['filepath'];
        $filename = $jobStatus->result['filename'];

        // Generate signed URL valid for 24 hours
        $url = Storage::disk('exports')->temporaryUrl(
            $filename,
            now()->addHours(24)
        );

        return response()->json(['data' => [
            'download_url' => $url,
            'filename' => $filename,
            'expires_at' => now()->addHours(24)->toIso8601String()
        ]]);
    }
}
