---
phase: 09-data-flows-caching-operations
plan: 00-EXPORT
type: execute
wave: 0
depends_on: []
files_modified:
  - tests/Feature/ExportSyncLogsTest.php
  - tests/Feature/ExportProductCatalogTest.php
  - tests/Feature/ExportDataContentTest.php
  - tests/Feature/ExportControllerTest.php
  - tests/Unit/ExportServiceTest.php
autonomous: true
requirements:
  - DATAFLOW-01
  - DATAFLOW-02
  - DATAFLOW-03
must_haves:
  truths:
    - "Test stub files exist for all export features"
    - "Tests specify expected behavior before implementation"
    - "Tests provide clear verification criteria for implementation"
  artifacts:
    - path: "tests/Feature/ExportSyncLogsTest.php"
      provides: "Test stub for sync log CSV export functionality"
      min_lines: 30
    - path: "tests/Feature/ExportProductCatalogTest.php"
      provides: "Test stub for product catalog Excel export"
      min_lines: 30
    - path: "tests/Feature/ExportDataContentTest.php"
      provides: "Test stub for export data content validation"
      min_lines: 25
    - path: "tests/Feature/ExportControllerTest.php"
      provides: "Test stub for export API endpoints"
      min_lines: 35
    - path: "tests/Unit/ExportServiceTest.php"
      provides: "Test stub for export service helper logic"
      min_lines: 25
  key_links:
    - from: "tests/Feature/ExportSyncLogsTest.php"
      to: "app/Jobs/ExportSyncLogs.php"
      via: "Job dispatch and execution tests"
      pattern: "ExportSyncLogs::dispatch"
    - from: "tests/Feature/ExportControllerTest.php"
      to: "app/Http/Controllers/ExportController.php"
      via: "API endpoint tests"
      pattern: "POST.*exports"
    - from: "tests/Unit/ExportServiceTest.php"
      to: "app/Services/ExportService.php"
      via: "Helper logic tests"
      pattern: "ExportService::"
---

<objective>
Create test stubs that specify expected behavior for data export features, enabling TDD implementation in subsequent plans.

Purpose: Define clear test expectations before implementation, ensuring testable requirements and verification criteria
Output: Test stub files with placeholder assertions that document expected behavior
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Key Models and Patterns from Previous Phases

From Phase 4 (Background Processing):
- **JobStatus model** (`app/Models/JobStatus.php`) — Tracks job lifecycle with status enum (pending, running, completed, failed)
- **TenantAwareJob base class** (`app/Jobs/TenantAwareJob.php`) — Abstract base for tenant-aware queue jobs
- **SetTenantContext middleware** — Restores tenant context during job execution
- **QueueJobTracker service** — Automatic status tracking via queue events

From Phase 3 (Tenant Management):
- **Tenant model** (`app/Models/Tenant.php`) — UUID primary keys, status enum, encrypted credentials
- **User-Tenant relationship** — Many-to-many with pivot data

From Phase 6 (Catalog Synchronization):
- **SyncLog model** (`app/Models/SyncLog.php`) — tenant relationship, status fields, timestamps
- **Product model** (`app/Models/Product.php`) — Scout searchable, tenant-scoped

From Phase 7 (Admin Dashboard):
- **Alpine.js components** — Toast notification component for export ready notifications
- **Blade + Alpine.js patterns** — Client-side API calls, loading states, error handling
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create test stub for sync log CSV export</name>
  <files>tests/Feature/ExportSyncLogsTest.php</files>
  <behavior>
    Test 1: Export job dispatches successfully with filters
    Test 2: Export job generates CSV file with correct headers (Tenant, Status, Products Synced, Started At, Completed At, Duration)
    Test 3: Export job respects date range filters (start_date, end_date)
    Test 4: Export job respects tenant filter
    Test 5: Export job respects status filter (completed, failed, partially_failed)
    Test 6: Export job enforces 100K row limit
    Test 7: Export job updates JobStatus to completed with filepath result
    Test 8: Export job marks JobStatus as failed on error
    Test 9: Signed URL download link works for completed exports
  </behavior>
  <action>
    Create tests/Feature/ExportSyncLogsTest.php with placeholder assertions:

    - Use RefreshDatabase trait for database isolation
    - Create Tenant, User, and SyncLog factories
    - Test job dispatch: ExportSyncLogs::dispatch($jobStatus->id, $filters, 'csv')
    - Test CSV generation: Assert file exists at storage/exports/
    - Test filter application: Create sync logs with different dates/tenants/status, verify filtered export
    - Test row limit: Create 100K+ sync logs, assert job fails with limit error
    - Test JobStatus updates: Assert status transitions from pending → running → completed
    - Test signed URL: GET /api/v1/exports/{uuid} returns download_url with 24-hour expiration
    - Use $this->assertTrue(true) placeholders for Nyquist compliance
  </action>
  <verify>
    <automated>php artisan test --filter=ExportSyncLogsTest</automated>
  </verify>
  <done>Test file created with 9 test cases covering export job dispatch, CSV generation, filters, limits, status tracking, and download links</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Create test stub for product catalog Excel export</name>
  <files>tests/Feature/ExportProductCatalogTest.php</files>
  <behavior>
    Test 1: Export job dispatches successfully for tenant products
    Test 2: Export job generates XLSX file with product data (name, sku, price, stock_status, created_at)
    Test 3: Export job handles large catalogs (10K+ products) via chunking
    Test 4: Export job uses tenant-scoped product query
    Test 5: Export job filename follows pattern: products_{tenant_slug}_{date}.xlsx
    Test 6: Export job stores file in storage/exports/ directory
    Test 7: Export job completes within 5-minute timeout
  </behavior>
  <action>
    Create tests/Feature/ExportProductCatalogTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create Tenant, User, and Product factories (50 products for testing)
    - Test job dispatch: ExportProductCatalog::dispatch($jobStatus->id, $tenantId, 'xlsx')
    - Test XLSX generation: Assert file exists, verify XLSX format (file signature or PHPSpreadsheet reader)
    - Test chunking: Create 10K products, assert job completes without OOM error
    - Test tenant scoping: Create products for multiple tenants, verify export includes only target tenant products
    - Test filename pattern: Assert filename matches expected format
    - Test timeout: Mock large dataset, assert job doesn't exceed 300 seconds
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=ExportProductCatalogTest</automated>
  </verify>
  <done>Test file created with 7 test cases covering export job dispatch, XLSX generation, chunking, tenant scoping, file naming, and timeout handling</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Create test stub for export data content validation</name>
  <files>tests/Feature/ExportDataContentTest.php</files>
  <behavior>
    Test 1: CSV export includes tenant name column
    Test 2: CSV export includes timestamps (created_at, completed_at)
    Test 3: CSV export includes sync status values
    Test 4: Excel export includes product SKU and price
    Test 5: Export handles UTF-8 characters in tenant names and product names
    Test 6: Export handles special characters (commas, newlines) in CSV fields
  </behavior>
  <action>
    Create tests/Feature/ExportDataContentTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create test data with UTF-8 characters (tenant names with accents, emojis)
    - Create test data with special characters (product names with commas, quotes, newlines)
    - Test CSV content: Read generated CSV, assert tenant name column exists and has correct values
    - Test timestamp format: Assert created_at and completed_at in Y-m-d H:i:s format
    - Test status values: Assert status column contains valid enum values
    - Test Excel content: Use PHPSpreadsheet reader to verify SKU and price columns
    - Test UTF-8 handling: Assert UTF-8 BOM present in CSV, verify special chars render correctly
    - Test CSV escaping: Assert fields with commas/newlines are properly quoted
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=ExportDataContentTest</automated>
  </verify>
  <done>Test file created with 6 test cases covering export data content (tenant info, timestamps, status, UTF-8, CSV escaping)</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Create test stub for export API endpoints</name>
  <files>tests/Feature/ExportControllerTest.php</files>
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
    Create tests/Feature/ExportControllerTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create User and Tenant factories
    - Authenticate user via Sanctum
    - Test POST /exports/sync-logs: Assert job dispatched, JobStatus created, returns 202
    - Test POST /exports/products: Assert job dispatched, JobStatus created, returns 202
    - Test GET /exports/{uuid}: Mock completed JobStatus, assert download_url returned
    - Test 404 for pending: Assert GET returns 404 when job_status is pending
    - Test 404 for running: Assert GET returns 404 when job_status is running
    - Test signed URL expiration: Assert URL has 24-hour expiration timestamp
    - Test authentication: Assert 401 Unauthorized when not authenticated
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=ExportControllerTest</automated>
  </verify>
  <done>Test file created with 8 test cases covering export API endpoints (dispatch, download, authentication)</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Create test stub for export service helper logic</name>
  <files>tests/Unit/ExportServiceTest.php</files>
  <behavior>
    Test 1: Generate filename follows pattern: {type}_{tenant_slug}_{date}.{ext}
    Test 2: Generate filename for sync logs with CSV extension
    Test 3: Generate filename for products with XLSX extension
    Test 4: Apply date range filter to query correctly
    Test 5: Apply tenant filter to query correctly
    Test 6: Apply status filter to query correctly
    Test 7: Estimate row count returns accurate count
  </behavior>
  <action>
    Create tests/Unit/ExportServiceTest.php with placeholder assertions:

    - No database needed (unit tests for service logic)
    - Test filename generation: Assert ExportService::generateFilename('synclogs', $tenant, 'csv') returns 'synclogs_acme-inc_20260314.csv'
    - Test date format: Assert filename uses current date in Ymd format
    - Test filter application: Mock query builder, assert where() clauses called correctly
    - Test combined filters: Assert multiple filters applied with AND logic
    - Test row count estimation: Mock query builder, assert count() called and returned
    - Test filter validation: Assert invalid filters throw exceptions or return empty query
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=ExportServiceTest</automated>
  </verify>
  <done>Test file created with 7 test cases covering export service helper logic (filename generation, filter application, row counting)</done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] All 5 export test stub files created in tests/ directory
- [ ] Test files use appropriate traits (RefreshDatabase for feature tests)
- [ ] Test files reference models that exist (JobStatus, Tenant, Product, SyncLog)
- [ ] Test cases cover all export requirements (DATAFLOW-01/02/03)
- [ ] Placeholder assertions use $this->assertTrue(true) for Nyquist compliance
- [ ] Test names clearly describe expected behavior
- [ ] Test files committed to git

</verification>

<success_criteria>

1. All export test stub files exist and are syntactically valid PHP
2. Running `php artisan test` executes all stub tests (all pass with placeholder assertions)
3. Test cases provide clear specifications for implementation in subsequent plans
4. Tests reference correct models, jobs, and patterns from previous phases
5. All export requirement IDs (DATAFLOW-*) have corresponding test coverage

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-00-EXPORT-SUMMARY.md`

</output>
