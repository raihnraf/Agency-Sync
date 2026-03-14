# Phase 7: Admin Dashboard - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Build web-based admin dashboard for agency staff to manage clients (create, view, edit, delete tenants), trigger and monitor catalog sync operations, search products within client catalogs, and view error logs. Dashboard uses Blade templates with Alpine.js for interactivity and TailwindCSS for styling.

</domain>

<decisions>
## Implementation Decisions

### Authentication Setup
- Install Laravel Breeze (Blade edition) for complete auth scaffolding
- Customize Breeze templates to match AgencySync design
- Skip email verification for v1 (user can login immediately after registration)
- Profile-based password reset: user provides current password + new password in profile settings (no email flow)
- Admin-only signup: first admin created via artisan command, additional users created only by existing admins from within dashboard
- Session-based authentication for web routes (routes/web.php) coexists with Sanctum token auth for API routes (routes/api.php)

### Dashboard Navigation & Layout
- Left sidebar navigation with links: Tenants, Error Logs, Logout
- Tenant list with summary stats (total products, last sync time, sync status) as homepage (/dashboard redirects to /dashboard/tenants)
- Sidebar collapses to hamburger menu overlay on mobile (< 768px)
- Sidebar-only layout: AgencySync logo at top, user menu (avatar, logout) at bottom, no separate top header bar

### Real-time Updates & Polling
- Tenant list auto-refreshes every 5-10 seconds to update sync status badges and product counts
- Error log page auto-refreshes every 5-10 seconds to show new sync errors
- Product search uses search-as-you-type with 300ms debounce (existing decision from Phase 7-04)
- Browser notifications or toast messages when background sync operations complete (success or failure)
- Sync status on tenant detail page polls every 2 seconds while status is running/pending (existing decision from Phase 7-03)

### User Experience Details
- Skeleton screens (gray placeholder boxes) while content loads
- Illustrated empty states with helpful messages and CTA buttons (e.g., "No tenants yet. Create your first client store to get started.")
- Toast notifications (corner, auto-dismiss) + inline error messages for error display
- Toast notification + redirect after successful actions (create tenant, update tenant, trigger sync)

### Claude's Discretion
- Exact toast notification duration and positioning
- Specific illustration style for empty states
- Exact polling interval within 5-10 second range (balancing responsiveness vs server load)
- Sidebar collapse animation details

</decisions>

<specifics>
## Specific Ideas

- Portfolio-focused: Dashboard must be immediately demoable to show Blade experience for DOITSUYA qualification ("Nice to have: Twig / Smarty / Blade")
- Hybrid architecture: API routes use Sanctum tokens (machine-to-machine), Web routes use session auth (human-facing)
- Login page exists but non-functional: custom login.blade.php posts to undefined route('login.submit'). Breeze will provide working auth system.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Blade templates:** resources/views/layouts/dashboard.blade.php (layout wrapper), resources/views/dashboard/tenants/*.blade.php (tenant CRUD), resources/views/dashboard/error-log.blade.php
- **Alpine.js components:** public/js/tenant-list.js, public/js/sync-status.js, public/js/product-search.js (extracted in Phase 7-05)
- **TailwindCSS configuration:** Custom indigo palette, Inter font, custom CSS with animations (Phase 7-05)
- **Web routes:** routes/web.php has dashboard routes protected by auth middleware
- **Dashboard controllers:** app/Http/Controllers/Dashboard/TenantController.php, ErrorLogController.php
- **Custom login template:** resources/views/auth/login.blade.php (will be replaced by Breeze)

### Established Patterns
- API versioning with /api/v1/ prefix (Phase 2)
- Laravel Sanctum for API token authentication (Phase 2)
- Session-based authentication for web routes (Phase 7-01 decision)
- Alpine.js component pattern: Export functions returning reactive objects with init/destroy lifecycle hooks
- TailwindCSS CDN for rapid prototyping
- Mobile-first responsive design with flex-col on mobile, sm:flex-row on desktop

### Integration Points
- **Authentication:** Web routes (routes/web.php) need login/logout/register routes from Breeze
- **API coexistence:** API routes (routes/api.php) continue using Sanctum tokens, unaffected by Breeze
- **User model:** Already has HasApiTokens trait from Sanctum (Phase 2). Breeze will add session auth methods.
- **Middleware:** auth middleware already protecting dashboard routes, Breeze will make it functional

### Known Gaps
- Laravel Breeze not installed (need: composer require laravel/breeze && php artisan breeze:install blade)
- No Auth controllers (app/Http/Controllers/Auth/ doesn't exist)
- No login/logout/register routes (login.blade.php posts to undefined route)
- Custom login.blade.php will be replaced by Breeze templates

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 07-admin-dashboard*
*Context gathered: 2026-03-14*
