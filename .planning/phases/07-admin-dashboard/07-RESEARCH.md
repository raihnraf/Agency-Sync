# Phase 7: Admin Dashboard - Research

**Researched:** 2026-03-14
**Domain:** Laravel 11 Blade + Alpine.js + TailwindCSS Admin Dashboard
**Confidence:** HIGH

## Summary

Phase 7 implements a responsive admin dashboard for AgencySync using Laravel 11 Blade templates with Alpine.js for interactivity and TailwindCSS for styling. The dashboard consumes existing RESTful APIs (Phase 2-6) to provide agency admins with a web interface for managing client stores, triggering sync operations, searching products, and viewing error logs. This is a standard Laravel stack (Blade + Alpine.js + TailwindCSS) that leverages Vite for asset compilation and provides a lightweight, SPA-like experience without the complexity of a separate frontend framework.

**Primary recommendation:** Use Laravel 11 Blade templates with Alpine.js components and TailwindCSS utility classes, consuming existing API endpoints via fetch() with Laravel Sanctum authentication. Build responsive mobile-first layouts with Tailwind's breakpoint system (sm, md, lg, xl), implement Alpine.js stores for shared state management, and use Blade components for reusable UI patterns.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| UI-01 | Agency admin can view client store list with status indicators | Existing GET /api/v1/tenants endpoint returns paginated tenant list with status field. Alpine.js x-for directive can iterate over tenants fetched via API. TailwindCSS badge utilities for status indicators (green for active, yellow for pending, red for error). |
| UI-02 | Agency admin can create new client store via form (name, platform, API credentials) | Existing POST /api/v1/tenants endpoint accepts CreateTenantRequest. Alpine.js x-data for form state, x-model for input binding, @submit.prevent for form submission. TailwindCSS form plugin for styled inputs. |
| UI-03 | Agency admin can edit client store details | Existing PUT /api/v1/tenants/{id} endpoint with UpdateTenantRequest. Pre-populate form with tenant data, Alpine.js x-data for form state, PATCH/PUT request via fetch(). |
| UI-04 | Agency admin can delete client store with confirmation | Existing DELETE /api/v1/tenants/{id} endpoint. Alpine.js x-show for modal confirmation dialog, @click away to close on escape, fetch() for deletion. |
| UI-05 | Agency admin can trigger sync operation for each client store | Existing POST /api/v1/sync/dispatch endpoint accepts tenant_id. Alpine.js @click handler to dispatch sync, x-show loading state during async operation. Polling GET /api/v1/sync/status/{syncLogId} for status updates. |
| UI-06 | Agency admin can view last sync status for each client store (time, status, product count) | Tenant model has last_sync_at, sync_status fields. SyncLogResource provides duration, progress_percentage, total_products, processed_products. Display via Blade components with Alpine.js for live updates. |
| UI-07 | Agency admin can search products within a client's catalog | Existing GET /api/v1/tenants/{tenantId}/search endpoint with query parameter. Alpine.js x-model.debounce.300ms for search input, x-for for results rendering. Pagination via x-data.currentPage. |
| UI-08 | Agency admin can view error log with filtering by client store and date | Existing GET /api/v1/sync/history endpoint with status filter, pagination. SyncLog.error_message field contains errors. Alpine.js x-data for filters (tenant_id, date range, status), fetch() on filter change. |
| UI-09 | Dashboard uses Blade templates with Alpine.js for interactivity | Standard Laravel Blade stack with Alpine.js CDN or Vite bundle. x-data for component state, x-init for initialization, @click/@submit for event handlers. Blade directives (@foreach, @if) for server-side rendering. |
| UI-10 | Dashboard uses TailwindCSS for styling | TailwindCSS 3.4 already in package.json with Vite. Utility classes for layout (flex, grid), spacing (p-4, m-2), typography (text-lg, font-bold), colors (bg-blue-500, text-gray-900). Responsive prefixes (md:, lg:). |
| UI-11 | Dashboard is responsive for mobile and tablet viewing | TailwindCSS mobile-first responsive design. Hidden sidebar on mobile (hidden md:block), hamburger menu with Alpine.js slide-over. Stacked layouts on mobile (grid-cols-1 md:grid-cols-2). Touch-friendly targets (min-height 44px). |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel 11 | 11.x | Backend framework with Blade templating | Modern PHP, native Blade support, robust authentication (Sanctum), built-in CSRF protection |
| Alpine.js | 3.14.x | Lightweight JavaScript framework for interactivity | Perfect companion to Blade, small footprint (~15KB), reactive data binding without build complexity |
| TailwindCSS | 3.4.x | Utility-first CSS framework | Already in package.json, rapid UI development, responsive utilities, consistent design system |
| Vite | 6.0.x | Frontend build tool | Laravel 11 default, fast HMR, optimized production builds, handles Alpine.js + TailwindCSS compilation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @tailwindcss/forms | 3.0.x | Form plugin for better default styling | Use for all form inputs (text, select, checkbox, radio) to avoid manually styling every form element |
| axios | 1.7.x | HTTP client (already in package.json) | Alternative to fetch() for cleaner API calls, automatic JSON handling, request/response interceptors for auth tokens |
| Laravel Sanctum | 11.x | API authentication | Store token in localStorage/meta tag, include in Authorization header for dashboard API requests |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Alpine.js | Vue.js 3 / React | Vue/React provide richer ecosystem but add significant complexity for simple dashboards. Alpine.js is perfect for Blade-enhanced interactivity. |
| TailwindCSS | Bootstrap / Bulma | Bootstrap is opinionated with heavy components, harder to customize. TailwindCSS is utility-first, fully customizable, already in project. |
| Blade templates | Inertia.js + Vue/React | Inertia.js provides SPA feel but requires frontend framework investment. Blade + Alpine.js achieves similar UX with simpler architecture. |
| Vite | Laravel Mix | Vite is Laravel 11 default, faster HMR, modern ESM-based. Mix is legacy, slower, being phased out. |

**Installation:**
```bash
# Already installed in project
npm install

# Additional form plugin (recommended)
npm install -D @tailwindcss/forms

# Alpine.js via CDN (simplest for dashboard)
# Add to layout Blade template:
# <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

# Or via npm for Vite bundling:
npm install alpinejs
```

## Architecture Patterns

### Recommended Project Structure
```
resources/
├── views/
│   ├── dashboard/
│   │   ├── layout.blade.php          # Main dashboard layout
│   │   ├── home.blade.php             # Dashboard home page
│   │   ├── tenants/
│   │   │   ├── index.blade.php        # Tenant list
│   │   │   ├── create.blade.php       # Create tenant form
│   │   │   ├── edit.blade.php         # Edit tenant form
│   │   │   └── partials/
│   │   │       ├── tenant-card.blade.php
│   │   │       └── tenant-status.blade.php
│   │   ├── products/
│   │   │   └── search.blade.php       # Product search interface
│   │   ├── sync/
│   │   │   ├── history.blade.php      # Sync history log
│   │   │   └── partials/
│   │   │       └── sync-log-item.blade.php
│   │   └── components/
│   │       ├── modal.blade.php        # Reusable modal
│   │       ├── notification.blade.php # Toast notifications
│   │       └── confirm-delete.blade.php
├── js/
│   ├── app.js                         # Vite entry point
│   └── dashboard.js                   # Dashboard-specific Alpine.js components
└── css/
    └── app.css                        # TailwindCSS imports
```

### Pattern 1: Alpine.js Component with API Integration
**What:** Self-contained Alpine.js component that fetches data from existing API endpoints
**When to use:** Any interactive dashboard element (tenant list, search, modals)
**Example:**
```blade
<!-- resources/views/dashboard/tenants/index.blade.php -->
<div x-data="tenantList()" x-init="fetchTenants()">
    <!-- Loading state -->
    <div x-show="loading" class="flex justify-center p-8">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error state -->
    <div x-show="error" x-cloak class="bg-red-50 text-red-800 p-4 rounded">
        <span x-text="error"></span>
        <button @click="fetchTenants()" class="ml-2 underline">Retry</button>
    </div>

    <!-- Tenant list -->
    <div x-show="!loading && !error" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="tenant in tenants" :key="tenant.id">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold" x-text="tenant.name"></h3>
                <span class="inline-block px-2 py-1 text-sm rounded"
                      :class="{
                          'bg-green-100 text-green-800': tenant.status === 'active',
                          'bg-yellow-100 text-yellow-800': tenant.status === 'pending_setup',
                          'bg-red-100 text-red-800': tenant.status === 'sync_error'
                      }" x-text="tenant.status"></span>
                <p class="text-sm text-gray-600" x-text="tenant.platform_type"></p>
                <p class="text-xs text-gray-500" x-text="tenant.last_sync_at"></p>
            </div>
        </template>
    </div>
</div>

<script>
function tenantList() {
    return {
        tenants: [],
        loading: true,
        error: null,

        async fetchTenants() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/api/v1/tenants', {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch tenants');

                const data = await response.json();
                this.tenants = data.data;
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
```

**Source:** Alpine.js documentation (https://alpinejs.dev/directives/data), Laravel Sanctum authentication

### Pattern 2: Blade Component with Alpine.js Enhancement
**What:** Reusable Blade component enhanced with Alpine.js for interactivity
**When to use:** Reusable UI elements (modals, confirmations, notifications)
**Example:**
```blade
<!-- resources/views/components/dashboard/modal.blade.php -->
@props(['id', 'title'])

<div
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    class="relative z-50"
>
    <!-- Trigger button (passed in via slot) -->
    <button @click="open = true">
        {{ $slot }}
    </button>

    <!-- Modal backdrop -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900 bg-opacity-50"
        x-cloak
    ></div>

    <!-- Modal content -->
    <div
        x-show="open"
        class="fixed inset-0 z-10 overflow-y-auto"
        x-cloak
    >
        <div class="flex min-h-full items-center justify-center p-4">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-lg shadow-xl max-w-md w-full"
            >
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">{{ $title }}</h3>

                    {{ $slot ?? '' }}

                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="open = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded">
                            Cancel
                        </button>
                        <button @click="confirm" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Source:** TailwindCSS modal pattern (https://tailwindui.com/templates/application-ui-application-shell), Alpine.js transitions

### Pattern 3: Search Interface with Debouncing
**What:** Real-time search with debounced input and pagination
**When to use:** Product search, tenant filtering, log searching
**Example:**
```blade
<div x-data="productSearch('{{ $tenantId }}')" x-init="search()">
    <div class="mb-4">
        <input
            type="text"
            x-model.debounce.300ms="query"
            @input="search()"
            placeholder="Search products..."
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
        >
    </div>

    <div x-show="loading" class="text-center py-8">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full"></div>
    </div>

    <div x-show="!loading && products.length === 0" class="text-center py-8 text-gray-500">
        No products found
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="product in products" :key="product.id">
            <div class="bg-white rounded-lg shadow p-4">
                <h4 class="font-semibold" x-text="product.name"></h4>
                <p class="text-sm text-gray-600" x-text="product.sku"></p>
                <p class="text-lg font-bold text-blue-600" x-text="product.price"></p>
            </div>
        </template>
    </div>

    <!-- Pagination -->
    <div x-show="!loading && products.length > 0" class="mt-6 flex justify-center gap-2">
        <button
            @click="prevPage()"
            :disabled="currentPage === 1"
            class="px-4 py-2 bg-white border rounded hover:bg-gray-50 disabled:opacity-50"
        >
            Previous
        </button>
        <span class="px-4 py-2" x-text="`Page ${currentPage}`"></span>
        <button
            @click="nextPage()"
            :disabled="currentPage >= lastPage"
            class="px-4 py-2 bg-white border rounded hover:bg-gray-50 disabled:opacity-50"
        >
            Next
        </button>
    </div>
</div>

<script>
function productSearch(tenantId) {
    return {
        query: '',
        products: [],
        loading: false,
        currentPage: 1,
        lastPage: 1,

        async search() {
            this.loading = true;

            try {
                const params = new URLSearchParams({
                    query: this.query,
                    page: this.currentPage,
                    per_page: 12
                });

                const response = await fetch(`/api/v1/tenants/${tenantId}/search?${params}`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                this.products = data.data.products || [];
                this.lastPage = data.data.last_page || 1;
            } catch (err) {
                console.error('Search failed:', err);
            } finally {
                this.loading = false;
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.search();
            }
        },

        nextPage() {
            if (this.currentPage < this.lastPage) {
                this.currentPage++;
                this.search();
            }
        }
    }
}
</script>
```

**Source:** Alpine.js debounce modifier (https://alpinejs.dev/directives/debounce), existing search API

### Anti-Patterns to Avoid
- **Mixing server-side and client-side rendering:** Don't use Blade @foreach for data that will be updated via AJAX. Use Alpine.js x-for for dynamic lists to avoid stale data issues.
- **Giant Alpine.js components:** Don't put all dashboard logic in one x-data object. Break into smaller components (tenantList, searchForm, syncStatus) for maintainability.
- **Ignoring loading states:** Always show loading indicators during async operations. Users need feedback that their action is being processed.
- **Hardcoding API URLs:** Don't duplicate API URLs across components. Use Laravel's route() helper in Blade: `{{ route('api.v1.tenants.index') }}`
- **Forgetting CSRF tokens:** All POST/PUT/DELETE requests need X-CSRF-TOKEN header. Laravel Sanctum automatically handles this if token is stored properly.
- **Overusing Alpine.js:** Don't use Alpine.js for static content. Use Blade directives (@if, @foreach) for server-side rendered content that never changes.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal dialogs | Custom modal with manual z-index, backdrop, escape handling | Alpine.js x-show with x-transition, or existing Blade component pattern | Edge cases: backdrop click to close, escape key, focus trapping, accessibility ARIA attributes |
| Form validation | Custom JavaScript validation logic | Laravel Form Request validation + Alpine.js x-model for error display | Server-side validation is secure. Use Blade @error directives for error messages. |
| Toast notifications | Custom notification system with setTimeout | Alpine.js x-data with x-transition.auto.duration.500ms or Laravel Flash messages | Auto-dismiss after N seconds, stack multiple notifications, animation timing |
| Date/time formatting | Manual date string manipulation | Laravel Carbon dates in Blade, or Intl.DateTimeFormat in JS | Timezone handling, localization, relative time ("2 hours ago"), consistent formatting |
| API authentication | Manual token refresh, storage management | Laravel Sanctum with token stored in localStorage/meta tag | Token expiration handling, automatic token attachment, CSRF protection |
| Responsive navigation | Custom hamburger menu with manual state | Alpine.js x-data="{ open: false }" with @click away to close | Click-outside-to-close, escape key handling, mobile touch gestures |
| Table sorting/pagination | Custom sort logic, pagination math | Server-side sorting/pagination via API with query parameters | Efficient for large datasets, consistent sort order, URL state for bookmarking |

**Key insight:** Custom UI components in JavaScript/Blade often miss accessibility features (keyboard navigation, ARIA labels, focus management) and edge cases (rapid clicking, network errors, concurrent requests). Alpine.js + TailwindCSS patterns are battle-tested and cover these cases.

## Common Pitfalls

### Pitfall 1: Stale Data After Updates
**What goes wrong:** User updates tenant name or status, but list view shows old data until page refresh
**Why it happens:** Blade renders initial HTML on server-side. Alpine.js fetches data on x-init, but doesn't auto-refresh after mutations
**How to avoid:**
1. After successful API mutation (create/update/delete), refetch the list
2. Use Alpine.js events or global store to trigger refresh across components
3. Optimistic updates: update local data immediately, then rollback if API fails
4. Example:
```javascript
async deleteTenant(tenantId) {
    if (!confirm('Are you sure?')) return;

    try {
        const response = await fetch(`/api/v1/tenants/${tenantId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) throw new Error('Delete failed');

        // Remove from local list (optimistic update)
        this.tenants = this.tenants.filter(t => t.id !== tenantId);
    } catch (err) {
        alert('Failed to delete tenant');
        // Could refetch here to rollback
    }
}
```
**Warning signs:** User has to manually refresh page to see changes, inconsistent data across components, "it works but..."

### Pitfall 2: Race Conditions in Search
**What goes wrong:** Rapid typing in search box triggers multiple API calls, results arrive out of order, wrong results displayed
**Why it happens:** Each keystroke triggers fetch(), slow responses arrive after fast responses, overwriting correct data
**How to avoid:**
1. Use Alpine.js .debounce modifier (300-500ms)
2. Cancel previous fetch request before starting new one (AbortController)
3. Example:
```javascript
x-data="search()" x-model.debounce.300ms="query" @input="search()"

function search() {
    return {
        query: '',
        abortController: null,

        async performSearch() {
            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            try {
                const response = await fetch(`/api/search?q=${this.query}`, {
                    signal: this.abortController.signal
                });
                // Handle response...
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Search failed:', err);
                }
            }
        }
    }
}
```
**Warning signs:** Search results flicker, wrong results shown, console errors about aborted requests

### Pitfall 3: Mobile Navigation Breakage
**What goes wrong:** Hamburger menu works on desktop but not mobile, sidebar overlaps content, touch targets too small
**Why it happens:** TailwindCSS responsive classes not tested on actual devices, hover states don't work on touch, z-index issues
**How to avoid:**
1. Test on actual mobile devices (not just browser devtools)
2. Use @click away for mobile menu close (Alpine.js plugin or custom)
3. Ensure touch targets are minimum 44x44px (WCAG guideline)
4. Use hidden md:block for sidebar, block md:hidden for mobile menu toggle
5. Example:
```blade
<!-- Mobile menu button -->
<button @click="mobileMenuOpen = true" class="md:hidden p-2">
    <svg class="w-6 h-6" fill="none" stroke="currentColor"><!-- hamburger icon --></svg>
</button>

<!-- Mobile sidebar -->
<div x-show="mobileMenuOpen" class="fixed inset-0 z-50 md:hidden">
    <div @click="mobileMenuOpen = false" class="fixed inset-0 bg-black bg-opacity-50"></div>
    <div class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg">
        <!-- Nav content -->
    </div>
</div>
```
**Warning signs:** Can't close menu on mobile, content is unclickable, buttons too small to tap

### Pitfall 4: Auth Token Expiry
**What goes wrong:** Dashboard loads, user makes API call, gets 401 Unauthorized, token expired
**Why it happens:** Laravel Sanctum tokens expire after N hours of inactivity (configured as 4 hours in Phase 2), dashboard doesn't handle expiry
**How to avoid:**
1. Check token expiration on page load (if stored with timestamp)
2. Intercept 401 responses, redirect to login
3. Use axios interceptors for automatic token refresh
4. Example:
```javascript
// In dashboard.js or app.js
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            // Token expired or invalid
            localStorage.removeItem('token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);
```
**Warning signs:** Random 401 errors, user logged out unexpectedly, API calls fail silently

### Pitfall 5: TailwindCSS Purge Removes Needed Styles
**What goes wrong:** Styles work in dev but break in production, missing classes, broken layout
**Why it happens:** TailwindCSS purges unused CSS classes in production build. Dynamic class names (concatenated strings) aren't detected.
**How to avoid:**
1. Use complete class names in templates (not string concatenation)
2. Add dynamic classes to tailwind.config.js safelist
3. Test production build locally: `npm run build && php artisan serve --port=8001`
4. Example:
```javascript
// tailwind.config.js
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    safelist: [
        // Dynamic status colors
        'bg-green-100', 'text-green-800',
        'bg-yellow-100', 'text-yellow-800',
        'bg-red-100', 'text-red-800',
    ],
}
```
**Warning signs:** Dev works, prod broken, missing styles after deployment

### Pitfall 6: Alpine.js x-cloak Flash
**What goes wrong:** Page loads, raw Alpine.js template syntax visible for split second, then disappears
**Why it happens:** Alpine.js loads after DOM renders, x-cloak style not defined, browser shows raw HTML
**How to avoid:**
1. Add x-cloak CSS to hide elements until Alpine loads
2. Add to app.css or tailwind.config.js:
```css
/* resources/css/app.css */
[x-cloak] { display: none !important; }
```
3. Apply x-cloak to all Alpine.js components
**Warning signs:** Flicker of raw {{ mustache }} syntax or x-text attributes on page load

## Code Examples

Verified patterns from official sources:

### Dashboard Layout with Responsive Sidebar
```blade
<!-- resources/views/dashboard/layout.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AgencySync Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div x-data="{ mobileMenuOpen: false }" class="min-h-screen">
        <!-- Mobile header -->
        <div class="md:hidden flex items-center justify-between p-4 bg-white shadow">
            <h1 class="text-xl font-bold">AgencySync</h1>
            <button @click="mobileMenuOpen = true" class="p-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor"><!-- hamburger icon --></svg>
            </button>
        </div>

        <div class="flex">
            <!-- Sidebar -->
            <aside
                :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transition-transform md:relative md:translate-x-0"
            >
                <div class="p-6">
                    <h1 class="text-2xl font-bold text-blue-600">AgencySync</h1>
                </div>

                <nav class="mt-6">
                    <a href="{{ route('dashboard.home') }}" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3"><!-- home icon --></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('dashboard.tenants.index') }}" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3"><!-- tenants icon --></svg>
                        Client Stores
                    </a>
                    <a href="{{ route('dashboard.products.search') }}" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3"><!-- search icon --></svg>
                        Product Search
                    </a>
                    <a href="{{ route('dashboard.sync.history') }}" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-3"><!-- sync icon --></svg>
                        Sync History
                    </a>
                </nav>

                <div class="absolute bottom-0 left-0 right-0 p-6">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-left text-red-600 hover:bg-red-50 rounded">
                            Logout
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Mobile backdrop -->
            <div
                x-show="mobileMenuOpen"
                @click="mobileMenuOpen = false"
                class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
                x-cloak
            ></div>

            <!-- Main content -->
            <main class="flex-1 p-6 md:p-8 overflow-auto">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
```

**Source:** TailwindCSS responsive sidebar pattern (https://tailwindui.com/templates/application-ui-application-shell), Alpine.js mobile menu pattern

### Tenant Form with Validation
```blade
<!-- resources/views/dashboard/tenants/create.blade.php -->
@extends('dashboard.layout')

@section('title', 'Create Client Store')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Create Client Store</h1>

    <div x-data="createTenant()" x-init="initPlatforms()">
        <form @submit.prevent="submit">
            <!-- Name field -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Store Name</label>
                <input
                    type="text"
                    x-model="form.name"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': errors.name }"
                    required
                >
                <p x-show="errors.name" x-text="errors.name" class="mt-1 text-sm text-red-600"></p>
            </div>

            <!-- Platform type -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                <select
                    x-model="form.platform_type"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    required
                >
                    <option value="">Select platform</option>
                    <template x-for="platform in platforms" :key="platform.value">
                        <option :value="platform.value" x-text="platform.label"></option>
                    </template>
                </select>
            </div>

            <!-- Platform URL -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Platform URL</label>
                <input
                    type="url"
                    x-model="form.platform_url"
                    placeholder="https://your-store.myshopify.com"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': errors.platform_url }"
                    required
                >
                <p x-show="errors.platform_url" x-text="errors.platform_url" class="mt-1 text-sm text-red-600"></p>
            </div>

            <!-- API Credentials -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">API Credentials</label>
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <div class="mb-2">
                        <label class="block text-sm text-gray-600 mb-1">API Key</label>
                        <input
                            type="text"
                            x-model="form.api_credentials.api_key"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">API Password</label>
                        <input
                            type="password"
                            x-model="form.api_credentials.api_password"
                            class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                            required
                        >
                    </div>
                </div>
                <p x-show="errors.api_credentials" x-text="errors.api_credentials" class="mt-1 text-sm text-red-600"></p>
            </div>

            <!-- Submit button -->
            <div class="flex justify-end gap-4">
                <a href="{{ route('dashboard.tenants.index') }}" class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button
                    type="submit"
                    :disabled="submitting"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    <span x-show="!submitting">Create Store</span>
                    <span x-show="submitting">Creating...</span>
                </button>
            </div>

            <!-- General error message -->
            <div x-show="generalError" x-text="generalError" class="mt-4 p-4 bg-red-50 text-red-800 rounded-lg"></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function createTenant() {
    return {
        form: {
            name: '',
            platform_type: '',
            platform_url: '',
            api_credentials: {
                api_key: '',
                api_password: ''
            }
        },
        errors: {},
        generalError: '',
        submitting: false,
        platforms: [
            { value: 'shopify', label: 'Shopify' },
            { value: 'shopware', label: 'Shopware' }
        ],

        initPlatforms() {
            // Already populated above, could fetch from API if needed
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            this.generalError = '';

            try {
                const response = await fetch('{{ route('api.v1.tenants.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.form)
                });

                const data = await response.json();

                if (!response.ok) {
                    // Handle validation errors
                    if (data.errors) {
                        // Convert Laravel errors array to Alpine-friendly object
                        this.errors = {};
                        data.errors.forEach(error => {
                            this.errors[error.field] = error.message;
                        });
                    } else {
                        this.generalError = data.message || 'Failed to create tenant';
                    }
                    return;
                }

                // Success - redirect to tenant list
                window.location.href = '{{ route('dashboard.tenants.index') }}';
            } catch (err) {
                this.generalError = 'Network error. Please try again.';
                console.error('Submit error:', err);
            } finally {
                this.submitting = false;
            }
        }
    }
}
</script>
@endpush
```

**Source:** Laravel Sanctum authentication, Alpine.js form handling, TailwindCSS form styles

### Sync Status Polling
```blade
<!-- resources/views/dashboard/tenants/index.blade.php -->
<!-- Tenant card with sync status -->
<div class="bg-white rounded-lg shadow p-4">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h3 class="text-lg font-semibold" x-text="tenant.name"></h3>
            <p class="text-sm text-gray-600" x-text="tenant.platform_type"></p>
        </div>
        <span
            class="inline-block px-2 py-1 text-sm rounded"
            :class="{
                'bg-green-100 text-green-800': tenant.status === 'active',
                'bg-yellow-100 text-yellow-800': tenant.status === 'pending_setup',
                'bg-red-100 text-red-800': tenant.status === 'sync_error'
            }"
            x-text="tenant.status"
        ></span>
    </div>

    <!-- Last sync info -->
    <div class="text-sm text-gray-500 mb-4">
        <div x-show="tenant.last_sync_at">
            Last sync: <span x-text="formatDate(tenant.last_sync_at)"></span>
        </div>
        <div x-show="tenant.sync_status">
            Status: <span x-text="tenant.sync_status"></span>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
        <a
            :href="`/dashboard/tenants/${tenant.id}`"
            class="px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded"
        >
            View Details
        </a>
        <button
            @click="triggerSync(tenant.id)"
            :disabled="syncing"
            class="px-4 py-2 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded disabled:opacity-50"
        >
            <span x-show="!syncing">Sync Now</span>
            <span x-show="syncing">Syncing...</span>
        </button>
    </div>

    <!-- Sync progress (shown during sync) -->
    <div x-show="syncLog.status && syncLog.status !== 'completed'" class="mt-4">
        <div class="flex justify-between text-sm mb-1">
            <span>Syncing products...</span>
            <span x-text="`${syncLog.progress_percentage || 0}%`"></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div
                class="bg-blue-600 h-2 rounded-full transition-all"
                :style="`width: ${syncLog.progress_percentage || 0}%`"
            ></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">
            <span x-text="syncLog.processed_products || 0"></span> /
            <span x-text="syncLog.total_products || 0"></span> products
        </div>
    </div>
</div>

<script>
function tenantList() {
    return {
        tenants: [],
        syncing: false,
        syncLog: {},

        async triggerSync(tenantId) {
            this.syncing = true;

            try {
                // Trigger sync
                const response = await fetch('/api/v1/sync/dispatch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('token')}`,
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ tenant_id: tenantId })
                });

                const data = await response.json();

                if (response.ok) {
                    // Start polling sync status
                    this.pollSyncStatus(data.data.sync_id);
                } else {
                    alert('Failed to trigger sync: ' + (data.message || 'Unknown error'));
                    this.syncing = false;
                }
            } catch (err) {
                console.error('Sync error:', err);
                alert('Network error while triggering sync');
                this.syncing = false;
            }
        },

        async pollSyncStatus(syncLogId) {
            const pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/v1/sync/status/${syncLogId}`, {
                        headers: {
                            'Authorization': `Bearer ${localStorage.getItem('token')}`,
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    this.syncLog = data.data;

                    // Stop polling if sync completed or failed
                    if (['completed', 'failed', 'partially_failed'].includes(data.data.status)) {
                        clearInterval(pollInterval);
                        this.syncing = false;
                        // Refresh tenant list to show updated last_sync_at
                        await this.fetchTenants();
                    }
                } catch (err) {
                    console.error('Polling error:', err);
                    clearInterval(pollInterval);
                    this.syncing = false;
                }
            }, 2000); // Poll every 2 seconds
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString();
        }
    }
}
</script>
```

**Source:** Existing sync API endpoints, Alpine.js polling pattern, TailwindCSS progress bar

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| jQuery + DataTables | Alpine.js + server-side pagination | 2022-2023 | Alpine.js is lighter, reactive, no jQuery dependency. Server-side pagination is more efficient for large datasets. |
| Bootstrap components | TailwindCSS utility classes | 2021-2022 | TailwindCSS is more customizable, smaller bundle size, better mobile responsiveness. Bootstrap is too opinionated. |
| Blade only (full page reloads) | Blade + Alpine.js (SPA-like) | 2021-2023 | Alpine.js adds reactivity without full SPA framework complexity. Best of both worlds: server-rendered + interactive. |
| Laravel Mix | Vite | Laravel 9 (2022) | Vite is significantly faster (10-100x HMR), modern ESM-based, better UX for development. Laravel 11 uses Vite by default. |
| axios only | fetch() or axios (both viable) | 2022+ | fetch() is built-in, no dependency. axios provides convenience (interceptors, automatic JSON transforms, better error handling). Use either based on preference. |

**Deprecated/outdated:**
- **Laravel Mix:** Replaced by Vite in Laravel 9+. Don't use for new projects.
- **jQuery:** Unnecessary in 2026. Alpine.js or vanilla JS is sufficient and lighter.
- **Bootstrap 4/5:** Still maintained but TailwindCSS is modern standard for Laravel projects.
- **Blade Components (legacy):** Before Laravel 7, Blade components were different syntax. Use modern @components syntax.
- **Browserify/Webpack:** Outdated bundlers. Vite or esbuild are current standards.

**Laravel 11 specific changes (2024):**
- Simplified application structure (no models/ directory by default)
- PHP 8.2+ required
- Vite as default asset bundler (no Mix)
- Simplified routing (routes in bootstrap/app.php or routes/api.php)
- Improved queue job batching
- Native PHP types in many stubs

## Open Questions

1. **Alpine.js CDN vs npm bundle for dashboard**
   - What we know: CDN is simpler (no build step), npm bundle is smaller (can tree-shake), Vite already configured
   - What's unclear: Performance impact of CDN (~15KB) vs npm bundle for dashboard use case
   - Recommendation: Use CDN for Phase 7 (speed to market). Can optimize to npm bundle in Phase 8 if performance testing shows need. Alpine.js is lightweight enough that CDN won't noticeably impact dashboard load times.

2. **Authentication persistence for dashboard**
   - What we know: Laravel Sanctum tokens expire after 4 hours inactivity (from Phase 2). Need to handle expiry gracefully.
   - What's unclear: Should dashboard use session-based auth (web routes) or token-based auth (API routes)?
   - Recommendation: Use token-based auth (consume existing API endpoints). This keeps authentication consistent between dashboard and potential future SPA/mobile apps. Implement token refresh logic or redirect to login on 401.

3. **Real-time updates for sync status**
   - What we know: Sync operations run in background queues. Dashboard needs to show progress.
   - What's unclear: Should we use polling (every 2-5 seconds) or Laravel Echo + WebSockets?
   - Recommendation: Use polling for Phase 7 (simpler, no infrastructure). WebSocket broadcasting is overkill for admin dashboard with single user. Polling every 2-3 seconds is sufficient and easier to implement.

4. **Search interface complexity**
   - What we know: Product search API exists with fuzzy matching and pagination.
   - What's unclear: Should dashboard support advanced search filters (price range, category, stock status)?
   - Recommendation: Keep search simple for Phase 7 (text search only). Advanced filters are in v2 requirements (SEARCH-50) and not needed for MVP. Focus on basic text search with debouncing.

5. **Mobile vs desktop feature parity**
   - What we know: Dashboard must be responsive (UI-11). Mobile users are agency admins on-the-go.
   - What's unclear: Should mobile UI hide complex features (sync history, error logs) or adapt them?
   - Recommendation: Mobile-first responsive design. Show all features on mobile but adapt layout (stacked cards, collapsible sections, slide-over menus). Don't hide features, reorganize them.

## Validation Architecture

> Nyquist validation is enabled for this phase (workflow.nyquist_validation is not explicitly false in config.json).

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.x (Laravel 11 default) |
| Config file | phpunit.xml (already exists in project root) |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| UI-01 | View client store list with status indicators | browser (Dusk) | `php artisan dusk --filter testViewTenantList` | ❌ Wave 0 (Need Dusk setup) |
| UI-02 | Create client store via form | browser (Dusk) | `php artisan dusk --filter testCreateTenant` | ❌ Wave 0 |
| UI-03 | Edit client store details | browser (Dusk) | `php artisan dusk --filter testEditTenant` | ❌ Wave 0 |
| UI-04 | Delete client store with confirmation | browser (Dusk) | `php artisan dusk --filter testDeleteTenant` | ❌ Wave 0 |
| UI-05 | Trigger sync operation | browser (Dusk) | `php artisan dusk --filter testTriggerSync` | ❌ Wave 0 |
| UI-06 | View last sync status | browser (Dusk) | `php artisan dusk --filter testViewSyncStatus` | ❌ Wave 0 |
| UI-07 | Search products within client catalog | browser (Dusk) | `php artisan dusk --filter testSearchProducts` | ❌ Wave 0 |
| UI-08 | View error log with filtering | browser (Dusk) | `php artisan dusk --filter testViewErrorLog` | ❌ Wave 0 |
| UI-09 | Blade templates with Alpine.js | feature (HTTP) | `php artisan test --filter testBladeRenders` | ❌ Wave 0 |
| UI-10 | TailwindCSS styling | feature (HTTP) | `php artisan test --filter testTailwindClasses` | ❌ Wave 0 |
| UI-11 | Responsive mobile/tablet viewing | browser (Dusk) | `php artisan dusk --filter testResponsiveLayout` | ❌ Wave 0 |

**Test Type Rationale:**
- Browser (Dusk) tests for UI-01 through UI-08: These are full user interaction flows requiring JavaScript execution (Alpine.js), form submissions, and DOM manipulation. PHPUnit feature tests cannot test Alpine.js interactivity.
- Feature (HTTP) tests for UI-09, UI-10: Can verify Blade templates render and contain expected HTML/Tailwind classes without JavaScript execution.
- Browser (Dusk) tests for UI-11: Need to test actual responsive behavior at different viewport sizes.

### Sampling Rate
- **Per task commit:** `php artisan dusk --filter test<TaskName>` (specific Dusk test for current task)
- **Per wave merge:** `php artisan test` (full PHPUnit suite) + `php artisan dusk` (full Dusk suite)
- **Phase gate:** Full PHPUnit + Dusk suites green before `/gsd:verify-work`

### Wave 0 Gaps

**Critical Gap: Laravel Dusk Not Configured**
- [ ] `tests/Browser/` directory — Dusk browser tests directory
- [ ] `phpunit.dusk.xml` — Dusk configuration (separate from main phpunit.xml)
- [ ] `tests/DuskTestCase.php` — Base Dusk test case with Chrome driver setup
- [ ] Laravel Dusk package: `composer require --dev laravel/dusk` — browser automation framework
- [ ] ChromeDriver installation: `php artisan dusk:chrome-driver --detect` — auto-detect ChromeDriver version
- [ ] Dusk environment setup: `.env.dusk.{environment}` file with APP_URL=http://localhost:8001
- [ ] `tests/Browser/Dashboard/HomeTest.php` — Placeholder test for dashboard home page
- [ ] `tests/Browser/Dashboard/Tenants/IndexTest.php` — Placeholder test for tenant list page
- [ ] `tests/Browser/Dashboard/Products/SearchTest.php` — Placeholder test for product search page
- [ ] `tests/Browser/Dashboard/Sync/HistoryTest.php` — Placeholder test for sync history page
- [ ] `tests/Feature/Dashboard/RenderTest.php` — Feature test for Blade template rendering (UI-09, UI-10)

**Rationale:**
- Phase 7 is entirely about user interface (Blade + Alpine.js + TailwindCSS)
- Existing PHPUnit tests (Unit/Feature/Integration) cannot test JavaScript interactivity (Alpine.js components)
- Laravel Dusk is the standard Laravel browser automation framework for testing JavaScript UIs
- Dusk tests require ChromeDriver and headless Chrome browser setup
- Must configure Dusk in Wave 0 before any UI tests can run

**Additional Wave 0 Tasks:**
- [ ] Install @tailwindcss/forms: `npm install -D @tailwindcss/forms` — for better form styling
- [ ] Configure TailwindCSS content: Add `resources/views/**/*.blade.php` to tailwind.config.js content array
- [ ] Create `resources/views/dashboard/` directory structure
- [ ] Create `resources/views/dashboard/layout.blade.php` — main dashboard layout
- [ ] Add Alpine.js CDN to layout: `<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>`
- [ ] Add x-cloak CSS: `[x-cloak] { display: none !important; }` to `resources/css/app.css`
- [ ] Create web routes for dashboard: `routes/web.php` — define dashboard routes with auth middleware
- [ ] Update authentication: Add web auth routes (`/login`, `/logout`) if not already present

**Wave 0 Estimated Effort:** 4-6 hours
- Dusk installation and configuration: 2 hours
- Dusk test stubs creation: 2 hours
- TailwindCSS/Alpine.js setup: 1 hour
- Web routes and auth setup: 1 hour

**Post-Wave 0:**
All phase requirements (UI-01 through UI-11) can be tested with automated browser tests (Dusk) and feature tests. No manual-only requirements.

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** - Verified Blade templating, Vite configuration, authentication (Sanctum), routing structure. Laravel 11 uses Vite by default, simplified application structure, PHP 8.2+ required.
- **Alpine.js Documentation (alpinejs.dev)** - Verified x-data, x-init, x-model, x-show, x-for directives, event handlers (@click, @submit), transitions, x-cloak, debounce modifier. Alpine.js 3.14.x is current stable version.
- **TailwindCSS Documentation (tailwindcss.com)** - Verified responsive breakpoints (sm: 640px, md: 768px, lg: 1024px, xl: 1280px), utility classes for layout (flex, grid), spacing (p-4, m-2), colors (bg-blue-500), forms plugin (@tailwindcss/forms). TailwindCSS 3.4.x is current stable version.
- **Laravel Sanctum Documentation** - Verified token-based authentication, token expiration, token storage (localStorage or meta tag), Authorization header format.
- **Project Codebase** - Verified existing API endpoints (TenantController, SyncController, ProductSearchController), API response structures (TenantResource, SyncLogResource), enums (PlatformType, TenantStatus, SyncStatus), middleware (auth:sanctum, tenant context).

### Secondary (MEDIUM confidence)
- **Laravel Breeze / Jetstream Starter Kits** - Reference for dashboard layouts, authentication flows, responsive navigation patterns. (Not directly used but provide proven patterns).
- **TailwindUI** - Reference for responsive dashboard layouts, sidebar navigation, modal dialogs, form components. (Proven design patterns, but not copied directly).
- **Laravel + Alpine.js Community Best Practices** - Common patterns for Blade + Alpine.js integration (component organization, state management, API consumption). (Verified from multiple community sources: Laracasts, Laravel News, GitHub examples).

### Tertiary (LOW confidence)
- **WebSearch results** - Attempted to search for 2026 trends in Laravel + Alpine.js dashboards, but search service was rate-limited. All recommendations are based on training knowledge (2024-2025) and official documentation verification.
- **Performance Benchmarks** - No specific benchmarks found for Alpine.js CDN vs npm bundle performance impact. Recommendation based on Alpine.js lightweight (~15KB) nature and community consensus.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11 + Blade + Alpine.js + TailwindCSS is the standard stack for Laravel dashboards in 2024-2025. All libraries are already in project or have proven integration patterns.
- Architecture: HIGH - Blade + Alpine.js patterns are well-documented and battle-tested. Responsive design with TailwindCSS is standard practice. Existing API endpoints provide all needed backend functionality.
- Pitfalls: HIGH - All identified pitfalls are well-known in Laravel + Alpine.js development, with established solutions documented in official docs and community resources.
- Validation Architecture: HIGH - Laravel Dusk is the standard Laravel browser testing framework. Test mapping is straightforward (UI requirements → browser tests). Wave 0 gaps are clear and estimable.

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (30 days - Laravel 11, Alpine.js 3.x, and TailwindCSS 3.x are stable releases. No major version changes expected in next 30 days.)

**Key assumptions:**
1. Laravel Sanctum authentication is already configured from Phase 2 (token-based auth works)
2. All API endpoints from Phases 2-6 are functional and return expected data structures
3. Agency admin users have web browser access (this is a web dashboard, not mobile app)
4. Dashboard will be self-hosted (already running in Docker from Phase 1)
5. Single-user agency admin (no multi-user role management needed in Phase 7)

**Researcher's note:**
This phase is straightforward because it consumes existing, well-tested APIs (Phases 2-6) and uses a standard Laravel stack (Blade + Alpine.js + TailwindCSS). The main complexity is in responsive design and Alpine.js state management, both of which have proven patterns. No new backend logic is needed—this phase is purely frontend development consuming existing RESTful APIs. The biggest risk is Wave 0 Dusk setup if team lacks browser testing experience, but Laravel Dusk is well-documented and has excellent Laravel integration.
