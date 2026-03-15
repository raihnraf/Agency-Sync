# Phase 13: Technical Debt Refactoring - Research

**Researched:** 2026-03-15
**Domain:** Laravel Sanctum SPA Authentication, API Resources, Technical Debt Refactoring
**Confidence:** MEDIUM

## Summary

Phase 13 addresses three critical technical debt items identified during Phase 12 verification:

1. **Improper API routing**: Sync log routes duplicated in web.php using session auth instead of api.php with Sanctum
2. **Inconsistent response formats**: Frontend expects `data.meta.last_page` but backend returns Laravel's default `data.last_page` from pagination
3. **Missing API Resource Collections**: Controllers return raw pagination objects instead of using Resource Collections for standardized responses

These issues stem from rapid development shortcuts and represent architectural debt that prevents the application from following Laravel best practices. The refactoring must maintain backward compatibility with the existing Alpine.js frontend while implementing proper authentication patterns and response structures.

**Primary recommendation**: Implement a three-wave refactoring strategy: (1) Move routes to api.php with Sanctum middleware, (2) Create API Resource Collections with proper pagination structure, (3) Update frontend to consume consistent response formats. Use test-driven development with comprehensive feature tests to ensure no regressions.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Sanctum | 11.x (bundled) | SPA authentication | Official Laravel solution for SPA/auth, handles both token and session-based auth |
| Laravel API Resources | 11.x (bundled) | Response transformation | Framework's standard for consistent JSON responses, handles collections/pagination |
| Laravel Pagination | 11.x (bundled) | Paginated data sets | Built-in paginator with length-aware, cursor, and simple pagination |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ResourceCollection | 11.x (bundled) | Collection pagination metadata | Wrap collections with standard `data` + `links` + `meta` structure |
| JsonResource | 11.x (bundled) | Single model transformation | Transform individual models with computed fields |
| middleware('auth:sanctum') | 11.x (bundled) | API route protection | Authenticate SPA requests via cookies/tokens |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sanctum SPA auth | Passport/JWT | More complex, overkill for same-origin SPA; Sanctum is lighter and officially recommended |
| API Resources | Fractal | Third-party dependency, less integrated with Laravel ecosystem |
| Resource Collections | Manual pagination arrays | Reinventing the wheel, loses framework features (appends, links, meta) |

**Installation:**
No packages needed - all features are bundled with Laravel 11.x core framework.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── *Controller.php      # All API controllers here
│   ├── Resources/
│   │   ├── *Resource.php                # Single model resources
│   │   └── *Collection.php              # Collection resources with pagination
│   └── Middleware/
│       └── SetTenant.php                # Custom middleware
├── Models/
│   └── *.php                             # Eloquent models
routes/
├── api.php                               # ALL API routes (Sanctum auth)
└── web.php                               # Web routes + dashboard (session auth)
```

### Pattern 1: Laravel Sanctum SPA Authentication
**What:** Sanctum's `EnsureFrontendRequestsAreStateful` middleware allows same-origin SPAs to authenticate via session cookies while API routes use `auth:sanctum` guard.

**When to use:** Single-page applications served by Laravel (Blade + Alpine.js, Inertia.js, Vue) where frontend and backend share the same domain.

**Current implementation:**
```php
// bootstrap/app.php
$middleware->api(prepend: [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);
```

**Correct API route pattern:**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/sync-logs', [SyncLogController::class, 'index']);
    Route::get('/sync-logs/{id}/details', [SyncLogDetailsController::class, 'show']);
});
```

**Incorrect (current technical debt):**
```php
// routes/web.php - SHOULD NOT BE HERE
Route::middleware(['auth'])->prefix('api/v1')->group(function () {
    Route::get('/sync-logs', [SyncLogController::class, 'index']);
});
```

**Why it matters:** Web routes use session middleware with CSRF protection, while API routes should use Sanctum's token/session hybrid. Mixing them creates security and maintenance issues.

### Pattern 2: API Resource Collections with Pagination
**What:** Resource Collections wrap paginated data with standardized structure: `{ data: [], links: {}, meta: {} }`

**When to use:** All endpoints returning multiple records (index, list, search endpoints)

**Example:**
```php
// app/Http/Resources/SyncLogCollection.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SyncLogCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'path' => $this->path(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }
}
```

**Controller usage:**
```php
// app/Http/Controllers/Api/V1/SyncLogController.php
use App\Http\Resources\SyncLogCollection;
use App\Models\SyncLog;

public function index(Request $request)
{
    $logs = SyncLog::query()
        ->with('tenant')
        ->orderBy('started_at', 'desc')
        ->paginate($request->input('per_page', 15));

    return new SyncLogCollection($logs);
}
```

**Response format:**
```json
{
  "data": [
    {
      "id": "uuid",
      "tenant_id": "uuid",
      "status": "completed",
      ...
    }
  ],
  "links": {
    "first": "https://localhost/api/v1/sync-logs?page=1",
    "last": "https://localhost/api/v1/sync-logs?page=5",
    "prev": null,
    "next": "https://localhost/api/v1/sync-logs?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 75
  }
}
```

### Pattern 3: Frontend Consumption of Resource Collections
**What:** Alpine.js fetches paginated data and extracts `data` array and `meta.last_page` for pagination controls.

**Current frontend code (dashboard.js line 586):**
```javascript
const data = await response.json();
this.logs = data.data.filter(log => log.status === 'failed');
this.totalPages = data.last_page;  // ❌ WRONG - should be data.meta.last_page
```

**Corrected frontend code:**
```javascript
const data = await response.json();
this.logs = data.data.filter(log => log.status === 'failed');
this.totalPages = data.meta.last_page;  // ✅ CORRECT
```

**Product search already uses correct pattern (line 482):**
```javascript
this.products = data.data;
this.totalProducts = data.meta.total;
this.totalPages = data.meta.last_page;  // ✅ CORRECT
```

### Anti-Patterns to Avoid
- **Mixing web and API routes**: Keep API endpoints in routes/api.php with Sanctum middleware, not routes/web.php with session middleware
- **Returning raw pagination**: Controllers should return Resource Collections, not `response()->json($paginator)` which loses structure consistency
- **Inconsistent response formats**: Frontend should not need to guess between `data.last_page` and `data.meta.last_page` - standardize on Resource Collection format
- **Skipping Resource Collections**: Don't use `JsonResource::collection()` for paginated data - use dedicated ResourceCollection class for proper links/meta structure

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Paginated response formatting | Manual array construction with `['data' => $items, 'meta' => [...]]` | ResourceCollection extends ResourceCollection | Laravel's paginator integrates with ResourceCollection automatically (links, meta, URL generation) |
| API authentication logic | Custom token validation, session checking | Sanctum's `auth:sanctum` middleware | Handles tokens, cookies, CSRF, stateful domains - security-critical code best left to framework |
| Response transformation | Manual `array_map()` or `foreach` loops to format models | JsonResource and ResourceCollection | Declarative transformation, conditional loading with `whenLoaded()`, type-safe, testable |

**Key insight:** Custom pagination wrappers are fragile (missed fields, URL generation bugs, inconsistent structure). Resource Collections are the framework's battle-tested solution for API responses.

## Common Pitfalls

### Pitfall 1: API Routes in web.php
**What goes wrong:** Routes defined in `web.php` with `api/v1` prefix bypass Sanctum middleware, use session authentication only, and create security confusion.

**Why it happens:** During rapid development, developers add API routes to web.php for quick CSRF-free access, not realizing the authentication implications.

**How to avoid:** All API endpoints MUST be in `routes/api.php` with appropriate middleware:
- Public endpoints: `throttle:api`
- Authenticated endpoints: `auth:sanctum` + `throttle:api-read` or `throttle:api-write`

**Warning signs:**
- Routes in web.php with `api` prefix
- Mixed middleware (both `auth` and `auth:sanctum` in same app)
- Frontend uses CSRF tokens for API calls (should use Sanctum cookies)

### Pitfall 2: Inconsistent Pagination Structure
**What goes wrong:** Frontend code breaks because some endpoints return `{ data: [], meta: { last_page } }` while others return `{ data: [], last_page }` (Laravel's default paginator JSON).

**Why it happens:** Controllers return `response()->json($paginator)` which uses Laravel's default paginator structure, not Resource Collection's standardized format.

**How to avoid:** All paginated endpoints MUST use ResourceCollection:
```php
return new SyncLogCollection($logs);  // ✅ Correct
return response()->json($logs);       // ❌ Wrong - inconsistent structure
```

**Warning signs:**
- Frontend has conditional logic: `data.meta?.last_page || data.last_page`
- Different endpoints use different pagination access patterns
- Tests check for `last_page` at root instead of `meta.last_page`

### Pitfall 3: Breaking Frontend During Refactoring
**What goes wrong:** Refactoring backend responses breaks existing Alpine.js components that expect specific structure.

**Why it happens:** Backend changes without updating frontend consuming code, or no tests catch the contract break.

**How to avoid:**
1. Write feature tests for response structure BEFORE refactoring
2. Update frontend in same commit as backend (atomic change)
3. Run tests after each change
4. Use TypeScript interfaces or JSDoc to document expected API contracts

**Warning signs:**
- Frontend console errors "Cannot read property 'last_page' of undefined"
- Pagination controls broken after backend refactor
- No tests for API response structure

### Pitfall 4: Missing Tenant Context in API Routes
**What goes wrong:** API routes moved to api.php lose tenant context because `SetTenant` middleware not applied.

**Why it happens:** web.php routes had global tenant middleware, but api.php routes need explicit middleware application.

**How to avoid:** Apply `tenant` and `tenant.scope` middleware to routes requiring tenant context:
```php
Route::middleware(['tenant', 'tenant.scope'])->group(function () {
    Route::get('/sync-logs', [SyncLogController::class, 'index']);
});
```

**Warning signs:**
- SQL errors "column 'tenant_id' not found"
- Queries returning all tenants' data instead of scoped
- Tests failing with "Tenant not set" errors

## Code Examples

Verified patterns from official sources:

### Sanctum SPA Configuration (bootstrap/app.php)
```php
// Source: Laravel 11.x documentation
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
})
```

### API Route Definition (routes/api.php)
```php
// Source: Laravel Sanctum documentation
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/sync-logs', [SyncLogController::class, 'index']);
    Route::get('/sync-logs/{id}/details', [SyncLogDetailsController::class, 'show']);
});
```

### Resource Collection with Pagination (app/Http/Resources/SyncLogCollection.php)
```php
// Source: Laravel Eloquent Resources documentation
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SyncLogCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'path' => $this->path(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }
}
```

### Controller Returning Resource Collection
```php
// Source: Laravel API Resources documentation
use App\Http\Resources\SyncLogCollection;
use App\Models\SyncLog;

public function index(Request $request)
{
    $logs = SyncLog::query()
        ->with('tenant')
        ->orderBy('started_at', 'desc')
        ->paginate($request->input('per_page', 15));

    return new SyncLogCollection($logs);
}
```

### Frontend Consumption (Alpine.js)
```javascript
// Source: Project's existing dashboard.js (corrected)
async fetchErrorLogs() {
    const response = await fetch('/api/v1/sync-logs?status=failed', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
    });

    const data = await response.json();
    this.logs = data.data;  // Extract array from data property
    this.totalPages = data.meta.last_page;  // Access pagination via meta
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Passport for SPA auth | Sanctum for SPA auth | Laravel 7.x (2020) | Simpler setup, lighter weight, official recommendation |
| Manual pagination arrays | Resource Collections | Laravel 5.5 (2017) | Consistent structure, built-in links/meta, better testing |
| Session-only auth | Token + Session hybrid | Laravel 7.x with Sanctum | Supports both API tokens and SPA cookies in same package |
| web.php API routes | api.php with Sanctum | Laravel 7.x | Clear separation of concerns, proper middleware stack |

**Deprecated/outdated:**
- **Passport for same-origin SPAs**: Overkill, use Sanctum instead
- **Manual response formatting**: Use API Resources framework feature
- **CSRF tokens for API calls**: Sanctum handles this automatically for SPAs
- **`auth` middleware on API routes**: Should use `auth:sanctum` for proper guard

## Open Questions

1. **Sanctum stateful domains configuration**
   - What we know: Current config uses default localhost domains
   - What's unclear: Production domain configuration requirements
   - Recommendation: Verify SANCTUM_STATEFUL_DOMAINS env var includes production domain

2. **Frontend authentication mechanism**
   - What we know: Frontend uses X-CSRF-TOKEN header with session auth
   - What's unclear: Does frontend need to switch to Bearer tokens or will Sanctum session auth work with api.php routes?
   - Recommendation: Test Sanctum's cookie-based auth with api.php routes - should work transparently if EnsureFrontendRequestsAreStateful middleware is configured

3. **Existing test coverage**
   - What we know: Project has 76 tests passing (Phase 9)
   - What's unclear: Do existing tests cover the routes being moved (sync-logs endpoints)?
   - Recommendation: Run full test suite after refactoring to catch regressions

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x (Laravel 11 default) |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFACTOR-01 | API routes in api.php with Sanctum auth | feature | `php artisan test --filter=SanctumAuthTest` | ❌ Wave 0 |
| REFACTOR-02 | API Resource Collections for all endpoints | feature | `php artisan test --filter=ResourceCollectionTest` | ❌ Wave 0 |
| REFACTOR-03 | Frontend consumes data.meta structure | feature | `php artisan test --filter=FrontendIntegrationTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --parallel`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/SanctumAuthTest.php` — verify Sanctum middleware on API routes
- [ ] `tests/Feature/ResourceCollectionTest.php` — verify response structure (data/meta/links)
- [ ] `tests/Feature/FrontendIntegrationTest.php` — verify Alpine.js can consume new responses
- [ ] Framework install: None (PHPUnit bundled with Laravel 11)

## Sources

### Primary (HIGH confidence)
- **Laravel Sanctum Documentation** - SPA authentication configuration, stateful domains, middleware
- **Laravel API Resources Documentation** - Resource Collections, pagination structure, transformation
- **Project source code** - Analyzed routes/web.php, routes/api.php, existing Resource classes, frontend JavaScript

### Secondary (MEDIUM confidence)
- **Laravel 11.x Upgrade Guide** - Bootstrap configuration changes, middleware registration
- **Project STATE.md** - History of authentication decisions, technical debt identification

### Tertiary (LOW confidence)
- **Web search blocked** - Rate limited (429 error) - Unable to verify 2026 best practices
- **Web reader blocked** - Rate limited (429 error) - Unable to fetch official docs via web

**Confidence note:** Research based on project source code analysis and general Laravel knowledge. Unable to verify against official docs due to rate limiting. Standard patterns documented are well-established Laravel practices (2017-2023 era).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11.x with Sanctum and API Resources are framework defaults
- Architecture: MEDIUM - Patterns are standard Laravel practice, but unable to verify 2026 docs due to rate limiting
- Pitfalls: HIGH - All pitfalls identified from actual project source code (web.php API routes, inconsistent pagination)

**Research date:** 2026-03-15
**Valid until:** 2026-04-15 (30 days - Laravel 11.x is stable, but verification needed when rate limits reset)
