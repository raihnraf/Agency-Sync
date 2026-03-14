<?php

namespace App\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\JobStatus;
use App\Models\Product;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportProductCatalog extends TenantAwareJob implements ShouldQueue
{
    use Queueable, \Illuminate\Foundation\Bus\Dispatchable, \Illuminate\Queue\InteractsWithQueue, \Illuminate\Queue\SerializesModels;

    private $jobStatusId;

    public function __construct(string $jobStatusId)
    {
        $this->jobStatusId = $jobStatusId;
        // Set timeout to 5 minutes for large catalogs
        $this->timeout = 300;
    }

    public function handle(ExportService $exportService): void
    {
        $jobStatus = JobStatus::findOrFail($this->jobStatusId);
        $jobStatus->markAsRunning();

        try {
            $tenant = $jobStatus->tenant;
            $query = Product::query()->where('tenant_id', $this->tenantId);

            // Generate filename
            $filename = $exportService->generateFilename('products', $tenant, 'xlsx');
            $filepath = storage_path("app/exports/{$filename}");

            // Generate XLSX
            $this->generateXlsx($query, $filepath);

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

    private function generateXlsx($query, string $filepath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'SKU');
        $sheet->setCellValue('C1', 'Price');
        $sheet->setCellValue('D1', 'Stock Status');
        $sheet->setCellValue('E1', 'Created At');

        // Data rows with chunking
        $row = 2;
        $query->chunk(1000, function ($products) use ($sheet, &$row) {
            foreach ($products as $product) {
                // Determine stock status
                $stockStatus = 'out_of_stock';
                if ($product->stock_quantity > 10) {
                    $stockStatus = 'in_stock';
                } elseif ($product->stock_quantity > 0) {
                    $stockStatus = 'low_stock';
                }

                $sheet->setCellValue("A{$row}", $product->name);
                $sheet->setCellValue("B{$row}", $product->sku);
                $sheet->setCellValue("C{$row}", $product->price);
                $sheet->setCellValue("D{$row}", $stockStatus);
                $sheet->setCellValue("E{$row}", $product->created_at->format('Y-m-d H:i:s'));
                $row++;
            }
        });

        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
    }
}
