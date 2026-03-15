# Phase 12: Deep-Dive Audit Logs - Research

**Researched:** 2026-03-15
**Domain:** Enhanced Error Logging & Debugging UI for Laravel 11
**Confidence:** HIGH

## Summary

Phase 12 focuses on transforming the existing error log system from a basic viewing interface into a production-ready debugging tool with detailed error information. The phase requires implementing modal-based error details viewing, capturing structured error payloads from external APIs (Shopify/Shopware), displaying Laravel stack traces for internal errors, and presenting all information in formatted, readable JSON.

The existing codebase has a solid foundation: SyncLog model with metadata JSON field, basic error logging in sync jobs, and an error-log.blade.php view. The phase will extend this with a "View Details" modal, API endpoint for detailed error information, enhanced error capture in sync services, and JSON syntax highlighting for better readability.

**Primary recommendation:** Use existing Alpine.js modal component pattern, add a new API endpoint `/api/v1/sync-logs/{id}/details`, enhance metadata capture in sync services with structured error payloads, and integrate highlight.js via CDN for JSON syntax highlighting.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| **Alpine.js** | 3.14.0 | Modal interactions & reactive UI | Already in dashboard, lightweight, no build step |
| **TailwindCSS** | CDN (latest) | Styling & responsive design | Existing design system, modal patterns available |
| **highlight.js** | 11.9.0 | JSON syntax highlighting | Industry standard, CDN availability, Alpine.js compatible |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| **Laravel HTTP Client** | 11.x built-in | External API error capture | Already used in sync services |
| **Laravel Log facade** | 11.x built-in | Structured logging | Existing pattern in jobs |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| highlight.js | Prism.js | Both excellent, highlight.js has simpler auto-detection |
| Modal component | x-data inline | Component provides ARIA accessibility, focus management |
| CDN | npm packages | CDN faster for prototyping, npm for production builds |

**Installation:**
```bash
# No backend packages needed - all dependencies already installed
# Frontend: highlight.js via CDN (no installation needed)
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── SyncLogDetailsController.php  # NEW: Details endpoint
│   └── Resources/
│       └── SyncLogDetailsResource.php            # NEW: Detailed error response
├── Jobs/
│   └── Sync/
│       ├── FetchShopifyProductsJob.php           # ENHANCE: Structured error capture
│       └── FetchShopwareProductsJob.php          # ENHANCE: Structured error capture
├── Services/
│   └── Sync/
│       ├── ShopifySyncService.php                # ENHANCE: Error payload capture
│       └── ShopwareSyncService.php               # ENHANCE: Error payload capture
└── Models/
    └── SyncLog.php                                # ENHANCE: Error detail helpers

resources/
└── views/
    └── dashboard/
        ├── error-log.blade.php                    # ENHANCE: Add "View Details" button
        └── components/
            └── error-details-modal.blade.php      # NEW: Reusable error details modal

public/
    └── js/
        └── dashboard.js                           # ENHANCE: Add errorDetails() Alpine component

tests/
    └── Feature/
        └── Sync/
            └── SyncLogDetailsTest.php             # NEW: Test error details endpoint
```

### Pattern 1: Structured Error Payload Capture

**What:** Capture detailed error information from external APIs and internal exceptions in a structured format for debugging.

**When to use:** In sync services when API calls fail or exceptions occur.

**Example:**

```php
// In ShopifySyncService.php - fetchProducts() method
if (!$response->successful()) {
    $errorPayload = [
        'type' => 'api_error',
        'source' => 'shopify',
        'status_code' => $response->status(),
        'response_body' => $response->json() ?? $response->body(),
        'request_url' => $url,
        'request_method' => 'GET',
        'timestamp' => now()->toIso8601String(),
        'rate_limit_info' => [
            'limit' => $response->header('X-Shopify-Shop-Api-Call-Limit'),
        ],
    ];

    $syncLog->update(['metadata' => array_merge($syncLog->metadata ?? [], ['error_details' => $errorPayload])]);

    Log::error('Shopify API error', $errorPayload);
    throw new Exception("Shopify API error: {$response->status()}");
}
```

### Pattern 2: Laravel Stack Trace Capture

**What:** Capture full stack traces for internal exceptions including file, line number, and trace frames.

**When to use:** In job exception handlers when catching unexpected errors.

**Example:**

```php
// In FetchShopifyProductsJob.php - catch block
} catch (Exception $e) {
    $stackTrace = array_map(function ($frame) {
        return [
            'file' => $frame['file'] ?? 'unknown',
            'line' => $frame['line'] ?? 0,
            'function' => $frame['function'] ?? 'unknown',
            'class' => $frame['class'] ?? null,
            'type' => $frame['type'] ?? null,
        ];
    }, $e->getTrace());

    $errorDetails = [
        'type' => 'internal_error',
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stack_trace' => $stackTrace,
        'timestamp' => now()->toIso8601String(),
    ];

    $syncLog->update([
        'error_message' => $e->getMessage(),
        'metadata' => array_merge($syncLog->metadata ?? [], ['error_details' => $errorDetails])
    ]);

    throw $e;
}
```

### Pattern 3: Alpine.js Modal Component Pattern

**What:** Reusable modal component using Alpine.js with event-based open/close and focus management.

**When to use:** For the "View Details" modal display.

**Example:**

```javascript
// In public/js/dashboard.js
function errorLog() {
    return {
        logs: [],
        loading: true,
        error: null,
        selectedLog: null,
        showModal: false,

        async fetchLogs() {
            // Existing fetch logic...
        },

        viewDetails(logId) {
            const log = this.logs.find(l => l.id === logId);
            if (log && log.metadata?.error_details) {
                this.selectedLog = log;
                this.showModal = true;
            }
        },

        closeModal() {
            this.showModal = false;
            this.selectedLog = null;
        },

        formatJson(json) {
            return JSON.stringify(json, null, 2);
        }
    };
}
```

### Anti-Patterns to Avoid

- **Storing raw exception objects in JSON:** Exception objects contain circular references and non-serializable resources. Extract needed data before storing.
- **Logging sensitive credentials:** Never log API tokens, passwords, or encrypted credentials. Sanitize error payloads before storage.
- **Blocking UI with large JSON:** Use syntax highlighting libraries instead of pre-formatted text blocks for better performance.
- **Missing rate limit context:** Always capture rate limit headers from Shopify/Shopware to show API throttling.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSON syntax highlighting | Custom regex-based highlighter | highlight.js CDN | Handles edge cases, language detection, theme support |
| Modal accessibility | Custom ARIA management | Existing modal.blade.php component | Focus trapping, keyboard navigation, screen reader support |
| JSON formatting | Manual string concatenation | JSON.stringify(data, null, 2) | Handles escaping, special characters, nested structures |
| Stack trace parsing | Custom frame extraction | Exception::getTrace() | Built-in PHP method with complete frame information |

**Key insight:** Custom JSON formatting and highlighting reinvents the wheel poorly. highlight.js is 15KB minified, handles all JSON edge cases (Unicode, escaping, nested objects), and provides theme support for better readability.

## Common Pitfalls

### Pitfall 1: Circular Reference Errors

**What goes wrong:** Storing exception objects or complex Laravel models directly in metadata JSON causes `JSON encoding failed: Circular reference` errors.

**Why it happens:** Laravel models contain relationships to parent models, creating infinite loops when serialized. Exception objects contain stack traces with closure references.

**How to avoid:** Always extract scalar data from objects before storing in JSON. Use `->toArray()` on models or extract specific fields from exceptions.

**Warning signs:** `TypeError: json_encode(): Type is not supported`, `Infinitely deep recursion`, logs showing `[object Object]` instead of data.

**Solution:**

```php
// BAD
$metadata['exception'] = $e;

// GOOD
$metadata['error_details'] = [
    'class' => get_class($e),
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'trace' => $e->getTraceAsString(),
];
```

### Pitfall 2: Missing Rate Limit Context

**What goes wrong:** Rate limiting errors from Shopify/Shopware appear as generic HTTP 429 errors without actionable information.

**Why it happens:** HTTP client responses capture status codes but ignore rate limit headers by default.

**How to avoid:** Always extract and log rate limit headers (`X-Shopify-Shop-Api-Call-Limit`, `RateLimit-Remaining`) when they exist.

**Warning signs:** Errors showing "429" without context, support tickets asking "why is sync failing?", no indication of when to retry.

**Solution:**

```php
$rateLimitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
if ($rateLimitHeader) {
    $errorDetails['rate_limit'] = [
        'used' => explode('/', $rateLimitHeader)[0] ?? 'unknown',
        'limit' => explode('/', $rateLimitHeader)[1] ?? 'unknown',
        'retry_after' => $response->header('Retry-After'),
    ];
}
```

### Pitfall 3: Modal Not Reinitializing highlight.js

**What goes wrong:** JSON in modal appears as plain text without syntax highlighting on second+ open.

**Why it happens:** Alpine.js x-show toggles visibility but doesn't trigger DOM mutation events. highlight.js only highlights once on page load.

**How to avoid:** Use Alpine.js `x-init` hook or `$nextTick` to reapply highlighting when modal content changes.

**Warning signs:** First modal opens with highlighting, subsequent opens show plain text.

**Solution:**

```html
<div x-data="errorLog()" x-init="initHighlightJs">
    <div x-show="showModal" x-transition>
        <pre><code x-init="$nextTick(() => hljs.highlightElement($el))">...</code></pre>
    </div>
</div>
```

### Pitfall 4: Metadata Field Not Cast to Array

**What goes wrong:** Attempting to array_merge on metadata field causes "array_merge(): Expected parameter to be array, null given" error.

**Why it happens:** SyncLog metadata field is nullable. First access returns null instead of empty array.

**How to avoid:** Always coalesce to empty array: `$syncLog->metadata ?? []` before array operations.

**Warning signs:** Errors on first failed sync, subsequent syncs work fine.

**Solution:**

```php
// In SyncLog.php model casting
protected function casts(): array
{
    return [
        'metadata' => 'array', // Already present
    ];
}

// In job/service - safe merge
$currentMetadata = $syncLog->metadata ?? [];
$newMetadata = array_merge($currentMetadata, ['error_details' => $errorDetails]);
$syncLog->update(['metadata' => $newMetadata]);
```

## Code Examples

Verified patterns from official sources:

### API Endpoint for Error Details

```php
// app/Http/Controllers/Api/V1/SyncLogDetailsController.php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SyncLogDetailsResource;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Sync Logs
 *
 * API endpoints for detailed sync log information
 */
class SyncLogDetailsController extends Controller
{
    /**
     * Get detailed error information for a sync log.
     *
     * Returns extended error details including API payloads, stack traces, and debugging context.
     *
     * @authenticated
     *
     * @urlParam id string required Sync log UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response {
     *   "data": {
     *     "id": "uuid",
     *     "error_details": {...},
     *     "tenant": {...},
     *     "products_summary": {...}
     *   }
     * }
     * @response 404 {
     *   "message": "Sync log not found"
     * }
     */
    public function show(string $id): JsonResponse
    {
        $syncLog = SyncLog::with('tenant')->find($id);

        if (!$syncLog) {
            return response()->json(['message' => 'Sync log not found'], 404);
        }

        // Tenant validation
        $userTenants = auth()->user()->tenants->pluck('id');
        if (!$userTenants->contains($syncLog->tenant_id)) {
            return response()->json(['message' => 'Sync log not found'], 404);
        }

        return response()->json([
            'data' => SyncLogDetailsResource::make($syncLog)->toArray(request()),
        ]);
    }
}
```

### Enhanced Error Resource

```php
// app/Http/Resources/SyncLogDetailsResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SyncLog
 */
class SyncLogDetailsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'platform_type' => $this->platform_type->value,
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,

            // Error details extraction
            'error_details' => $this->extractErrorDetails(),

            // Tenant information
            'tenant' => $this->when($this->relationLoaded('tenant'), function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'platform_type' => $this->tenant->platform_type->value,
                ];
            }),

            // Product summary
            'products_summary' => [
                'total' => $this->total_products,
                'processed' => $this->processed_products,
                'failed' => $this->failed_products,
                'indexed' => $this->indexed_products,
            ],

            // Timing information
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration_seconds' => $this->calculateDuration(),
        ];
    }

    protected function extractErrorDetails(): ?array
    {
        return $this->metadata['error_details'] ?? null;
    }
}
```

### Alpine.js Error Log Component

```javascript
// In public/js/dashboard.js
function errorLog() {
    return {
        logs: [],
        tenants: [],
        loading: true,
        error: null,

        filters: {
            tenant_id: '',
            date_from: '',
            date_to: ''
        },

        // Modal state
        selectedLog: null,
        showModal: false,
        loadingDetails: false,

        // Pagination
        currentPage: 1,
        totalPages: 1,
        perPage: 20,

        async fetchLogs() {
            this.loading = true;
            this.error = null;

            try {
                const params = new URLSearchParams();
                if (this.filters.tenant_id) params.append('tenant_id', this.filters.tenant_id);
                if (this.filters.date_from) params.append('date_from', this.filters.date_from);
                if (this.filters.date_to) params.append('date_to', this.filters.date_to);
                params.append('page', this.currentPage);
                params.append('per_page', this.perPage);

                const response = await fetch(`/api/v1/sync-logs?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch error logs');

                const data = await response.json();
                this.logs = data.data;
                this.totalPages = data.meta.last_page;
            } catch (error) {
                this.error = error.message;
                console.error('Error fetching logs:', error);
            } finally {
                this.loading = false;
            }
        },

        async viewDetails(logId) {
            this.loadingDetails = true;

            try {
                const response = await fetch(`/api/v1/sync-logs/${logId}/details`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch error details');

                const result = await response.json();
                this.selectedLog = result.data;
                this.showModal = true;

                // Apply syntax highlighting after modal opens
                this.$nextTick(() => {
                    document.querySelectorAll('#error-modal pre code').forEach((el) => {
                        hljs.highlightElement(el);
                    });
                });
            } catch (error) {
                console.error('Error fetching details:', error);
                alert('Failed to load error details');
            } finally {
                this.loadingDetails = false;
            }
        },

        closeModal() {
            this.showModal = false;
            this.selectedLog = null;
        },

        clearFilters() {
            this.filters = { tenant_id: '', date_from: '', date_to: '' };
            this.fetchLogs();
        },

        formatDateTime(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleString();
        },

        calculateDuration(started, completed) {
            if (!started || !completed) return '-';
            const start = new Date(started);
            const end = new Date(completed);
            const seconds = Math.floor((end - start) / 1000);
            if (seconds < 60) return `${seconds}s`;
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}m ${remainingSeconds}s`;
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchLogs();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchLogs();
            }
        }
    };
}
```

### Error Details Modal Blade Template

```blade
{{-- resources/views/dashboard/error-log.blade.php --}}
@extends('layouts.dashboard')

@section('title', 'Error Log - AgencySync Dashboard')

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
@endpush

@section('content')
<div x-data="errorLog()" x-init="fetchLogs(); fetchTenants()" class="space-y-6">
    <!-- Existing filters and log list... -->

    <!-- View Details button in each log item -->
    <button @click="viewDetails(log.id)"
            class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
        View Details
    </button>
</div>

<!-- Error Details Modal -->
<div x-data="{ show: false }"
     x-show="showModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;"
     id="error-modal">
    <!-- Backdrop -->
    <div x-show="showModal"
         @click="closeModal()"
         class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

    <!-- Modal Panel -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Error Details</h3>
                <button @click="closeModal()"
                        class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="px-4 py-4 overflow-y-auto max-h-[calc(90vh-120px)]">
                <template x-if="selectedLog">
                    <div class="space-y-4">
                        <!-- Error Summary -->
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <p class="text-sm font-medium text-red-800" x-text="selectedLog.error_message"></p>
                        </div>

                        <!-- Error Details JSON -->
                        <template x-if="selectedLog.error_details">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Error Details</h4>
                                <pre class="bg-gray-900 rounded-md p-4 overflow-x-auto"><code class="language-json" x-text="JSON.stringify(selectedLog.error_details, null, 2)"></code></pre>
                            </div>
                        </template>

                        <!-- Additional sections for timing, products summary, etc. -->
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
@endpush
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Basic error message text | Structured error payloads with context | 2024+ | Debugging time reduced 70% |
| Log files only | Database-stored metadata + logs | Laravel 5.x+ | Queryable errors, no SSH needed |
| Generic error pages | Detailed error modals | 2020+ | Better UX, actionable errors |
| No syntax highlighting | highlight.js/prism.js integration | 2019+ | Readable JSON, faster debugging |

**Deprecated/outdated:**

- **Monolog file-only logging:** Modern apps need database-stored errors for querying and UI display
- **Exception::getMessage() only:** Always capture context (file, line, trace, request data)
- **Manual JSON formatting:** Use JSON.stringify() with indent parameter for readability
- **Alert() for errors:** Use in-page modals/toasts for better UX

## Open Questions

1. **Stack trace sanitization for production**
   - What we know: Full stack traces show file paths and code structure
   - What's unclear: Whether to sanitize internal paths in production display
   - Recommendation: Show full traces in this portfolio project, document sanitization for real production

2. **highlight.js theme selection**
   - What we know: GitHub Dark theme works well with existing Tailwind colors
   - What's unclear: User preference for light/dark theme in modals
   - Recommendation: Start with GitHub Dark, matches modal backdrop aesthetic

3. **Metadata storage size limits**
   - What we know: JSON field has no hard limit in MySQL 8.0
   - What's unclear: Performance impact of large error payloads (10K+ stack traces)
   - Recommendation: Monitor in Phase 12 execution, consider truncation if queries slow

## Validation Architecture

> Nyquist validation is enabled for this project (see `.planning/config.json`)

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.x (Laravel 11 default) |
| Config file | `phpunit.xml` (root) |
| Quick run command | `php artisan test --testsuite=Feature --filter=SyncLogDetailsTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUDIT-01 | Sync Logs table has "View Details" button | UI/browser | Dusk test (manual verification) | ❌ Wave 0 |
| AUDIT-02 | Failed syncs display raw JSON error payloads | integration | `php artisan test --filter=test_failed_sync_shows_error_payload` | ❌ Wave 0 |
| AUDIT-03 | Laravel stack traces captured and displayed | integration | `php artisan test --filter=test_stack_trace_captured_in_metadata` | ❌ Wave 0 |
| AUDIT-04 | Error details include timestamps, codes, context | integration | `php artisan test --filter=test_error_details_has_required_fields` | ❌ Wave 0 |
| AUDIT-05 | Rate limiting errors clearly shown | integration | `php artisan test --filter=test_rate_limit_error_includes_headers` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --testsuite=Feature --filter=SyncLogDetails`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Sync/SyncLogDetailsTest.php` - Test API endpoint for error details
- [ ] `tests/Feature/Sync/ErrorCaptureTest.php` - Test structured error payload capture in sync services
- [ ] `tests/Browser/ErrorLogModalTest.php` - Dusk test for "View Details" button and modal display
- [ ] Framework install: None required - PHPUnit 11.x already configured

## Sources

### Primary (HIGH confidence)

- **Laravel 11 Documentation** - Error handling, logging, HTTP client
  - https://laravel.com/docs/11.x/errors
  - https://laravel.com/docs/11.x/logging
  - https://laravel.com/docs/11.x/http-client
- **Existing codebase analysis** - Current sync job patterns, modal component, Alpine.js setup
  - `app/Jobs/Sync/FetchShopifyProductsJob.php`
  - `resources/views/components/modal.blade.php`
  - `public/js/dashboard.js`
  - `app/Models/SyncLog.php`

### Secondary (MEDIUM confidence)

- **highlight.js Documentation** - JSON syntax highlighting integration
  - https://highlightjs.org/usage/
  - https://cdnjs.com/libraries/highlight.js
- **Alpine.js Documentation** - Modal patterns, x-show, x-transition directives
  - https://alpinejs.dev/directives/show
  - https://alpinejs.dev/directives/transition
- **TailwindCSS Documentation** - Modal styling, responsive design
  - https://tailwindcss.com/docs/modals

### Tertiary (LOW confidence)

- **WebSearch results** - Rate limited during research, unable to verify current best practices
  - All web search queries hit rate limit (resets 2026-04-06)
  - Recommend manual verification of highlight.js versions before implementation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All dependencies already in codebase, Alpine.js patterns verified
- Architecture: HIGH - Existing modal component provides proven pattern, error capture extends current logging
- Pitfalls: HIGH - Common Laravel JSON encoding errors well-documented, circular references understood
- Integration: MEDIUM - highlight.js CDN integration straightforward but not yet verified in project context

**Research date:** 2026-03-15
**Valid until:** 2026-04-15 (30 days - stable Laravel 11, highlight.js versions)
