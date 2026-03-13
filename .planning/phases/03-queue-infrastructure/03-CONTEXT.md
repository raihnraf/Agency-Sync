# Phase 3: Tenant Management System - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Multi-tenant management system where agency admins can create, view, update, and delete client stores with complete data isolation via tenant_id discriminator and global scopes. All database queries automatically scope to current tenant context selected via X-Tenant-ID header.

</domain>

<decisions>
## Implementation Decisions

### Tenant Selection Strategy
- **Header-based tenant selection** — Use `X-Tenant-ID` header in all tenant-scoped requests
- **Missing header returns 422** — Explicit error: "X-Tenant-ID header is required"
- **Invalid tenant returns 404** — Generic "Tenant not found or access denied" (prevents enumeration)
- **Many-to-many user-tenant association** — Users can access multiple tenants via pivot table
- **Combined middleware approach** — `SetTenant` middleware stores context, `TenantScope` applies global scopes to all tenant-aware models

### Tenant Model Structure
- **Single Tenant model** (not separated into Tenant + TenantCredential)
- **Fields**: id, name, slug, platform_type, platform_url, status, api_credentials (encrypted), agency_id, settings (JSON), last_sync_at, sync_status, created_at, updated_at
- **Status enum**: active, pending_setup, sync_error, suspended (includes sync states for Phase 6)
- **Slug auto-generated** — Auto-generate from name using `Str::slug()`: "My Store" → "my-store"
- **Platform enum**: shopify, shopware

### Credential Encryption
- **Encrypted + JSON cast** — `protected $casts = ['api_credentials' => 'encrypted:json'];`
- **Automatic encrypt/decrypt** — Laravel handles encryption on save, decryption on access
- **AES-256-CBC encryption** — Uses APP_KEY from .env (Laravel default)

### Credential Validation
- **Synchronous API validation** — Call platform API during tenant creation to verify credentials (blocking request)
- **Show platform error details** — Return `{error: "Invalid API credentials", platform_response: "..."}` to help user debug
- **No retry rate limiting** — Allow immediate retry after fixing typo (Phase 4 will add general rate limiting)

### API Response Format (from Phase 2)
- **Consistent structure** — Success: `{data: {...}, meta: {...}}`, Error: `{errors: [{field, message}]}`
- **HTTP status codes** — 200 (GET/PATCH), 201 (POST), 204 (DELETE), 401 (auth), 404 (not found), 422 (validation)

### Claude's Discretion
- Exact structure of tenant_user pivot table (additional fields? timestamps?)
- Global scope implementation details (trait vs base class vs manual scopes)
- Validation rules beyond platform_type (name length, URL format, etc.)
- Tenant ownership verification logic (who can create/update/delete which tenants)
- Exact error message wording for edge cases

</decisions>

<specifics>
## Specific Ideas

- "I want tenant selection to be explicit — the header makes it clear which tenant you're working with"
- "Synchronous validation is slower but gives immediate feedback — better UX for setup"
- "Show the actual platform error so admins can debug credential issues without contacting support"
- "Many-to-many lets agencies have multiple team members accessing different tenant sets"
- "Include sync fields now so Phase 6 doesn't need migration"

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **User model** (`app/Models/User.php`) — Has `HasApiTokens` trait from Sanctum, ready for many-to-many relationship
- **API routes** (`routes/api.php`) — `/api/v1/` prefix structure, middleware patterns established
- **Middleware** — `auth:sanctum`, `throttle:api-read/write`, `token.expire` already configured
- **Laravel 11** — Enums, readonly properties, cast attributes available

### Established Patterns
- **Controller response structure** — BaseApiController with `success()`, `error()`, `created()`, `noContent()` helpers
- **Validation error format** — `{errors: [{field: "email", message: "Invalid email"}]}`
- **Form Request classes** — Separate validation logic from controllers (e.g., RegisterRequest)
- **Resource classes** — API Resource transformation layer for consistent responses

### Integration Points
- **API routes** — Add `/api/v1/tenants` route group with tenant middleware
- **Middleware** — Create `SetTenant` and `TenantScope` middleware for automatic scoping
- **Migrations** — Create tenants table, tenant_user pivot table
- **Models** — Create Tenant model with relationships to User
- **Controllers** — Create TenantController with index, store, show, update, destroy methods
- **Form Requests** — CreateTenantRequest, UpdateTenantRequest for validation

</code_context>

<deferred>
## Deferred Ideas

- Sync operation triggers (Phase 6: Catalog Synchronization)
- Queue job status monitoring (Phase 5 or Phase 7: Admin Dashboard)
- Tenant-specific permissions/roles (v2 feature)
- Bulk tenant operations (v2 feature)
- Tenant analytics/usage metrics (future enhancement)

</deferred>

---

*Phase: 03-tenant-management*
*Context gathered: 2026-03-13*
