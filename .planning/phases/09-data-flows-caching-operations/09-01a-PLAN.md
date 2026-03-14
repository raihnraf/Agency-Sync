---
phase: 09-data-flows-caching-operations
plan: 01a
type: execute
wave: 1
depends_on: []
files_modified:
  - composer.json
  - config/filesystems.php
  - app/Services/ExportService.php
  - database/migrations/2026_03_14_000000_add_exports_disk_to_filesystems.php
autonomous: true
requirements:
  - DATAFLOW-01
  - DATAFLOW-02
  - DATAFLOW-03
must_haves:
  truths:
    - "Export libraries (league/csv, phpspreadsheet) installed"
    - "Export storage disk configured for local driver"
    - "ExportService provides common export logic"
    - "storage/app/exports directory exists with correct permissions"
  artifacts:
    - path: "composer.json"
      provides: "PHP dependencies for export functionality"
      contains: "league/csv"
      contains: "phpoffice/phpspreadsheet"
    - path: "config/filesystems.php"
      provides: "Exports disk configuration"
      contains: "disks.exports"
      min_lines: 10
    - path: "app/Services/ExportService.php"
      provides: "Common export logic (filename generation, filter helper)"
      exports: ["generateFilename()", "applyFilters()", "estimateRowCount()"]
      min_lines: 50
    - path: "storage/app/exports/"
      provides: "Export file storage directory (gitignored)"
    - path: "database/migrations/2026_03_14_000000_add_exports_disk_to_filesystems.php"
      provides: "Migration to create exports directory"
      min_lines: 15
  key_links:
    - from: "app/Jobs/ExportSyncLogs.php"
      to: "app/Services/ExportService.php"
      via: "Dependency injection for export logic"
      pattern: "ExportService.*exportService"
    - from: "app/Jobs/ExportProductCatalog.php"
      to: "app/Services/ExportService.php"
      via: "Dependency injection for filename generation"
      pattern: "ExportService.*exportService"
    - from: "app/Jobs/ExportSyncLogs.php"
      to: "Storage::disk('exports')"
      via: "File storage for generated CSV files"
      pattern: "Storage::disk.*exports"
---

<objective>
Build foundation for data export functionality including library installation, storage configuration, and shared export service.

Purpose: Provide infrastructure and common utilities for export jobs
Output: Installed libraries, configured storage disk, and ExportService helper
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/phases/09-data-flows-caching-operations/09-00-EXPORT-PLAN.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Key Patterns from Previous Phases

From Phase 4 (Background Processing):
- **JobStatus model** (`app/Models/JobStatus.php`) — Status tracking with enum
- **TenantAwareJob base class** — Abstract base with tenant context

From Phase 6 (Catalog Synchronization):
- **SyncLog model** — tenant_id, status, products_synced, timestamps
- **Product model** — tenant_id, name, sku, price, stock_status

# Export Requirements (from CONTEXT.md)

**Format Requirements:**
- CSV with UTF-8 BOM for Excel compatibility
- Excel (XLSX) for product catalogs
- Filename pattern: {type}_{tenant_slug}_{date}.{ext}

**Storage Requirements:**
- Private disk (no public access)
- Signed URLs for secure downloads
- 24-hour file retention

**Filter Requirements:**
- Date range (start_date, end_date)
- Tenant selection
- Status filter (for sync logs)
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Install export libraries (league/csv, phpspreadsheet)</name>
  <files>composer.json</files>
  <behavior>
    Test 1: league/csv package installed (version ^9.15)
    Test 2: phpspreadsheet package installed (version ^2.0)
    Test 3: Packages loadable via autoloader
  </behavior>
  <action>
    Install CSV and Excel export libraries:

    ```bash
    composer require league/csv phpoffice/phpspreadsheet
    ```

    Verify installation:
    - Check composer.json for league/csv and phpoffice/phpspreadsheet entries
    - Run `composer show league/csv phpspreadsheet/phpoffice` to verify versions
    - No autoloader regeneration needed (composer handles it)

    These libraries provide:
    - league/csv: UTF-8 CSV generation with proper escaping, BOM for Excel compatibility
    - phpspreadsheet: XLSX file generation with memory-efficient cell writing
  </action>
  <verify>
    <automated>composer show league/csv phpspreadsheet/phpoffice | grep -E "versions |name "</automated>
  </verify>
  <done>Export libraries installed and loadable, composer.json updated</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Configure exports disk in filesystems</name>
  <files>config/filesystems.php</files>
  <behavior>
    Test 1: Exports disk configured with local driver
    Test 2: Exports disk root points to storage/app/exports
    Test 3: Exports disk visibility set to private
  </behavior>
  <action>
    Configure exports disk in config/filesystems.php:

    Add 'exports' disk to disks array:
    ```php
    'exports' => [
        'driver' => 'local',
        'root' => storage_path('app/exports'),
        'url' => env('APP_URL').'/storage/exports',
        'visibility' => 'private',
        'throw' => false,
    ],
    ```

    Key points:
    - Private visibility (no public access)
    - Signed URLs provide temporary secure access
    - Local driver for file storage
    - Root path: storage/app/exports
  </action>
  <verify>
    <automated>grep -A 5 "'exports' =>" config/filesystems.php | grep -E "driver|root|visibility"</automated>
  </verify>
  <done>Exports disk configured with private visibility and local driver</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Create ExportService for common export logic</name>
  <files>app/Services/ExportService.php</files>
  <behavior>
    Test 1: generateFilename() returns correct format with type, tenant slug, date, extension
    Test 2: applyFilters() applies date range filter to query builder
    Test 3: applyFilters() applies tenant filter to query builder
    Test 4: applyFilters() applies status filter to query builder
    Test 5: estimateRowCount() returns accurate count from query
  </behavior>
  <action>
    Create app/Services/ExportService.php with helper methods:

    ```php
    <?php

    namespace App\Services;

    use App\Models\Tenant;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Support\Facades\DB;

    class ExportService
    {
        public function generateFilename(string $type, Tenant $tenant, string $format): string
        {
            $date = now()->format('Ymd');
            $slug = $tenant->slug;
            $ext = $format === 'csv' ? 'csv' : 'xlsx';
            return "{$type}_{$slug}_{$date}.{$ext}";
        }

        public function applyFilters(Builder $query, array $filters): Builder
        {
            if (!empty($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }

            if (!empty($filters['tenant_id'])) {
                $query->where('tenant_id', $filters['tenant_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return $query;
        }

        public function estimateRowCount(Builder $query): int
        {
            return $query->count();
        }
    }
    ```

    Key points:
    - generateFilename() uses pattern: {type}_{tenant_slug}_{date}.{ext}
    - applyFilters() applies all filters with AND logic
    - estimateRowCount() returns integer count for limit validation
    - Reusable across ExportSyncLogs and ExportProductCatalog jobs
  </action>
  <verify>
    <automated>php artisan test --filter=ExportServiceTest</automated>
  </verify>
  <done>ExportService created with filename generation, filter application, and row counting logic</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Create migration for exports directory</name>
  <files>database/migrations/2026_03_14_000000_add_exports_disk_to_filesystems.php</files>
  <behavior>
    Test 1: Migration runs successfully
    Test 2: storage/app/exports directory exists after migration
    Test 3: Directory has correct permissions (writable by app user)
  </behavior>
  <action>
    Create migration to ensure exports directory exists:

    ```bash
    php artisan make:migration add_exports_disk_to_filesystems
    ```

    Edit migration file:
    ```php
    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Support\Facades\Storage;

    return new class extends Migration
    {
        public function up(): void
        {
            // Ensure exports directory exists
            Storage::disk('local')->makeDirectory('exports');
        }

        public function down(): void
        {
            // Clean up exports directory
            Storage::disk('local')->deleteDirectory('exports');
        }
    };
    ```

    Run migration:
    ```bash
    php artisan migrate
    ```

    Key points:
    - Creates storage/app/exports directory
    - Uses Laravel Storage facade for cross-platform compatibility
    - Reversible migration (cleanup on rollback)
  </action>
  <verify>
    <automated>test -d storage/app/exports && echo "Directory exists"</automated>
  </verify>
  <done>Exports directory created and configured with correct permissions</done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] Export libraries installed (league/csv, phpspreadsheet)
- [ ] Exports disk configured in config/filesystems.php
- [ ] ExportService created with helper methods
- [ ] storage/app/exports directory exists
- [ ] Migration runs successfully
- [ ] All tests passing (ExportServiceTest)

### Integration Verification

- [ ] Libraries loadable via composer autoloader
- [ ] ExportService can be instantiated via dependency injection
- [ ] Storage::disk('exports') returns configured disk instance
- [ ] Directory writable by application user

</verification>

<success_criteria>

1. Export libraries (league/csv, phpspreadsheet) installed and loadable
2. Exports disk configured with private visibility
3. ExportService provides filename generation, filter application, and row counting
4. storage/app/exports directory exists with correct permissions

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-01a-SUMMARY.md`

</output>
