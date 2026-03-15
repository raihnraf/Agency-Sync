# Phase 14: Critical Frontend Fixes - Research

**Researched:** 2026-03-15
**Domain:** Laravel 11 + Alpine.js frontend-backend integration
**Confidence:** HIGH

## Summary

Phase 14 addresses two critical broken user-facing flows that block core features: product search and sync trigger. Both flows have complete backend implementations but are broken due to frontend API endpoint mismatches. The fixes are straightforward (1-line changes) but critical for user functionality.

**Root Cause Analysis:**
1. **Product Search:** Frontend calls `/api/v1/tenants/{tenantId}/products` but backend route is `/api/v1/tenants/{tenantId}/search`
2. **Sync Trigger:** Frontend calls `/api/v1/tenants/{tenantId}/sync` but backend route is `/api/v1/sync/dispatch` with different parameter structure

**Primary Recommendation:** Fix frontend API calls to match existing backend routes. Both fixes are simple string replacements in JavaScript files that unblock core features immediately.

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| **SEARCH-01** | Agency admin can search products within a single client's catalog | Backend route exists, frontend needs endpoint correction |
| **SEARCH-07** | Search results only include products from selected client store (tenant isolation) | ProductSearchController implements tenant scoping, frontend needs correct endpoint |
| **SYNC-01** | Agency admin can trigger manual catalog sync for a specific client store | SyncController@dispatch exists, frontend needs API call correction |
| **UI-05** | Agency admin can trigger sync operation for each client store | Dashboard UI exists, needs correct API endpoint |
| **UI-07** | Agency admin can search products within a client's catalog | Dashboard search UI exists, needs correct API endpoint |

## Current State Analysis

### Broken Flow 1: Product Search

**File:** `public/js/dashboard.js:467`
**Component:** Product search functionality in dashboard

**Current (Broken) Implementation:**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/products?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
    method: 'GET',
    headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    }
});
```

**Problem:**
- Frontend calls `/api/v1/tenants/{tenantId}/products`
- This route does not exist in the API
- The correct route is `/api/v1/tenants/{tenantId}/search`

**Backend Route (Exists and Working):**
```php
// File: routes/api.php:78
Route::get('/tenants/{tenantId}/search', [ProductSearchController::class, 'search']);
```

**Backend Controller (Complete):**
- `ProductSearchController@search` implemented
- Tenant authorization via `whereHas('users')` relationship
- Elasticsearch integration via `ProductSearchService`
- Pagination support via Laravel paginator
- Fuzzy matching and filtering capabilities

**Impact:**
- Users cannot search products despite complete backend implementation
- Core feature completely broken
- 404 errors on product search attempts

**Affected Files:**
1. `public/js/dashboard.js:467` (main dashboard)
2. `resources/js/components/product-search.js:33` (reusable component)

### Broken Flow 2: Sync Trigger

**File:** `public/js/dashboard.js:181` and `resources/js/components/sync-status.js:40`
**Component:** Sync trigger button in dashboard

**Current (Broken) Implementation:**
```javascript
const response = await fetch(`/api/v1/tenants/${tenantId}/sync`, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    }
});
```

**Problem:**
- Frontend calls `/api/v1/tenants/{tenantId}/sync`
- This route does not exist
- The correct route is `/api/v1/sync/dispatch` with different parameter structure

**Backend Route (Exists and Working):**
```php
// File: routes/api.php:56
Route::post('/sync/dispatch', [SyncController::class, 'dispatch']);
```

**Backend Controller (Complete):**
- `SyncController@dispatch` implemented
- Expects `tenant_id` in request body (not URL parameter)
- Returns 202 Accepted with job tracking
- Queue integration via `ExampleSyncJob`
- Validation for tenant_id existence

**Expected Request Format:**
```json
{
    "tenant_id": "uuid-here",
    "data": {}
}
```

**Impact:**
- Users cannot trigger sync operations
- Core functionality completely broken
- 404 errors on sync trigger attempts

**Affected Files:**
1. `public/js/dashboard.js:181` (main dashboard)
2. `resources/js/components/sync-status.js:40` (reusable component)

## Framework Documentation Research

### Laravel 11 + Alpine.js Integration Patterns

**Architecture Pattern:**
- **Backend:** Laravel 11 API routes with Sanctum authentication
- **Frontend:** Blade templates + Alpine.js for interactivity
- **Communication:** Fetch API for async operations
- **Authentication:** CSRF tokens for web routes, Sanctum tokens for API routes

**API Call Pattern (Current Implementation):**
```javascript
const response = await fetch(`/api/v1/endpoint`, {
    method: 'GET/POST',
    headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    }
});
```

**Best Practices Identified:**
1. **CSRF Protection:** All fetch calls include X-CSRF-TOKEN header ✓
2. **Error Handling:** Try-catch blocks with user-friendly messages ✓
3. **Loading States:** Alpine.js data properties for loading indicators ✓
4. **Response Parsing:** Consistent JSON response handling ✓
5. **Route Naming:** Kebab-case for URL paths (`/api/v1/tenants/{id}/search`) ✓

### Laravel Route Conventions

**Current Route Structure (Verified):**
```php
// API routes are versioned with /api/v1/ prefix
Route::prefix('v1')->group(function () {
    // Tenant-scoped routes use /tenants/{tenantId}/
    Route::get('/tenants/{tenantId}/search', [ProductSearchController::class, 'search']);

    // Global operations use resource-style routing
    Route::post('/sync/dispatch', [SyncController::class, 'dispatch']);
});
```

**Route Naming Patterns:**
- **Tenant-scoped operations:** `/api/v1/tenants/{tenantId}/action` (search, reindex, etc.)
- **Global operations:** `/api/v1/resource/action` (sync/dispatch, exports/products)
- **Nested resources:** `/api/v1/tenants/{tenantId}/products` (not implemented, uses /search instead)

**Frontend-Backend Contract:**
- Frontend must match exact route paths defined in backend
- Parameters must match (URL params vs. body params)
- HTTP methods must align (GET vs. POST)

### Authentication Integration

**Current Implementation:**
- **Web routes:** Session-based authentication via Laravel Breeze
- **API routes:** Sanctum token-based authentication
- **Dashboard:** Hybrid approach - web routes for views, fetch API for data

**API Authentication Pattern:**
```php
Route::middleware(['auth:sanctum', 'token.expire'])->group(function () {
    // Protected API routes
});
```

**Frontend Authentication:**
- CSRF tokens included in fetch headers ✓
- Sanctum tokens stored and sent with API requests ✓
- Session auth for dashboard page access ✓

## Architecture Patterns

### Recommended Project Structure

```
public/js/
├── dashboard.js              # Main dashboard Alpine.js component
└── components/               # Reusable components
    ├── product-search.js     # Product search functionality
    └── sync-status.js        # Sync status polling

routes/
├── api.php                   # API routes (Sanctum auth)
└── web.php                   # Web routes (session auth)

app/Http/Controllers/Api/V1/
├── ProductSearchController.php # Search endpoint
└── SyncController.php         # Sync dispatch endpoint
```

### Pattern 1: Frontend-Backend API Communication

**What:** Standardized fetch API calls with error handling and loading states

**When to use:** All async operations in Alpine.js components

**Correct Implementation:**
```javascript
async performApiCall() {
    this.loading = true;
    this.error = null;

    try {
        const response = await fetch(`/api/v1/endpoint`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ key: 'value' })
        });

        if (!response.ok) {
            throw new Error('API request failed');
        }

        const data = await response.json();
        // Process data...

    } catch (error) {
        this.error = error.message;
        console.error('API error:', error);
    } finally {
        this.loading = false;
    }
}
```

### Pattern 2: Route Alias vs. Direct API Call

**Option A: Fix Frontend to Call Correct Route (RECOMMENDED)**
```javascript
// Change from:
fetch(`/api/v1/tenants/${tenantId}/sync`)

// To:
fetch('/api/v1/sync/dispatch', {
    method: 'POST',
    body: JSON.stringify({ tenant_id: tenantId })
});
```

**Pros:**
- Follows existing backend architecture
- No backend changes required
- Aligns with resource-style routing

**Cons:**
- Frontend must handle different parameter structure

**Option B: Add Route Alias for Convenience**
```php
// Add to routes/api.php:
Route::post('/tenants/{tenantId}/sync', function (Request $request, string $tenantId) {
    $request->merge(['tenant_id' => $tenantId]);
    return app(SyncController::class)->dispatch($request);
});
```

**Pros:**
- Frontend code remains simple
- Matches tenant-scoped route pattern

**Cons:**
- Adds backend complexity
- Maintains two routes for same operation

**Recommendation:** Option A - Fix frontend to call correct API route

### Anti-Patterns to Avoid

- **Hardcoded route strings:** Use route constants or configuration
- **Inconsistent error handling:** Standardize error response format
- **Missing loading states:** Always provide user feedback during API calls
- **CSRF token omission:** All POST/PUT/DELETE requests need CSRF tokens
- **Incorrect HTTP methods:** Use GET for queries, POST for actions

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| API client libraries | Custom fetch wrappers | Laravel's existing API structure | Backend routes already complete and tested |
| Route resolution | Frontend route parsing | Match backend routes exactly | Avoids routing bugs and maintains consistency |
| Authentication handling | Custom token management | Existing Sanctum + CSRF setup | Secure, tested, follows Laravel best practices |
| Error handling | Custom error display | Standardized error response format | Consistent user experience across dashboard |

**Key insight:** The backend infrastructure is complete and production-ready. Frontend只需调用正确的API端点，无需构建自定义解决方案。

## Common Pitfalls

### Pitfall 1: Route Mismatch Between Frontend and Backend

**What goes wrong:** Frontend calls non-existent routes, resulting in 404 errors

**Why it happens:**
- Frontend developed before backend routes finalized
- Route refactoring on backend without updating frontend
- Copy-paste errors in URL strings

**How to avoid:**
1. Always verify route exists in `routes/api.php` before implementing frontend
2. Use route names consistently (`route('api.sync.dispatch')` if using Blade)
3. Test frontend-backend integration early in development

**Warning signs:**
- 404 errors in browser console
- Network requests failing silently
- Features "should work but don't"

### Pitfall 2: Parameter Structure Mismatch

**What goes wrong:** API requests fail with validation errors (422)

**Why it happens:**
- Frontend sends URL parameters when backend expects body parameters
- Parameter names don't match (camelCase vs snake_case)
- Missing required fields in request body

**How to avoid:**
1. Check controller validation rules before implementing frontend
2. Match request format exactly to backend expectations
3. Test with API documentation or tools like Postman first

**Warning signs:**
- 422 Unprocessable Entity responses
- "Validation failed" error messages
- Backend receiving null/empty values

### Pitfall 3: Missing Authentication Headers

**What goes wrong:** API requests return 401 Unauthorized

**Why it happens:**
- CSRF tokens not included in fetch headers
- Sanctum tokens not attached to requests
- Session authentication not established

**How to avoid:**
1. Always include CSRF tokens for web routes
2. Attach Sanctum tokens for API routes
3. Verify authentication middleware in route definitions

**Warning signs:**
- 401 Unauthorized responses
- Requests working in Postman but failing in browser
- Intermittent authentication failures

## Code Examples

### Fix 1: Product Search Endpoint Correction

**File:** `public/js/dashboard.js:467`

**Before (Broken):**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/products?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
```

**After (Fixed):**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/search?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
```

**File:** `resources/js/components/product-search.js:33`

**Before (Broken):**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/products?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
```

**After (Fixed):**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/search?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
```

### Fix 2: Sync Trigger Endpoint Correction

**Option A: Call Correct API Route (RECOMMENDED)**

**File:** `public/js/dashboard.js:181`

**Before (Broken):**
```javascript
const response = await fetch(`/api/v1/tenants/${tenantId}/sync`, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    }
});
```

**After (Fixed):**
```javascript
const response = await fetch('/api/v1/sync/dispatch', {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    },
    body: JSON.stringify({
        tenant_id: tenantId,
        data: {}
    })
});
```

**File:** `resources/js/components/sync-status.js:40`

**Before (Broken):**
```javascript
const response = await fetch(`/api/v1/tenants/${this.tenantId}/sync`, {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    }
});
```

**After (Fixed):**
```javascript
const response = await fetch('/api/v1/sync/dispatch', {
    method: 'POST',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
    },
    body: JSON.stringify({
        tenant_id: this.tenantId,
        data: {}
    })
});
```

**Option B: Add Route Alias (Alternative)**

**File:** `routes/api.php` (add after line 63)

```php
// Alias for convenience - matches frontend tenant-scoped pattern
Route::post('/tenants/{tenantId}/sync', function (Request $request, string $tenantId) {
    $request->merge(['tenant_id' => $tenantId]);
    return app(SyncController::class)->dispatch($request);
});
```

## Implementation Blueprint

### Step 1: Fix Product Search (5 minutes)

1. **Update main dashboard file**
   - File: `public/js/dashboard.js:467`
   - Change: `/products` → `/search`
   - Test: Load dashboard, search for products

2. **Update reusable component**
   - File: `resources/js/components/product-search.js:33`
   - Change: `/products` → `/search`
   - Test: Verify component usage in dashboard

3. **Verify integration**
   - Backend route exists: `GET /api/v1/tenants/{tenantId}/search`
   - Controller works: `ProductSearchController@search`
   - Response format: `{data: [...], meta: {total, last_page}}`

### Step 2: Fix Sync Trigger (10 minutes)

**Option A: Frontend Fix (RECOMMENDED)**

1. **Update main dashboard file**
   - File: `public/js/dashboard.js:181`
   - Change endpoint: `/api/v1/tenants/${tenantId}/sync` → `/api/v1/sync/dispatch`
   - Add request body: `{tenant_id: tenantId, data: {}}`
   - Test: Click sync trigger button

2. **Update reusable component**
   - File: `resources/js/components/sync-status.js:40`
   - Change endpoint: `/api/v1/tenants/${this.tenantId}/sync` → `/api/v1/sync/dispatch`
   - Add request body: `{tenant_id: this.tenantId, data: {}}`
   - Test: Verify component usage in dashboard

3. **Verify integration**
   - Backend route exists: `POST /api/v1/sync/dispatch`
   - Controller works: `SyncController@dispatch`
   - Response format: `{data: {job_id, status, message}}`

**Option B: Backend Alias (ALTERNATIVE)**

1. **Add route alias**
   - File: `routes/api.php`
   - Add: `Route::post('/tenants/{tenantId}/sync', ...)`
   - Test: Existing frontend code works unchanged

2. **Consider implications**
   - Maintains two routes for same operation
   - Frontend code simpler but backend more complex
   - May confuse future developers

### Step 3: Testing Strategy

**Manual Testing:**
1. Load dashboard at `/dashboard`
2. Navigate to product search page
3. Enter search query, verify results appear
4. Navigate to client list
5. Click "Trigger Sync" button
6. Verify sync starts and status updates

**Automated Testing:**
- Update existing `FrontendIntegrationTest` to verify corrected endpoints
- Add integration tests for search endpoint
- Add integration tests for sync dispatch endpoint

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No frontend-backend integration | Complete API-first architecture | Phase 2 | API routes versioned, documented |
| Broken user flows | Working dashboard integration | Phase 14 (current) | Core features functional |
| Inconsistent error handling | Standardized error responses | Phase 13 | Better user experience |

**Current Best Practices (2025):**
- API versioning with `/api/v1/` prefix
- Resource-style routing conventions
- Sanctum authentication for APIs
- CSRF protection for web routes
- Fetch API with async/await
- Alpine.js for lightweight interactivity

**Deprecated/outdated:**
- jQuery AJAX (replaced by fetch API)
- Session-only authentication (replaced by Sanctum tokens)
- Unversioned API routes (security/maintenance risk)

## Dependency Graph

### Files Involved in Fixes

**Product Search Fix:**
1. `public/js/dashboard.js:467` - Main dashboard product search
2. `resources/js/components/product-search.js:33` - Reusable search component
3. `routes/api.php:78` - Backend search route (no change needed)
4. `app/Http/Controllers/Api/V1/ProductSearchController.php` - Controller (no change needed)

**Sync Trigger Fix:**
1. `public/js/dashboard.js:181` - Main dashboard sync trigger
2. `resources/js/components/sync-status.js:40` - Reusable sync component
3. `routes/api.php:56` - Backend sync route (no change needed)
4. `app/Http/Controllers/Api/V1/SyncController.php` - Controller (no change needed)

### Component Dependencies

**Dashboard Components:**
```
dashboard.js (main)
├── productSearch() function
│   └── calls: /api/v1/tenants/{id}/search (BROKEN → FIX)
├── triggerSync() function
│   └── calls: /api/v1/tenants/{id}/sync (BROKEN → FIX)
└── fetchSyncStatus() function
    └── calls: /api/v1/sync-logs (WORKING)
```

**Reusable Components:**
```
components/product-search.js
└── performSearch() method
    └── calls: /api/v1/tenants/{id}/products (BROKEN → FIX)

components/sync-status.js
├── triggerSync() method
│   └── calls: /api/v1/tenants/{id}/sync (BROKEN → FIX)
└── fetchSyncStatus() method
    └── calls: /api/v1/sync-logs (WORKING)
```

### Backend Dependencies (No Changes Required)

**Product Search Flow:**
```
ProductSearchController@search
├── Tenant authorization (whereHas users)
├── ProductSearchService
│   ├── Elasticsearch query
│   └── Pagination
└── JSON response {data, meta}
```

**Sync Dispatch Flow:**
```
SyncController@dispatch
├── Validation (tenant_id required)
├── JobStatus creation
├── ExampleSyncJob dispatch
└── 202 Accepted response
```

## Industry Standards

### Frontend-Backend Integration Best Practices

**1. API Versioning**
- **Standard:** URL-based versioning (`/api/v1/`)
- **Implementation:** Route prefix groups in Laravel
- **Benefit:** Backwards compatibility, API evolution

**2. Route Naming Conventions**
- **Standard:** Kebab-case for URLs (`/product-search`, not `/productSearch`)
- **Implementation:** Consistent route definitions
- **Benefit:** Predictable API structure

**3. HTTP Method Semantics**
- **Standard:** GET for queries, POST for actions
- **Implementation:** Route method definitions
- **Benefit:** RESTful API design

**4. Authentication Patterns**
- **Standard:** Token-based auth for APIs, session for web
- **Implementation:** Laravel Sanctum + Breeze
- **Benefit:** Security, separation of concerns

**5. Error Handling**
- **Standard:** Consistent error response format
- **Implementation:** BaseApiController with helper methods
- **Benefit:** Predictable error responses

### Testing Strategies

**1. Integration Testing**
- **What:** Test frontend-backend communication
- **How:** Feature tests with actual HTTP requests
- **Tools:** PHPUnit, Laravel's test APIs

**2. End-to-End Testing**
- **What:** Test complete user flows
- **How:** Browser automation (Laravel Dusk)
- **Tools:** Chrome DevTools, Dusk

**3. Manual Testing**
- **What:** Human verification of functionality
- **How:** Click through dashboard, verify features work
- **Tools:** Browser, network inspector

### Security Considerations

**1. CSRF Protection**
- **Requirement:** All state-changing requests need CSRF token
- **Implementation:** X-CSRF-TOKEN header in fetch calls
- **Verification:** Laravel's @csrf directive

**2. Authentication**
- **Requirement:** API routes require valid Sanctum token
- **Implementation:** auth:sanctum middleware
- **Verification:** 401 responses for unauthenticated requests

**3. Authorization**
- **Requirement:** Users can only access their tenant data
- **Implementation:** Tenant scoping via whereHas relationships
- **Verification:** Generic 404 for unauthorized access (prevents enumeration)

## Open Questions

1. **None identified** - Both fixes are straightforward and well-understood

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.5.55 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --filter=FrontendIntegrationTest` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SEARCH-01 | Product search calls correct `/search` endpoint | integration | `php artisan test --filter=test_product_search_uses_correct_endpoint` | ❌ Wave 0 |
| SEARCH-07 | Search results scoped to selected tenant | integration | `php artisan test --filter=test_product_search_tenant_scoping` | ❌ Wave 0 |
| SYNC-01 | Sync trigger calls correct `/sync/dispatch` endpoint | integration | `php artisan test --filter=test_sync_trigger_uses_correct_endpoint` | ❌ Wave 0 |
| UI-05 | Dashboard sync button triggers API call | integration | `php artisan test --filter=test_dashboard_sync_button_integration` | ❌ Wave 0 |
| UI-07 | Dashboard search calls correct API endpoint | integration | `php artisan test --filter=test_dashboard_search_integration` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=FrontendIntegrationTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ProductSearchIntegrationTest.php` — covers SEARCH-01, SEARCH-07
- [ ] `tests/Feature/SyncTriggerIntegrationTest.php` — covers SYNC-01, UI-05
- [ ] `tests/Feature/DashboardIntegrationTest.php` — covers UI-05, UI-07
- [ ] Update existing `tests/Feature/FrontendIntegrationTest.php` with corrected endpoints

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** - API routing, Sanctum authentication, fetch integration
- **Project Codebase Analysis** - Direct examination of broken implementations
- **Milestone Audit Document** - `.planning/v1.0-MILESTONE-AUDIT.md` (comprehensive gap analysis)

### Secondary (MEDIUM confidence)
- **Alpine.js Documentation** - Component patterns, async operations
- **Project History** - Phase 13 refactoring decisions, architectural patterns
- **Existing Test Coverage** - FrontendIntegrationTest.php showing expected patterns

### Tertiary (LOW confidence)
- **Web Search (Rate Limited)** - General best practices for Laravel + Alpine.js integration (search unavailable due to rate limits, relying on project-specific analysis)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11 + Alpine.js + Sanctum well-established in project
- Architecture: HIGH - Direct codebase analysis reveals exact issues and fixes
- Pitfalls: HIGH - Clear understanding of root causes and prevention strategies
- Implementation: HIGH - Straightforward string replacements with minimal risk

**Research date:** 2026-03-15
**Valid until:** 30 days (stable architecture, well-understood fixes)

**Key Findings:**
1. Both issues are simple frontend endpoint mismatches
2. Backend implementations complete and tested
3. Fixes are 1-line changes per file
4. No backend changes required
5. High confidence in quick resolution (15 minutes total)
