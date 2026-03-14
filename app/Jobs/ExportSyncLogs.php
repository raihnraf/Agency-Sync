<?php

namespace App\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\JobStatus;
use App\Models\SyncLog;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportSyncLogs extends TenantAwareJob implements ShouldQueue
{
    use Queueable, \Illuminate\Foundation\Bus\Dispatchable, \Illuminate\Queue\InteractsWithQueue, \Illuminate\Queue\SerializesModels;

    private $jobStatusId;
    private $filters;
    private $format;

    public function __construct(string $jobStatusId, array $filters, string $format)
    {
        $this->jobStatusId = $jobStatusId;
        $this->filters = $filters;
        $this->format = $format;
    }

    public function handle(ExportService $exportService): void
    {
        $jobStatus = JobStatus::findOrFail($this->jobStatusId);
        $jobStatus->markAsRunning();

        try {
            $tenant = $jobStatus->tenant;
            $query = SyncLog::query()->where('tenant_id', $this->tenantId);

            // Apply filters
            $query = $exportService->applyFilters($query, $this->filters);

            // Check row limit
            $estimated = $exportService->estimateRowCount($query);
            if ($estimated > 100000) {
                throw new \Exception("Export exceeds 100K row limit ({$estimated} rows)");
            }

            // Generate filename
            $filename = $exportService->generateFilename('synclogs', $tenant, $this->format);
            $filepath = storage_path("app/exports/{$filename}");

            // Generate CSV
            $this->generateCsv($query, $filepath);

            // Update JobStatus
            $jobStatus->update([
                'result' => ['filepath' => $filepath, 'filename' => $filename]
            ]);
            $jobStatus->markAsCompleted();
        } catch (\Exception $e) {
            $jobStatus->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function generateCsv($query, string $filepath): void
    {
        $csv = Writer::createFromPath($filepath, 'w+');
        $csv->setOutputBOM(Writer::BOM_UTF8);

        // Header row
        $csv->insertOne(['Tenant', 'Status', 'Products Synced', 'Started At', 'Completed At', 'Duration']);

        // Data rows with chunking
        $query->with('tenant')->chunk(1000, function ($logs) use ($csv) {
            foreach ($logs as $log) {
                $duration = 'N/A';
                if ($log->completed_at && $log->started_at) {
                    $diff = $log->completed_at->diffInSeconds($log->started_at);
                    $duration = $diff . 's';
                }

                $csv->insertOne([
                    $log->tenant->name,
                    $log->status->value,
                    $log->indexed_products ?? 0,
                    $log->started_at->format('Y-m-d H:i:s'),
                    $log->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $duration,
                ]);
            }
        });
    }
}
