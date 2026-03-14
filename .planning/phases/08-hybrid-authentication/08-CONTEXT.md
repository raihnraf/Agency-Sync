# Phase 8: Hybrid Authentication - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Add Laravel Breeze (Blade edition) for web UI authentication (login, logout, session management) while preserving existing Sanctum token auth for API routes. Make the dashboard login → tenant management flow functional for portfolio demos. Web routes use session-based auth, API routes continue using Sanctum tokens.

</domain>

<decisions>
## Implementation Decisions

### Initial Admin Creation
- **Custom artisan command** — `php artisan agency:admin` with interactive prompts
- **Full validation** — Email uniqueness check, password minimum 8 characters with retry on failure
- **Command output** — Success message + login URL: "Admin created! Login at: http://localhost/login"
- **Only method for v1** — No registration route, no user management UI (deferred)

### User Registration Access
- **Remove /register route** — Delete Breeze's registration route after installation
- **Defer user management UI** — Admin-only signup happens via artisan command only
- **No self-registration** — AgencySync is a single-tenant application (agency controls all users)

### Login Page Customization
- **Basic branding only** — Add AgencySync logo, indigo theme colors (match TailwindCSS from Phase 7), custom footer
- **Keep Breeze layout structure** — Don't rebuild from scratch, customize template styling
- **Portfolio-ready appearance** — Clean, modern login that demonstrates Blade experience

### Logout Behavior
- **Redirect to /home** — After logout, users go to public-facing landing page (welcome.blade.php)
- **Public home page** — Explains AgencySync value proposition with login CTA button

### Session Configuration
- **2-hour lifetime** — Default Laravel session expiration (balances security with UX)
- **5-year remember me** — Enable remember me checkbox with Laravel's default 5-year duration
- **Multiple concurrent sessions** — Allow users logged in from multiple devices (desktop, mobile, tablet)
- **Manageable sessions** — Show active sessions in profile, allow manual revocation (future phase)

### Security Features
- **2FA deferred to v2** — Skip two-factor authentication for v1 milestone
- **Skip email verification** — User can login immediately after admin creation (from Phase 7)
- **Session-based only for web** — API routes continue using Sanctum tokens (from Phase 2)

### API/Web Coexistence
- **Separate authentication systems** — Web routes (routes/web.php) use sessions, API routes (routes/api.php) use Sanctum tokens
- **No conflicts** — Breeze adds session methods to User model, Sanctum's HasApiTokens trait remains intact
- **Separate middleware** — `auth` middleware for web, `auth:sanctum` for API

### Claude's Discretion
- Exact password strength rules beyond minimum 8 characters
- Specific validation error messages
- Session management UI implementation details (future phase)
- Exact styling customization approach (CSS vs Blade component overrides)

</decisions>

<specifics>
## Specific Ideas

- **Portfolio-focused**: Working login → dashboard flow is critical for DOITSUYA qualification ("Nice to have: Twig / Smarty / Blade")
- **Single-tenant application**: AgencySync serves one agency, not multi-agency SaaS. All users created by that agency's admin.
- **Clean separation**: API tokens for machine-to-machine (webhooks, external integrations), sessions for human-facing dashboard
- **First impression matters**: Login page is the first thing employers see — clean branding shows attention to detail

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Web routes** (`routes/web.php`) — Dashboard routes already protected by `auth` middleware (line 12)
- **Dashboard controllers** — `app/Http/Controllers/Dashboard/TenantController.php`, `ErrorLogController.php` exist
- **User model** — Has `HasApiTokens` trait from Sanctum (Phase 2), Breeze will add session auth methods
- **API authentication** — Sanctum fully implemented with 4-hour token expiration (Phase 2)
- **TailwindCSS configuration** — Custom indigo palette from Phase 7-05 for consistent branding

### Known Gaps
- **No Laravel Breeze** — Not in composer.json, need: `composer require laravel/breeze --dev`
- **No Auth controllers** — `app/Http/Controllers/Auth/` directory doesn't exist
- **Non-functional login** — Custom `resources/views/auth/login.blade.php` posts to undefined route
- **No logout route** — Dashboard sidebar has logout link but route doesn't exist

### Integration Points
- **Installation** — Run `php artisan breeze:install blade` to scaffold auth
- **Routes** — Breeze creates login/logout/register routes in routes/web.php
- **Middleware** — `auth` middleware already protecting dashboard routes, Breeze makes it functional
- **Views** — Breeze creates auth/login.blade.php, auth/register.blade.php (to be removed/modified)
- **Controllers** — Breeze creates AuthenticatedSessionController, RegisteredUserController (register controller to be removed)

### Established Patterns
- **API versioning** — /api/v1/ prefix for API routes (unchanged)
- **Response structure** — `{data: {...}, meta: {...}}` for API (unchanged)
- **Rate limiting** — Per-user rate limiting from Phase 2 (unchanged)

</code_context>

<deferred>
## Deferred Ideas

- **User management UI** — Admin dashboard to create/edit/delete users from UI (v2 or future phase)
- **Profile-based password reset** — Phase 7 decision, implementation deferred
- **Email verification** — Skip for v1 (Phase 7 decision)
- **2FA (Two-Factor Authentication)** — Defer to v2
- **OAuth/Social login** — Not planned for v1
- **Session management UI** — Show active sessions, revoke functionality (future enhancement)

</deferred>

---

*Phase: 08-hybrid-authentication*
*Context gathered: 2026-03-14*
