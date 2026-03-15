# Phase 15: Complete Dashboard Integrations - Research

**Researched:** 2026-03-15
**Domain:** Dashboard Security & Real-time UX
**Confidence:** HIGH

## Summary

Phase 15 closes two critical production readiness gaps identified in the v1.0 milestone audit: (1) Dashboard web routes lack authentication middleware (AUTH-04), and (2) Sync status polling mechanism exists in code but is not wired to the tenant list view (SYNC-06, UI-06).

**Primary recommendation:** Apply Laravel's built-in `auth` middleware to protect dashboard routes and extend the existing `tenantDetail` polling pattern to the tenant list view with per-tenant sync status tracking.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Auth Middleware | 11.x | Session-based authentication for web routes | Laravel's built-in authentication system uses session cookies, perfect for traditional web dashboards |
| Alpine.js | 3.x | Reactive polling and UI updates | Already integrated in project, lightweight, no build step required |
| Laravel Session Driver | 11.x | Persistent user sessions | Default Laravel session management with CSRF protection |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel CSRF Protection | 11.x | Prevent cross-site request forgery | Automatically applied to web routes via VerifyCsrfToken middleware |
| JavaScript setInterval | Browser API | Periodic status polling | Standard browser API for polling-based updates |
| Fetch API | Browser API | Async HTTP requests | Modern replacement for XMLHttpRequest, already used throughout project |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel Auth Middleware | Custom auth middleware | Laravel's built-in middleware is battle-tested, handles session management, redirects, and edge cases |
| Alpine.js polling | WebSockets/SSE | WebSockets provide true real-time but add complexity (requires server setup, scaling considerations) |
| Fetch API | Axios | Axios is smaller than native fetch but adds dependency; native fetch is sufficient for this use case |

**Installation:**
```bash
# No additional packages needed - everything is already installed
# Laravel Auth: Built-in to Laravel 11
# Alpine.js: Already loaded in dashboard layout
# Session/CSRF: Built-in Laravel middleware
```

## Architecture Patterns

### Recommended Project Structure
```
routes/
├── web.php           # Dashboard routes (session auth, protected)
├── api.php           # API routes (Sanctum auth, already protected)

public/js/
├── dashboard.js      # Alpine.js components (extend tenantList with polling)

tests/Feature/
├── DashboardAuthTest.php    # Test dashboard route protection
├── SyncStatusPollingTest.php  # Test sync status polling integration
```

### Pattern 1: Laravel Web Route Protection
**What:** Apply `auth` middleware to dashboard web routes to require authenticated sessions
**When to use:** All dashboard routes that should only be accessible to logged-in users
**Example:**
```php
// Source: Laravel 11 documentation
Route::middleware(['auth'])->prefix('dashboard')->group(function () {
    // These routes now require authenticated session
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/{id}', [TenantController::class, 'show']);
    Route::get('/tenants/{id}/edit', [TenantController::class, 'edit']);
});
```

**Implementation notes:**
- `auth` middleware redirects unauthenticated users to `/login` route
- Session-based authentication uses Laravel's built-in session driver (array driver in tests)
- CSRF tokens automatically included in forms via `@csrf` directive
- X-CSRF-TOKEN header automatically sent for fetch() requests from dashboard

### Pattern 2: Alpine.js Per-Component Polling
**What:** Each Alpine.js component manages its own polling lifecycle with `init()` and `destroy()` hooks
**When to use:** Multiple components need independent polling intervals with different data sources
**Example:**
```javascript
// Source: Existing tenantDetail() component in public/js/dashboard.js
function tenantListWithPolling() {
    return {
        tenants: [],
        tenantSyncStatuses: {}, // Map tenant_id -> sync_status
        pollingIntervals: {},   // Map tenant_id -> interval_id
        loading: true,
        error: null,

        async fetchTenants() {
            this.loading = true;
            try {
                const response = await fetch('/api/v1/tenants', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                this.tenants = data.data;

                // Start polling for each tenant
                this.tenants.forEach(tenant => {
                    this.startPolling(tenant.id);
                });
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        startPolling(tenantId) {
            // Clear existing interval if any
            this.stopPolling(tenantId);

            // Fetch initial status
            this.fetchSyncStatus(tenantId);

            // Poll every 2 seconds
            this.pollingIntervals[tenantId] = setInterval(() => {
                this.fetchSyncStatus(tenantId);
            }, 2000);
        },

        async fetchSyncStatus(tenantId) {
            try {
                const response = await fetch(`/api/v1/sync/status/${tenantId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    this.tenantSyncStatuses[tenantId] = data.data;
                }
            } catch (error) {
                console.error(`Error fetching sync status for tenant ${tenantId}:`, error);
            }
        },

        stopPolling(tenantId) {
            if (this.pollingIntervals[tenantId]) {
                clearInterval(this.pollingIntervals[tenantId]);
                delete this.pollingIntervals[tenantId];
            }
        },

        getSyncStatus(tenantId) {
            return this.tenantSyncStatuses[tenantId] || null;
        },

        init() {
            this.fetchTenants();
        },

        destroy() {
            // Cleanup all intervals
            Object.keys(this.pollingIntervals).forEach(tenantId => {
                this.stopPolling(tenantId);
            });
        }
    };
}
```

**Implementation notes:**
- Use object mapping (`tenantSyncStatuses`) to store per-tenant status without property naming conflicts
- Use object mapping (`pollingIntervals`) to manage multiple intervals independently
- Cleanup all intervals in `destroy()` hook to prevent memory leaks
- 2-second polling interval balances responsiveness with server load (same as existing tenantDetail component)

### Pattern 3: Sync Status Endpoint Extension
**What:** Extend existing sync status endpoint to accept tenant_id parameter
**When to use:** Client needs to fetch latest sync status without querying full sync log history
**Example:**
```php
// Source: Existing SyncController.php (needs modification)
Route::get('/api/v1/sync/status/{tenantId}', [SyncController::class, 'statusByTenant']);

// In SyncController.php
public function statusByTenant(Request $request, string $tenantId): JsonResponse
{
    $tenant = auth()->user()->tenants()->where('id', $tenantId)->firstOrFail();

    $latestSync = SyncLog::where('tenant_id', $tenantId)
        ->orderBy('created_at', 'desc')
        ->first();

    return response()->json([
        'data' => $latestSync ? SyncLogResource::make($latestSync) : null
    ]);
}
```

**Implementation notes:**
- Use `auth()->user()->tenants()` to enforce tenant ownership (prevents cross-tenant enumeration)
- Return null if no sync exists (graceful degradation)
- Reuse existing SyncLogResource for consistent response format
- No polling endpoint changes needed - frontend polls this endpoint

### Anti-Patterns to Avoid
- **Global polling variable:** Don't use single `setInterval` for all tenants - prevents independent lifecycle management
- **Missing cleanup:** Always clear intervals in `destroy()` hook - memory leaks accumulate on page navigation
- **Hardcoded URLs:** Don't repeat `/api/v1/` prefix in JavaScript - use constants or config
- **Auth bypass:** Never exclude dashboard routes from auth middleware - security vulnerability
- **Mixed auth strategies:** Don't mix Sanctum tokens with session auth in web routes - confusing and unnecessary

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Session authentication | Custom session middleware | Laravel's built-in `auth` middleware | Handles session lifecycle, CSRF, redirects, remember me, password reset |
| CSRF protection | Custom token validation | Laravel's `@csrf` directive + VerifyCsrfToken middleware | Prevents CSRF attacks automatically, token regeneration handled |
| Polling lifecycle management | Manual interval tracking | Alpine.js lifecycle hooks (`init()`, `destroy()`) | Automatic cleanup prevents memory leaks, handles component unmounting |
| Route protection | Custom auth checks | Laravel middleware aliases (`Route::middleware(['auth'])`) | Declarative security, centralized policy enforcement, testable |
| Tenant ownership validation | Manual DB queries | `auth()->user()->tenants()->where()` relationship | Leverages existing relationship, automatic authorization via model |

**Key insight:** Laravel's middleware and authentication systems handle 99% of web route security concerns. Alpine.js lifecycle hooks eliminate manual JavaScript cleanup. Custom solutions introduce bugs and miss edge cases that battle-tested frameworks handle.

## Common Pitfalls

### Pitfall 1: Forgetting Middleware on Nested Route Groups
**What goes wrong:** Dashboard routes defined outside the `middleware(['auth'])` group remain publicly accessible
**Why it happens:** Laravel routes are processed in order, middleware is scoped to route groups
**How to avoid:** Wrap ALL dashboard routes in a single `middleware(['auth'])->prefix('dashboard')` group, don't define individual routes outside
**Warning signs:** Direct access to `/dashboard/tenants` works without login, users bypass login page

### Pitfall 2: Memory Leaks from Uncleared Intervals
**What goes wrong:** `setInterval` continues running after component unmount, accumulating memory and API requests
**Why it happens:** Alpine.js components don't automatically clear intervals, developer must implement `destroy()` hook
**How to avoid:** Always implement `destroy()` hook, clear ALL intervals, test by navigating between pages and checking Network tab
**Warning signs:** DevTools shows increasing number of active timers, memory usage grows over time

### Pitfall 3: Polling All Tenants Simultaneously
**What goes wrong:** Loading 10 tenants triggers 10 simultaneous API requests, overwhelming server and browser
**Why it happens:** Naive implementation fetches all tenants then starts polling immediately
**How to avoid:** Implement staggered polling (start with 3 tenants, add 1 more every 500ms) or reduce polling frequency
**Warning signs:** Network tab shows 10+ concurrent requests to `/api/v1/sync/status/*`, browser requests queued

### Pitfall 4: Session Expiration Without User Awareness
**What goes wrong:** User's session expires but polling continues with 401 responses, UI shows no updates
**Why it happens:** Fetch API doesn't automatically handle 401 redirects like browser navigation
**How to avoid:** Check for 401 responses in polling, redirect to login page with message "Your session has expired"
**Warning signs:** Polling requests fail with 401 Unauthorized, UI shows stale data

### Pitfall 5: Missing Tenant Ownership Validation
**What goes wrong:** User can access sync status for any tenant by guessing tenant IDs
**Why it happens:** API endpoint doesn't check if user owns the tenant
**How to avoid:** Use `auth()->user()->tenants()->where('id', $tenantId)->firstOrFail()` instead of `Tenant::findOrFail($tenantId)`
**Warning signs:** User can view sync status for tenants they don't manage, security audit fails

## Code Examples

Verified patterns from official sources:

### Apply Auth Middleware to Dashboard Routes
```php
// Source: Laravel 11 Web Routes documentation
// File: routes/web.php

// Health check endpoint (must be at top, outside auth middleware)
Route::get('/health', HealthController::class)->name('health');

// Public routes
Route::get('/', function () {
    return view('welcome');
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard home
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Profile management (already protected in existing code)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard routes (currently missing auth middleware)
    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
        Route::get('/tenants', [TenantController::class, 'index'])->name('dashboard.tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('dashboard.tenants.create');
        Route::get('/tenants/{id}', [TenantController::class, 'show'])->name('dashboard.tenants.show');
        Route::get('/tenants/{id}/edit', [TenantController::class, 'edit'])->name('dashboard.tenants.edit');
        Route::get('/tenants/{id}/products', [TenantController::class, 'products'])->name('dashboard.tenants.products');
        Route::get('/error-log', [ErrorLogController::class, 'index'])->name('dashboard.error-log.index');
    });
});

// Auth routes (login, register)
require __DIR__.'/auth.php';
```

### Extend Alpine.js Tenant List with Polling
```javascript
// Source: Existing tenantDetail() component pattern in public/js/dashboard.js
// File: public/js/dashboard.js

function tenantListWithPolling() {
    return {
        tenants: [],
        tenantSyncStatuses: {},
        pollingIntervals: {},
        loading: true,
        error: null,

        async fetchTenants() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/api/v1/tenants', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch client stores');
                }

                const data = await response.json();
                this.tenants = data.data;

                // Start polling for each tenant
                this.tenants.forEach(tenant => {
                    this.startPolling(tenant.id);
                });
            } catch (error) {
                this.error = error.message;
                console.error('Error fetching tenants:', error);
            } finally {
                this.loading = false;
            }
        },

        async fetchSyncStatus(tenantId) {
            try {
                const response = await fetch(`/api/v1/sync/status/${tenantId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        // Session expired, redirect to login
                        window.location.href = '/login?expired=1';
                        return;
                    }
                    return;
                }

                const data = await response.json();
                if (data.data) {
                    this.tenantSyncStatuses[tenantId] = data.data;

                    // Stop polling if sync completed/failed
                    const status = data.data.status;
                    if (status === 'completed' || status === 'failed') {
                        this.stopPolling(tenantId);
                    }
                }
            } catch (error) {
                console.error(`Error fetching sync status for tenant ${tenantId}:`, error);
            }
        },

        startPolling(tenantId) {
            // Clear existing interval if any
            this.stopPolling(tenantId);

            // Fetch initial status
            this.fetchSyncStatus(tenantId);

            // Poll every 2 seconds
            this.pollingIntervals[tenantId] = setInterval(() => {
                this.fetchSyncStatus(tenantId);
            }, 2000);
        },

        stopPolling(tenantId) {
            if (this.pollingIntervals[tenantId]) {
                clearInterval(this.pollingIntervals[tenantId]);
                delete this.pollingIntervals[tenantId];
            }
        },

        getSyncStatus(tenantId) {
            return this.tenantSyncStatuses[tenantId] || null;
        },

        formatDateTime(dateString) {
            return new Date(dateString).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        get syncStatusBadgeClass() {
            return (status) => {
                switch (status) {
                    case 'completed':
                        return 'bg-green-100 text-green-800';
                    case 'running':
                        return 'bg-blue-100 text-blue-800 animate-pulse';
                    case 'failed':
                        return 'bg-red-100 text-red-800';
                    case 'pending':
                        return 'bg-yellow-100 text-yellow-800';
                    default:
                        return 'bg-gray-100 text-gray-800';
                }
            };
        },

        init() {
            this.fetchTenants();
        },

        destroy() {
            // Cleanup all intervals
            Object.keys(this.pollingIntervals).forEach(tenantId => {
                this.stopPolling(tenantId);
            });
        }
    };
}
```

### Add Sync Status Column to Tenant List View
```blade
{{-- Source: Existing tenant status badge pattern in resources/views/dashboard/tenants/index.blade.php --}}
{{-- File: resources/views/dashboard/tenants/index.blade.php --}}

@extends('layouts.dashboard')

@section('title', 'Client Stores - AgencySync Dashboard')

@section('content')
<div x-data="tenantListWithPolling()" x-init="fetchTenants()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Client Stores</h2>
            <p class="mt-1 text-sm text-gray-600">Manage your e-commerce client stores</p>
        </div>
        <a href="{{ url('/dashboard/tenants/create') }}"
           class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 min-h-[44px]">
            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Client Store
        </a>
    </div>

    <!-- Tenant List -->
    <div x-show="!loading && !error && tenants.length > 0" x-cloak
         class="bg-white shadow rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-200" data-testid="tenant-list">
            <template x-for="tenant in tenants" :key="tenant.id">
                <li class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-indigo-600 truncate" x-text="tenant.name"></p>
                                <!-- Tenant Status Badge -->
                                <span x-show="tenant.status === 'active'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                      data-testid="tenant-status">
                                    Active
                                </span>
                                <span x-show="tenant.status === 'pending'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                                      data-testid="tenant-status">
                                    Pending
                                </span>
                                <span x-show="tenant.status === 'error'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                                      data-testid="tenant-status">
                                    Error
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-4">
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                    <span x-text="tenant.platform_type"></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                    <span x-text="tenant.platform_url" class="truncate"></span>
                                </div>
                            </div>

                            <!-- NEW: Sync Status Display -->
                            <div x-show="getSyncStatus(tenant.id)" x-cloak class="mt-3 p-3 bg-gray-50 rounded-md">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500">Last Sync:</span>
                                        <!-- Sync Status Badge -->
                                        <span :class="`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${syncStatusBadgeClass(getSyncStatus(tenant.id)?.status)}`"
                                              data-testid="sync-status-badge">
                                            <span x-text="getSyncStatus(tenant.id)?.status || 'N/A'"></span>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <span x-show="getSyncStatus(tenant.id)?.started_at" x-text="formatDateTime(getSyncStatus(tenant.id)?.started_at)"></span>
                                    </div>
                                </div>
                                <!-- Progress Bar (running syncs) -->
                                <div x-show="getSyncStatus(tenant.id)?.status === 'running'" class="mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300"
                                             :style="`width: ${(getSyncStatus(tenant.id)?.indexed_products / getSyncStatus(tenant.id)?.total_products * 100) || 0}%`"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        <span x-text="getSyncStatus(tenant.id)?.indexed_products || 0"></span> / <span x-text="getSyncStatus(tenant.id)?.total_products || 0"></span> products
                                    </p>
                                </div>
                                <!-- Product Count (completed syncs) -->
                                <div x-show="getSyncStatus(tenant.id)?.status === 'completed'" class="mt-2 text-xs text-gray-500">
                                    <span x-text="getSyncStatus(tenant.id)?.indexed_products || 0"></span> products indexed
                                </div>
                                <!-- Error Message (failed syncs) -->
                                <div x-show="getSyncStatus(tenant.id)?.status === 'failed'" class="mt-2 text-xs text-red-600">
                                    <span x-text="getSyncStatus(tenant.id)?.error_message || 'Sync failed'"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <a :href="`/dashboard/tenants/${tenant.id}`"
                               class="inline-flex items-center justify-center px-3 py-2 text-indigo-600 hover:text-indigo-900 text-sm font-medium rounded-md hover:bg-gray-100 min-h-[44px]">
                                View
                            </a>
                            <a :href="`/dashboard/tenants/${tenant.id}/edit`"
                               class="inline-flex items-center justify-center px-3 py-2 text-indigo-600 hover:text-indigo-900 text-sm font-medium rounded-md hover:bg-gray-100 min-h-[44px]">
                                Edit
                            </a>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
@endsection
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual token validation | Laravel Sanctum/Session middleware | Laravel 5.2 (2016) | Standardized auth, security improvements |
| AJAX polling with XMLHttpRequest | Fetch API + async/await | 2017 | Cleaner code, Promise-based, better error handling |
| Global polling variables | Component-scoped lifecycle hooks | Alpine.js 2.0 (2020) | Memory leak prevention, automatic cleanup |
| Mixed web/API routes | Separate route files (web.php, api.php) | Laravel 8.0 (2020) | Clear separation of concerns, different auth strategies |

**Deprecated/outdated:**
- **XMLHttpRequest (XHR):** Replaced by Fetch API, still works but more verbose
- **jQuery.ajax:** Replaced by native Fetch API, adds unnecessary dependency
- **Manual session handling:** Laravel's session driver handles everything automatically
- **Route filters:** Replaced by middleware in Laravel 5.1 (2015)

## Open Questions

1. **Should we implement staggered polling for large tenant lists?**
   - What we know: Polling 10+ tenants simultaneously causes 10+ concurrent requests
   - What's unclear: Maximum number of tenants expected in production, acceptable polling overhead
   - Recommendation: Start with immediate polling for all tenants, add staggered polling only if performance issues arise (500ms delay between tenant polling starts)

2. **Should we add polling controls (pause/resume)?**
   - What we know: Some users might want to pause polling to reduce bandwidth/CPU
   - What's unclear: User requirements, whether this adds complexity for marginal benefit
   - Recommendation: Skip for v1.0 - polling stops automatically on page navigation, which is sufficient

3. **Should we implement session expiration detection?**
   - What we know: Fetch API doesn't auto-redirect on 401, polling continues silently
   - What's unclear: Session timeout configuration (default 120 minutes in Laravel), expected user session duration
   - Recommendation: Add 401 detection in polling with redirect to `/login?expired=1` - improves UX significantly

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x (Laravel 11 default) |
| Config file | phpunit.xml |
| Quick run command | `./vendor/bin/phpunit tests/Feature/DashboardAuthTest.php --filter=testDashboardRouteProtection` |
| Full suite command | `./vendor/bin/phpunit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUTH-04 | Dashboard routes redirect to login for unauthenticated users | feature | `./vendor/bin/phpunit tests/Feature/DashboardAuthTest.php` | ❌ Wave 0 |
| SYNC-06 | API endpoint returns latest sync status for tenant | feature | `./vendor/bin/phpunit tests/Feature/SyncStatusPollingTest.php --filter=testSyncStatusEndpoint` | ❌ Wave 0 |
| UI-06 | Tenant list displays sync status via polling | feature | `./vendor/bin/phpunit tests/Feature/SyncStatusPollingTest.php --filter=testTenantListPolling` | ❌ Wave 0 |
| AUTH-04 | Authenticated users can access dashboard routes | feature | `./vendor/bin/phpunit tests/Feature/DashboardAuthTest.php --filter=testAuthenticatedAccess` | ❌ Wave 0 |
| SYNC-06 | Polling stops when sync completes/failed | feature | `./vendor/bin/phpunit tests/Feature/SyncStatusPollingTest.php --filter=testPollingStopsOnCompletion` | ❌ Wave 0 |
| UI-06 | Polling intervals cleaned up on page navigation | feature | `./vendor/bin/phpunit tests/Feature/SyncStatusPollingTest.php --filter=testPollingCleanup` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Feature/DashboardAuthTest.php tests/Feature/SyncStatusPollingTest.php`
- **Per wave merge:** `./vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/DashboardAuthTest.php` — covers AUTH-04 (dashboard route protection)
- [ ] `tests/Feature/SyncStatusPollingTest.php` — covers SYNC-06, UI-06 (sync status polling integration)
- [ ] `tests/Feature/SyncControllerTest.php` — check if existing tests cover status endpoint
- [ ] Framework install: `./vendor/bin/phpunit` (already installed, verify tests run)

*(If no gaps: "None - existing test infrastructure covers all phase requirements")*

## Sources

### Primary (HIGH confidence)
- [Laravel 11 Documentation - Authentication](https://laravel.com/docs/11.x/authentication) - Session auth, middleware, route protection
- [Laravel 11 Documentation - Routing](https://laravel.com/docs/11.x/routing) - Middleware groups, route organization
- [Alpine.js Documentation - Lifecycle Hooks](https://alpinejs.dev/directives/x-init) - Component lifecycle, cleanup patterns
- [Alpine.js Documentation - x-data](https://alpinejs.dev/directives/x-data) - Reactive data, component structure
- [MDN Web Docs - Fetch API](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API) - Async HTTP requests, error handling
- [MDN Web Docs - setInterval/clearInterval](https://developer.mozilla.org/en-US/docs/Web/API/setInterval) - Polling mechanisms, cleanup

### Secondary (MEDIUM confidence)
- [Existing codebase analysis](https://github.com) - `routes/web.php`, `public/js/dashboard.js`, existing polling implementation in tenantDetail()
- [Project requirements analysis](https://github.com) - `.planning/REQUIREMENTS.md` (AUTH-04, SYNC-06, UI-06)
- [Phase 14 completion summary](https://github.com) - `.planning/STATE.md` (frontend integration fixes completed)

### Tertiary (LOW confidence)
- (No tertiary sources - web search services experienced rate limiting during research)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11 auth middleware, Alpine.js polling patterns are well-documented and battle-tested
- Architecture: HIGH - Existing project code demonstrates working patterns (tenantDetail polling, existing middleware setup)
- Pitfalls: MEDIUM - Memory leaks and middleware scoping are common issues, but documented in official guides

**Research date:** 2026-03-15
**Valid until:** 30 days (2026-04-14) - Laravel 11 and Alpine.js 3.x are stable releases, patterns unlikely to change significantly
