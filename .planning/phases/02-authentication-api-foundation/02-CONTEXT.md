# Phase 2: Authentication & API Foundation - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Agency admin authentication system with email/password registration, login, logout, and token-based API access. RESTful API foundation with versioned endpoints (/api/v1/), consistent JSON response structure, request validation, rate limiting, and proper HTTP status codes.

</domain>

<decisions>
## Implementation Decisions

### Authentication Method
- **Laravel Sanctum (API tokens)** — Use Laravel's official token authentication system
- **Token storage** — Tokens stored in database (personal_access_tokens table)
- **Expire after inactivity** — Tokens expire after period of non-use (not fixed time)
- **4-hour duration** — Tokens valid for 4 hours during active use, then expire
- **Multiple tokens supported** — Allow simultaneous logins from different devices/browsers
- **Logout invalidates token** — Token deletion on logout for that specific device

### API Response Format
- **Wrapped responses** — All responses use consistent structure: `{data: {...}, meta: {...}, errors: [...]}`
- **Success responses** — `{data: {...}, meta: {page: 1, per_page: 20, total: 100}}`
- **Error responses** — `{errors: [{field: "email", message: "Invalid email"}]}`
- **Field-based validation errors** — Array of errors with field and message properties
- **Supports multiple errors** — Clients can display all validation issues at once

### HTTP Status Codes
- **Full RESTful semantics** — Use appropriate HTTP status codes:
  - 200 OK — Successful GET/PATCH
  - 201 Created — Successful POST
  - 204 No Content — Successful DELETE
  - 400 Bad Request — Malformed request
  - 401 Unauthorized — Missing or invalid token
  - 403 Forbidden — Valid token but insufficient permissions
  - 404 Not Found — Resource doesn't exist
  - 422 Unprocessable Entity — Validation failed
  - 429 Too Many Requests — Rate limit exceeded
  - 500 Internal Server Error — Server error

### Pagination
- **Laravel default with totals** — `{data: [...], meta: {current_page, per_page, total, last_page}}`
- **20 items per page** — Default pagination size
- **Clients can see total count** — Enables page numbers and item count display

### Rate Limiting
- **Per authenticated user** — Limits scoped by user_id
- **IP-based for unauthenticated** — Fallback to IP address when no user context
- **60 requests/minute** — Read operations (GET, HEAD, OPTIONS)
- **10 requests/minute** — Write operations (POST, PUT, PATCH, DELETE)
- **Endpoint-specific limits** — Auth endpoints stricter (5 login attempts/minute)
- **Rate limit headers** — Include `Retry-After` and `X-RateLimit-Remaining` headers
- **429 response** — `{message: "Rate limit exceeded", retry_after: 45}`

### API Versioning
- **URL-based versioning** — `/api/v1/` prefix for all endpoints
- **Version 1 only** — No backward compatibility needed for v1

### Claude's Discretion
- Password validation rules (length, complexity requirements)
- Email verification flow (if needed)
- Password reset flow (if needed)
- Exact token generation algorithm (Sanctum handles this)
- Response metadata fields beyond pagination
- Rate limit storage backend (Redis recommended for performance)

</decisions>

<specifics>
## Specific Ideas

- "I want API responses to be predictable and consistent — clients should know what to expect"
- "Use Laravel best practices — Sanctum is the modern choice for Laravel 11"
- "Rate limiting should protect against abuse but not get in the way of legitimate admin work"
- "Multiple device support is important — I might work from desktop and mobile"

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Laravel 11 installation** — Fresh installation with default structure
- **users table migration** — Already exists (0001_01_01_000000_create_users_table.php)
- **Laravel Sanctum** — Available via Composer (laravel/sanctum)
- **routes/api.php** — Standard API routes file (not yet created, need to add)

### Established Patterns
- **Laravel conventions** — Follow Laravel's directory structure and naming patterns
- **MVC architecture** — Controllers in app/Http/Controllers, routes/ directory
- **Migration system** — Database changes via Laravel migrations
- **Environment config** — .env file for configuration

### Integration Points
- **API routes** — Create routes/api.php for /api/v1/ endpoints
- **Auth middleware** — Sanctum middleware for token validation
- **Controllers** — Create AuthController for registration/login/logout
- **Middleware** — Apply rate limiting and auth middleware to route groups
- **User model** — Extend default User model for Sanctum token support

</code_context>

<deferred>
## Deferred Ideas

- Password reset flow — Phase 6 or later (admin UX enhancement)
- Email verification — Phase 6 or later (security enhancement)
- OAuth/Social login — Not planned for v1
- Multi-factor authentication — Not planned for v1
- API documentation (OpenAPI/Swagger) — Phase 8 (CI/CD & Documentation)

</deferred>

---

*Phase: 02-authentication-api-foundation*
*Context gathered: 2026-03-13*
