---
phase: 09-data-flows-caching-operations
plan: 01b
type: execute
wave: 2
depends_on:
  - 09-01a
files_modified:
  - app/Jobs/ExportSyncLogs.php
  - app/Jobs/ExportProductCatalog.php
  - app/Http/Controllers/ExportController.php
  - routes/api.php
  - resources/views/dashboard/tenants/show.blade.php
  - resources/views/dashboard/tenants/products.blade.php
  - public/js/dashboard.js
autonomous: false
requirements:
  - DATAFLOW-01
  - DATAFLOW-02
  - DATAFLOW-03
must_haves:
  truths:
    - "ExportSyncLogs job generates CSV with filters and row limit"
    - "ExportProductCatalog job generates XLSX with chunking"
    - "ExportController API endpoints working (dispatch and download)"
    - "Export UI added to views with loading states and download links"
    - "Job status polling works every 2 seconds"
    - "Download links appear when export completes"
  artifacts:
    - path: "app/Jobs/ExportSyncLogs.php"
      provides: "Background job for sync log CSV export"
      exports: ["handle()", "generateCsv()", "generateFilename()"]
      min_lines: 80
    - path: "app/Jobs/ExportProductCatalog.php"
      provides: "Background job for product catalog Excel export"
      exports: ["handle()", "generateXlsx()", "generateFilename()"]
      min_lines: 70
    - path: "app/Http/Controllers/ExportController.php"
      provides: "API endpoints for export dispatch and download"
      exports: ["dispatchSyncLogsExport()", "dispatchProductExport()", "download()"]
      min_lines: 60
    - path: "routes/api.php"
      provides: "API route registration for export endpoints"
      contains: "POST.*exports/sync-logs"
      contains: "GET.*exports/{uuid}"
      min_lines: 5
    - path: "resources/views/dashboard/tenants/show.blade.php"
      provides: "Export UI for sync logs with filters"
      contains: "exportSyncLogs()"
      min_lines: 30
    - path: "resources/views/dashboard/tenants/products.blade.php"
      provides: "Export UI for product catalog"
      contains: "exportProducts()"
      min_lines: 25
    - path: "public/js/dashboard.js"
      provides: "Alpine.js components for export UI"
      contains: "function exportSyncLogs"
      contains: "function exportProducts"
      min_lines: 100
  key_links:
    - from: "resources/views/dashboard/tenants/show.blade.php"
      to: "POST /api/v1/exports/sync-logs"
      via: "Alpine.js fetch() call in exportSyncLogs()"
      pattern: "fetch.*api/v1/exports/sync-logs.*POST"
    - from: "resources/views/dashboard/tenants/products.blade.php"
      to: "POST /api/v1/exports/products"
      via: "Alpine.js fetch() call in exportProducts()"
      pattern: "fetch.*api/v1/exports/products.*POST"
    - from: "public/js/dashboard.js (exportSyncLogs)"
      to: "GET /api/v1/exports/{uuid}"
      via: "Job status polling in pollJobStatus()"
      pattern: "fetch.*api/v1/exports/\${.*jobUuid}"
    - from: "app/Http/Controllers/ExportController.php"
      to: "app/Jobs/ExportSyncLogs.php"
      via: "Job dispatch with JobStatus and filters"
      pattern: "ExportSyncLogs::dispatch"
    - from: "app/Http/Controllers/ExportController.php"
      to: "app/Jobs/ExportProductCatalog.php"
      via: "Job dispatch with tenant_id"
      pattern: "ExportProductCatalog::dispatch"
    - from: "app/Jobs/ExportSyncLogs.php"
      to: "JobStatus model"
      via: "Status updates (pending → running → completed)"
      pattern: "jobStatus->update.*status"
    - from: "GET /api/v1/exports/{uuid}"
      to: "Storage::disk('exports')"
      via: "Signed URL generation"
      pattern: "Storage::disk.*exports.*temporaryUrl"
---

<objective>
Implement export background jobs, API endpoints, and UI integration for async CSV/Excel data export with job tracking and download links.

Purpose: Enable agency admins to export sync logs and product catalogs while maintaining system performance
Output: Working export jobs with UI integration, job tracking, and file downloads
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/phases/09-data-flows-caching-operations/09-00-EXPORT-PLAN.md
@.planning/phases/09-data-flows-caching-operations/09-01a-PLAN.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Foundation from Plan 01a

From **09-01a-PLAN.md** (Foundation):
- **Export libraries installed** — league/csv for CSV, phpspreadsheet for XLSX
- **Exports disk configured** — Private disk at storage/app/exports
- **ExportService created** — Common logic for filename, filters, row counting
- **Exports directory exists** — Migrated and writable

# Key Models from Previous Phases

From Phase 4 (Background Processing):
- **JobStatus model** — Status tracking with enum (pending, running, completed, failed)
- **TenantAwareJob base class** — Abstract base with tenantId, retry logic, timeout

From Phase 6 (Catalog Synchronization):
- **SyncLog model** — tenant_id, status, products_synced, created_at, completed_at
- **Product model** — tenant_id, name, sku, price, stock_status, created_at

From Phase 7 (Admin Dashboard):
- **Alpine.js toast component** — For "export ready" notifications
- **Client-side API calls** — fetch() with CSRF token

# Export Format Requirements

**Sync Log CSV:**
- Headers: Tenant, Status, Products Synced, Started At, Completed At, Duration
- Filters: date range, tenant_id, status
- Row limit: 100K max
- Filename: synclogs_{tenant_slug}_{date}.csv

**Product Catalog Excel:**
- Columns: name, sku, price, stock_status, created_at
- Tenant-scoped query
- Chunking: 1000 rows per chunk
- Filename: products_{tenant_slug}_{date}.xlsx

**Delivery:**
- Background job dispatch (returns 202 with job_id)
- JobStatus tracking (GET /api/v1/exports/{uuid})
- Signed URL download (24-hour expiration)
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create ExportSyncLogs background job</name>
  <files>app/Jobs/ExportSyncLogs.php</files>
  <behavior>
    Test 1: Job extends TenantAwareJob and implements ShouldQueue
    Test 2: Job generates CSV file with correct headers
    Test 3: Job applies date range, tenant, and status filters
    Test 4: Job enforces 100K row limit and fails if exceeded
    Test 5: Job updates JobStatus from pending to running to completed
    Test 6: Job stores filepath in JobStatus result on completion
    Test 7: Job marks JobStatus as failed on exception
    Test 8: CSV includes UTF-8 BOM for Excel compatibility
    Test 9: CSV properly escapes special characters (commas, newlines, quotes)
  </behavior>
  <action>
    Create app/Jobs/ExportSyncLogs.php extending TenantAwareJob:

    ```php
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
        use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

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
            $jobStatus->update(['status' => JobStatusEnum::RUNNING]);

            try {
                $tenant = Tenant::findOrFail($this->tenantId);
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
                    'status' => JobStatusEnum::COMPLETED,
                    'result' => ['filepath' => $filepath, 'filename' => $filename]
                ]);
            } catch (\Exception $e) {
                $jobStatus->update([
                    'status' => JobStatusEnum::FAILED,
                    'error_message' => $e->getMessage()
                ]);
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
                    $csv->insertOne([
                        $log->tenant->name,
                        $log->status->value,
                        $log->products_synced,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                        $log->duration ?? 'N/A'
                    ]);
                }
            });
        }
    }
    ```

    Key points:
    - Extends TenantAwareJob for tenant context and retry logic
    - Uses ExportService for filters, filename, row counting
    - Generates CSV with UTF-8 BOM for Excel compatibility
    - Chunks 1000 rows at a time to prevent memory issues
    - Enforces 100K row limit before generation
    - Updates JobStatus through lifecycle (pending → running → completed/failed)
    - Stores filepath in JobStatus result for download link generation
  </action>
  <verify>
    <automated>php artisan test --filter=ExportSyncLogsTest</automated>
  </verify>
  <done>ExportSyncLogs job created with CSV generation, filters, row limit, and JobStatus tracking</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Create ExportProductCatalog background job</name>
  <files>app/Jobs/ExportProductCatalog.php</files>
  <behavior>
    Test 1: Job extends TenantAwareJob and implements ShouldQueue
    Test 2: Job generates XLSX file with product data
    Test 3: Job queries only tenant-scoped products
    Test 4: Job uses chunking (1000 rows) to handle large catalogs
    Test 5: Job updates JobStatus to completed with filepath result
    Test 6: Job marks JobStatus as failed on error
    Test 7: Job filename follows pattern: products_{tenant_slug}_{date}.xlsx
  </behavior>
  <action>
    Create app/Jobs/ExportProductCatalog.php extending TenantAwareJob:

    ```php
    <?php

    namespace App\Jobs;

    use App\Jobs\TenantAwareJob;
    use App\Models\JobStatus;
    use App\Models\Product;
    use App\Models\Tenant;
    use App\Services\ExportService;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    class ExportProductCatalog extends TenantAwareJob implements ShouldQueue
    {
        use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

        private $jobStatusId;

        public function __construct(string $jobStatusId)
        {
            $this->jobStatusId = $jobStatusId;
        }

        public function handle(ExportService $exportService): void
        {
            $jobStatus = JobStatus::findOrFail($this->jobStatusId);
            $jobStatus->update(['status' => JobStatusEnum::RUNNING]);

            try {
                $tenant = Tenant::findOrFail($this->tenantId);
                $query = Product::query()->where('tenant_id', $this->tenantId);

                // Generate filename
                $filename = $exportService->generateFilename('products', $tenant, 'xlsx');
                $filepath = storage_path("app/exports/{$filename}");

                // Generate XLSX
                $this->generateXlsx($query, $filepath);

                // Update JobStatus
                $jobStatus->update([
                    'status' => JobStatusEnum::COMPLETED,
                    'result' => ['filepath' => $filepath, 'filename' => $filename]
                ]);
            } catch (\Exception $e) {
                $jobStatus->update([
                    'status' => JobStatusEnum::FAILED,
                    'error_message' => $e->getMessage()
                ]);
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
                    $sheet->setCellValue("A{$row}", $product->name);
                    $sheet->setCellValue("B{$row}", $product->sku);
                    $sheet->setCellValue("C{$row}", $product->price);
                    $sheet->setCellValue("D{$row}", $product->stock_status->value);
                    $sheet->setCellValue("E{$row}", $product->created_at->format('Y-m-d H:i:s'));
                    $row++;
                }
            });

            // Save file
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
        }
    }
    ```

    Key points:
    - Extends TenantAwareJob for tenant context
    - Uses PhpSpreadsheet for XLSX generation
    - Chunks 1000 products at a time for memory efficiency
    - Selects only tenant-scoped products
    - Generates filename via ExportService
    - Updates JobStatus on completion/failure
  </action>
  <verify>
    <automated>php artisan test --filter=ExportProductCatalogTest</automated>
  </verify>
  <done>ExportProductCatalog job created with XLSX generation, tenant scoping, and chunking</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Create ExportController for API endpoints</name>
  <files>app/Http/Controllers/ExportController.php</files>
  <behavior>
    Test 1: POST /exports/sync-logs dispatches ExportSyncLogs job with filters
    Test 2: POST /exports/sync-logs creates JobStatus and returns 202 with job_uuid
    Test 3: POST /exports/products dispatches ExportProductCatalog job
    Test 4: POST /exports/products creates JobStatus and returns 202 with job_uuid
    Test 5: GET /exports/{uuid} returns download_url for completed exports
    Test 6: GET /exports/{uuid} returns 404 for pending/running exports
    Test 7: GET /exports/{uuid} generates signed URL valid for 24 hours
    Test 8: All endpoints require authentication
  </behavior>
  <action>
    Create app/Http/Controllers/ExportController.php:

    ```php
    <?php

    namespace App\Http\Controllers;

    use App\Http\Controllers\Controller;
    use App\Models\JobStatus;
    use App\Jobs\ExportSyncLogs;
    use App\Jobs\ExportProductCatalog;
    use App\Models\Tenant;
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

            // Create JobStatus
            $jobStatus = JobStatus::create([
                'user_id' => $user->id,
                'tenant_id' => $filters['tenant_id'] ?? null,
                'status' => JobStatusEnum::PENDING,
                'job_type' => 'export_sync_logs'
            ]);

            // Dispatch job
            ExportSyncLogs::dispatch($jobStatus->id, $filters, $format);

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
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'status' => JobStatusEnum::PENDING,
                'job_type' => 'export_product_catalog'
            ]);

            // Dispatch job
            ExportProductCatalog::dispatch($jobStatus->id);

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

            if ($jobStatus->status !== JobStatusEnum::COMPLETED) {
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
    ```

    Key points:
    - dispatchSyncLogsExport() validates filters, creates JobStatus, dispatches job
    - dispatchProductExport() validates tenant access, creates JobStatus, dispatches job
    - download() generates signed URL for completed exports, returns 404 for pending/running
    - All endpoints require Sanctum authentication
    - Returns 202 Accepted for job dispatch (not 201 Created)
  </action>
  <verify>
    <automated>php artisan test --filter=ExportControllerTest</automated>
  </verify>
  <done>ExportController created with dispatch and download endpoints</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Register API routes for export endpoints</name>
  <files>routes/api.php</files>
  <behavior>
    Test 1: POST /api/v1/exports/sync-logs route registered
    Test 2: POST /api/v1/exports/products route registered
    Test 3: GET /api/v1/exports/{uuid} route registered
    Test 4: All routes require authentication middleware
  </behavior>
  <action>
    Register API routes in routes/api.php:

    ```php
    use App\Http\Controllers\ExportController;

    Route::middleware('auth:sanctum')->group(function () {
        // Export dispatch endpoints
        Route::post('/exports/sync-logs', [ExportController::class, 'dispatchSyncLogsExport']);
        Route::post('/exports/products', [ExportController::class, 'dispatchProductExport']);

        // Export download endpoint
        Route::get('/exports/{uuid}', [ExportController::class, 'download']);
    });
    ```

    Key points:
    - All routes require Sanctum authentication
    - Routes follow RESTful conventions (POST for dispatch, GET for download)
    - Endpoint paths match controller method names
  </action>
  <verify>
    <automated>php artisan route:list --path=exports | grep -E "POST|GET"</automated>
  </verify>
  <done>Export API routes registered with authentication middleware</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Add export UI to tenant detail view</name>
  <files>resources/views/dashboard/tenants/show.blade.php public/js/dashboard.js</files>
  <behavior>
    Test 1: Export button visible on tenant detail view
    Test 2: Filter form present (date range pickers, status dropdown)
    Test 3: Clicking export button dispatches job to API
    Test 4: Loading state appears while job processes
    Test 5: Download button appears when job completes
    Test 6: Toast notification appears on export completion
    Test 7: Job status polling works every 2 seconds
  </behavior>
  <action>
    Add export UI components to tenant detail view:

    **resources/views/dashboard/tenants/show.blade.php:**
    Add above sync log table:
    ```blade
    <div x-data="exportSyncLogs()" class="mb-4 flex items-center justify-between">
        <div class="flex gap-2">
            <input type="date" x-model="filters.start_date" class="border rounded px-2 py-1">
            <input type="date" x-model="filters.end_date" class="border rounded px-2 py-1">
            <select x-model="filters.status" class="border rounded px-2 py-1">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="partially_failed">Partially Failed</option>
            </select>
        </div>
        <button @click="exportSyncLogs" :disabled="loading"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
            <span x-show="!loading">Export Sync Logs</span>
            <span x-show="loading">Exporting...</span>
        </button>
        <a :href="downloadUrl" x-show="downloadUrl" target="_blank"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Download Export
        </a>
    </div>
    ```

    **public/js/dashboard.js:**
    Add Alpine.js component:
    ```javascript
    function exportSyncLogs() {
        return {
            filters: { start_date: '', end_date: '', status: '' },
            loading: false,
            downloadUrl: null,
            jobUuid: null,

            async exportSyncLogs() {
                this.loading = true;
                try {
                    const response = await fetch('/api/v1/exports/sync-logs', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            filters: this.filters,
                            format: 'csv'
                        })
                    });
                    const data = await response.json();
                    this.jobUuid = data.data.job_uuid;
                    this.pollJobStatus();
                } catch (error) {
                    console.error('Export failed:', error);
                    this.loading = false;
                }
            },

            async pollJobStatus() {
                const interval = setInterval(async () => {
                    try {
                        const response = await fetch(`/api/v1/exports/${this.jobUuid}`);
                        const data = await response.json();
                        if (response.ok) {
                            this.downloadUrl = data.data.download_url;
                            this.loading = false;
                            clearInterval(interval);
                            showToast('Export ready!', 'success');
                        }
                    } catch (error) {
                        clearInterval(interval);
                        this.loading = false;
                    }
                }, 2000);
            }
        };
    }
    ```

    Key points:
    - Alpine.js component handles UI state (loading, downloadUrl)
    - Export button dispatches job via fetch API to POST /api/v1/exports/sync-logs
    - Job status polling every 2 seconds via GET /api/v1/exports/{uuid}
    - Download button appears when job completes
    - Toast notification from Phase 7 reused for success message
    - CSRF token included in API requests
  </action>
  <verify>
    <automated>grep -q "exportSyncLogs" resources/views/dashboard/tenants/show.blade.php && grep -q "function exportSyncLogs" public/js/dashboard.js && echo "Export UI components added"</automated>
  </verify>
  <done>Export UI added to tenant detail view with loading states, download links, and job polling</done>
</task>

<task type="auto" tdd="true">
  <name>Task 6: Add export UI to product search view</name>
  <files>resources/views/dashboard/tenants/products.blade.php public/js/dashboard.js</files>
  <behavior>
    Test 1: Export button visible on product search view
    Test 2: Format selector present (CSV/XLSX radio buttons)
    Test 3: Clicking export button dispatches job to API
    Test 4: Loading state appears while job processes
    Test 5: Download button appears when job completes
    Test 6: Job status polling works every 2 seconds
  </behavior>
  <action>
    Add export UI components to product search view:

    **resources/views/dashboard/tenants/products.blade.php:**
    Add above product search:
    ```blade
    <div x-data="exportProducts()" class="mb-4 flex items-center justify-between">
        <div class="flex gap-2">
            <label class="flex items-center gap-1">
                <input type="radio" x-model="format" value="csv"> CSV
            </label>
            <label class="flex items-center gap-1">
                <input type="radio" x-model="format" value="xlsx"> Excel
            </label>
        </div>
        <button @click="exportProducts" :disabled="loading"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
            <span x-show="!loading">Export Catalog</span>
            <span x-show="loading">Exporting...</span>
        </button>
        <a :href="downloadUrl" x-show="downloadUrl" target="_blank"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Download Export
        </a>
    </div>
    ```

    **public/js/dashboard.js:**
    Add Alpine.js component:
    ```javascript
    function exportProducts() {
        return {
            format: 'csv',
            loading: false,
            downloadUrl: null,
            jobUuid: null,
            tenantId: document.querySelector('[data-tenant-id]').dataset.tenantId,

            async exportProducts() {
                this.loading = true;
                try {
                    const response = await fetch('/api/v1/exports/products', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ tenant_id: this.tenantId })
                    });
                    const data = await response.json();
                    this.jobUuid = data.data.job_uuid;
                    this.pollJobStatus();
                } catch (error) {
                    console.error('Export failed:', error);
                    this.loading = false;
                }
            },

            async pollJobStatus() {
                const interval = setInterval(async () => {
                    try {
                        const response = await fetch(`/api/v1/exports/${this.jobUuid}`);
                        const data = await response.json();
                        if (response.ok) {
                            this.downloadUrl = data.data.download_url;
                            this.loading = false;
                            clearInterval(interval);
                            showToast('Export ready!', 'success');
                        }
                    } catch (error) {
                        clearInterval(interval);
                        this.loading = false;
                    }
                }, 2000);
            }
        };
    }
    ```

    Key points:
    - Alpine.js component handles UI state (format, loading, downloadUrl)
    - Export button dispatches job via fetch API to POST /api/v1/exports/products
    - Job status polling every 2 seconds via GET /api/v1/exports/{uuid}
    - Download button appears when job completes
    - Format selector (CSV/XLSX) included in request body
  </action>
  <verify>
    <automated>grep -q "exportProducts" resources/views/dashboard/tenants/products.blade.php && grep -q "function exportProducts" public/js/dashboard.js && echo "Export UI components added"</automated>
  </verify>
  <done>Export UI added to product search view with format selection, loading states, and download links</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 7: Verify export UI and job processing</name>
  <files>resources/views/dashboard/tenants/show.blade.php, resources/views/dashboard/tenants/products.blade.php, public/js/dashboard.js</files>
  <action>
    **Manual UI verification steps:**

    1. **Tenant detail view export button:**
       - Visit /dashboard/tenants/{tenant_id}
       - Verify "Export Sync Logs" button visible above sync log table
       - Verify filter form present (date range pickers, status dropdown)

    2. **Product search view export button:**
       - Visit /dashboard/tenants/{tenant_id}/products
       - Verify "Export Catalog" button visible above product search
       - Verify format selector (CSV/XLSX radio buttons)

    3. **Export job flow:**
       - Click export button, verify loading state appears (spinner)
       - Verify job dispatched to Redis queue (check `redis-cli MONITOR`)
       - Wait for job completion (2-5 seconds for small dataset)
       - Verify download button appears when job completes
       - Click download, verify file opens in new tab with correct format
       - Verify toast notification appears: "Export ready!"

    4. **File validation:**
       - Open CSV file, verify UTF-8 characters render correctly
       - Verify CSV headers match expected format
       - Open XLSX file, verify all columns present
       - Verify export includes filtered data only (if filters applied)

    **Browser console checks:**
    - Open DevTools Console
    - Verify no JavaScript errors during export flow
    - Verify API calls logged (POST /exports/*, GET /exports/{uuid})
    - Verify job status polling every 2 seconds
  </action>
  <verify>
    <automated>grep -q "exportSyncLogs" resources/views/dashboard/tenants/show.blade.php && grep -q "exportProducts" resources/views/dashboard/tenants/products.blade.php && grep -q "function exportSyncLogs" public/js/dashboard.js && grep -q "function exportProducts" public/js/dashboard.js && echo "Export UI components present"</automated>
  </verify>
  <done>
    Export UI verified working:
    - Export buttons visible and clickable
    - Loading states appear during job processing
    - Download links appear when jobs complete
    - Files download with correct format
    - Toast notifications appear on completion
    - No JavaScript errors in console
  </done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] ExportSyncLogs job generates CSV with filters and row limit
- [ ] ExportProductCatalog job generates XLSX with chunking
- [ ] ExportController API endpoints working (dispatch and download)
- [ ] API routes registered and require authentication
- [ ] Export UI added to both views with loading states
- [ ] Job status polling working every 2 seconds
- [ ] Download links appear when exports complete
- [ ] All tests passing (ExportSyncLogsTest, ExportProductCatalogTest, ExportControllerTest)

### Integration Verification

- [ ] Export buttons dispatch jobs to Redis queue
- [ ] Queue workers process export jobs
- [ ] Export files stored in storage/app/exports/
- [ ] Download links expire after 24 hours
- [ ] Filters work correctly (date range, tenant, status)
- [ ] 100K row limit enforced
- [ ] Tenant isolation maintained (exports only show tenant data)
- [ ] UI→API→Job flow working end-to-end (human verified)

</verification>

<success_criteria>

1. Agency admins can export sync logs to CSV with filters
2. Agency admins can export product catalogs to Excel
3. Export jobs run asynchronously in background queue
4. Download links available when jobs complete
5. Export files include tenant info, timestamps, and status
6. All exports enforce 100K row limit
7. Signed URLs provide secure temporary downloads
8. Export UI works correctly with loading states and download links (human verified)

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-01b-SUMMARY.md`

</output>
