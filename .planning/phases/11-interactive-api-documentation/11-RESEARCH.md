# Phase 11: Interactive API Documentation - Research

**Researched:** 2026-03-15
**Domain:** Laravel API Documentation with Scribe
**Confidence:** MEDIUM

## Summary

Phase 11 requires implementing interactive API documentation using Laravel Scribe to auto-generate beautiful, consumable API documentation from existing Laravel code. This phase demonstrates the "API-first backend system" portfolio capability by making all API endpoints visible, testable, and well-documented without requiring employers to read source code.

**Primary recommendation:** Use Laravel Scribe (knuckleswtf/scribe) as the standard Laravel API documentation package - it integrates seamlessly with Laravel 11, supports Sanctum authentication, generates interactive documentation with "Try it out" functionality, and produces curl command examples automatically.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| knuckleswtf/scribe | ^4.x | API documentation generation | Most popular Laravel API doc generator, actively maintained, excellent Laravel 11 support |
| laravel/sanctum | * | Already installed - token auth | Scribe has built-in support for Sanctum token authentication |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None | - | No additional packages needed | Scribe is standalone |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel Scribe | OpenAPI/Swagger (manual) | Manual OpenAPI maintenance overhead, no auto-generation from code |
| Laravel Scribe | Stripe API docs (custom) | Requires building entire documentation system from scratch |

**Installation:**
```bash
composer require --dev knuckleswtf/scribe
php artisan vendor:publish --tag=scribe-config
php artisan scribe:generate
```

## Architecture Patterns

### Recommended Project Structure
```
public/docs/              # Generated static documentation (auto-created)
config/scribe.php         # Scribe configuration (published)
routes/web.php            # Add /docs route for viewing documentation
app/Http/Controllers/Api/V1/  # Add @group and @authenticated annotations
```

### Pattern 1: Controller DocBlocks for Auto-Documentation
**What:** Scribe extracts API documentation from PHPDoc comments on controllers and methods
**When to use:** All API endpoints should have comprehensive PHPDoc blocks
**Example:**
```php
/**
 * @group Tenant Management
 *
 * Display a listing of the user's tenants.
 *
 * @authenticated
 *
 * @responseField data{0}.id string The tenant UUID
 * @responseField data{0}.name string The tenant name
 * @responseField data{0}.status string Tenant status (active, pending, error)
 * @responseField data{0}.platform_type string Platform type (shopify, shopware)
 */
public function index()
{
    // ...
}
```

### Pattern 2: Authentication Configuration
**What:** Configure Scribe to use Sanctum Bearer tokens for authenticated endpoints
**When to use:** All protected API endpoints (most endpoints in this project)
**Example (config/scribe.php):**
```php
'auth' => [
    'enabled' => true,
    'default' => 'sanctum',
    'sanctum' => [
        'type' => 'bearer',
        'name' => 'Authorization',
        'use_test_user' => true,
        'test_user' => [
            'email' => 'test@example.com',
            'password' => 'password',
        ],
    ],
],
```

### Pattern 3: Request/Response Examples
**What:** Scribe automatically generates example requests and responses from FormRequest validation rules and API Resource responses
**When to use:** All endpoints with request validation or JSON responses
**Example:**
```php
// CreateTenantRequest.php defines validation rules
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'platform_type' => ['required', 'in:shopify,shopware'],
        'api_credentials' => ['required', 'array'],
    ];
}

// Scribe automatically documents these rules in the API docs
```

### Pattern 4: Endpoint Grouping
**What:** Use @group annotation to organize related endpoints
**When to use:** All controllers to create logical navigation in documentation
**Example:**
```php
/**
 * @group Authentication
 */
class AuthController extends BaseController
{
    // ...
}

/**
 * @group Tenant Management
 */
class TenantController extends ApiController
{
    // ...
}

/**
 * @group Catalog Synchronization
 */
class SyncController extends ApiController
{
    // ...
}
```

### Anti-Patterns to Avoid
- **Manual documentation maintenance:** Don't write separate markdown files - let Scribe auto-generate from code
- **Missing authentication setup:** Don't leave authentication unconfigured or interactive "Try it out" won't work
- **Incomplete docblocks:** Don't skip @group, @authenticated, and @responseField annotations - they're critical for clear docs
- **Hardcoding example values:** Don't hardcode request/response examples in docblocks when FormRequest and API Resources already define structure

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| API documentation HTML/CSS/JS | Custom documentation UI with manual endpoint listing | Laravel Scribe's auto-generated static site | Interactive "Try it out", curl examples, authentication handling, responsive design |
| OpenAPI/Swagger JSON generation | Manual OpenAPI specification files | Scribe's automatic OpenAPI export | Stays synchronized with code, supports Postman import |
| Authentication example generation | Manual curl command examples with placeholder tokens | Scribe's automatic authenticated request generation | Tokens auto-injected, examples stay current with auth changes |
| Response schema documentation | Manual JSON schema files | @responseField annotations in docblocks | Single source of truth, auto-updates with code changes |

**Key insight:** Building custom API documentation is a maintenance nightmare. Scribe reads your existing code (routes, controllers, validation rules, API resources) and generates beautiful documentation that stays synchronized automatically.

## Common Pitfalls

### Pitfall 1: Missing Test User Configuration
**What goes wrong:** Interactive "Try it out" buttons fail because Scribe can't obtain authentication tokens
**Why it happens:** Scribe's test_user configuration in config/scribe.php is not set or points to non-existent user
**How to avoid:** Always configure test_user with valid credentials that exist in the database
**Warning signs:** "Try it out" returns 401 Unauthorized, docs show "Authentication required" but no way to test

### Pitfall 2: Incomplete DocBlock Coverage
**What goes wrong:** Documentation has missing parameters, unclear grouping, or no authentication indicators
**Why it happens:** Developers add endpoints without comprehensive PHPDoc blocks
**How to avoid:** Make docblock-first development mandatory - write docblocks before implementing endpoints
**Warning signs:** Some endpoints show "No description" in docs, parameters appear as "unknown" in request examples

### Pitfall 3: Forgetting to Regenerate Documentation
**What goes wrong:** Documentation shows outdated endpoints or missing new features
**Why it happens:** Developers add/change endpoints but don't run `php artisan scribe:generate`
**How to avoid:** Add documentation generation to deployment script (Phase 10) and CI/CD pipeline
**Warning signs:** Live API doesn't match documentation, new endpoints missing from /docs

### Pitfall 4: Not Excluding Internal Routes
**What goes wrong:** Documentation exposes internal/debug endpoints that should be private
**Why it happens:** Scribe by default includes all API routes
**How to avoid:** Configure route matching patterns in config/scribe.php to exclude internal endpoints
**Warning signs:** Debug routes, health checks, or admin-only operations appear in public documentation

### Pitfall 5: Missing Response Field Documentation
**What goes wrong:** API responses show data but documentation doesn't explain what each field means
**Why it happens:** Developers don't add @responseField annotations to docblocks
**How to avoid:** Require @responseField for all complex responses with nested data
**Warning signs:** Response examples show raw JSON but no field descriptions or data types

## Code Examples

Verified patterns from official sources:

### Basic Scribe Installation and Setup
```bash
# Install Scribe as dev dependency
composer require --dev knuckleswtf/scribe

# Publish configuration file
php artisan vendor:publish --tag=scribe-config

# Generate documentation for the first time
php artisan scribe:generate

# Access documentation at
http://localhost/docs
```

### Configuring Sanctum Authentication
```php
// config/scribe.php
'auth' => [
    'enabled' => true,
    'default' => 'sanctum',
    'sanctum' => [
        'type' => 'bearer',
        'name' => 'Authorization', // Header name
        'use_test_user' => true,
        'test_user' => [
            'email' => 'test@example.com',
            'password' => 'password',
        ],
    ],
],
```

### Annotated Controller Example
```php
<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @group Authentication
 *
 * API endpoints for user authentication and token management
 */
class AuthController extends BaseController
{
    /**
     * Register a new user.
     *
     * Creates a new user account and returns an authentication token.
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: secret123
     *
     * @response {
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     },
     *     "token": "plainTextTokenHere"
     *   },
     *   "meta": {
     *     "expires_at": "2026-03-15T12:00:00Z"
     *   }
     * }
     * @response 401 {
     *   "errors": [
     *     {
     *       "field": "email",
     *       "message": "Invalid credentials"
     *     }
     *   ]
     * }
     */
    public function register(RegisterRequest $request)
    {
        // Implementation...
    }

    /**
     * Login user and return token.
     *
     * Authenticates user credentials and returns a Sanctum token.
     *
     * @authenticated
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: secret123
     *
     * @response {
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     },
     *     "token": "plainTextTokenHere"
     *   },
     *   "meta": {
     *     "expires_at": "2026-03-15T12:00:00Z"
     *   }
     * }
     */
    public function login(LoginRequest $request)
    {
        // Implementation...
    }

    /**
     * Logout user and invalidate current token.
     *
     * Invalidates the authentication token used for this request.
     *
     * @authenticated
     *
     * @response 204
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(null, 204);
    }
}
```

### Tenant Controller with Response Fields
```php
<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @group Tenant Management
 *
 * API endpoints for managing client stores (tenants)
 */
class TenantController extends ApiController
{
    /**
     * Display a listing of the user's tenants.
     *
     * Returns all tenants associated with the authenticated user.
     *
     * @authenticated
     *
     * @responseField data{0}.id string The tenant UUID
     * @responseField data{0}.name string The tenant name
     * @responseField data{0}.slug string URL-friendly tenant identifier
     * @responseField data{0}.status string Tenant status (active, pending, error)
     * @responseField data{0}.platform_type string E-commerce platform (shopify, shopware)
     * @responseField data{0}.platform_url string The platform's base URL
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "My Shopify Store",
     *       "slug": "my-shopify-store",
     *       "status": "active",
     *       "platform_type": "shopify",
     *       "platform_url": "https://store.myshopify.com"
     *     }
     *   ]
     * }
     */
    public function index()
    {
        // Implementation...
    }

    /**
     * Store a newly created tenant in storage.
     *
     * Creates a new tenant with encrypted API credentials.
     *
     * @authenticated
     *
     * @bodyParam name string required The tenant name. Example: My Client Store
     * @bodyParam platform_type string required Platform type (shopify or shopware). Example: shopify
     * @bodyParam platform_url string required The platform's base URL. Example: https://store.myshopify.com
     * @bodyParam api_credentials object required API credentials object
     * @bodyParam api_credentials.api_key string required The API key. Example: api_key_123
     * @bodyParam api_credentials.api_secret string required The API secret. Example: secret_456
     * @bodyParam settings object optional Additional settings
     *
     * @response 201 {
     *   "data": {
     *     "id": "uuid",
     *     "name": "My Client Store",
     *     "slug": "my-client-store",
     *     "status": "active",
     *     "platform_type": "shopify",
     *     "platform_url": "https://store.myshopify.com"
     *   },
     *   "meta": {
     *     "message": "Tenant created successfully"
     *   }
     * }
     * @response 422 {
     *   "errors": [
     *     {
     *       "field": "api_credentials",
     *       "message": "Invalid API credentials"
     *     }
     *   ]
     * }
     */
    public function store(CreateTenantRequest $request)
    {
        // Implementation...
    }
}
```

### Sync Controller with Complex Endpoints
```php
<?php

namespace App\Http\Controllers\Api\V1;

/**
 * @group Catalog Synchronization
 *
 * API endpoints for triggering and monitoring product catalog synchronization
 */
class SyncController extends ApiController
{
    /**
     * Dispatch a catalog sync operation.
     *
     * Triggers an asynchronous sync job for the specified tenant.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required The tenant UUID to sync
     * @bodyParam sync_type string required Type of sync (full, incremental). Example: full
     *
     * @response 202 {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "pending",
     *     "tenant_id": "uuid"
     *   },
     *   "meta": {
     *     "message": "Sync job dispatched successfully"
     *   }
     * }
     */
    public function dispatch(Request $request)
    {
        // Implementation...
    }

    /**
     * Get sync operation status.
     *
     * Returns the current status of a sync job.
     *
     * @authenticated
     *
     * @urlParam syncLogId string required The sync log UUID
     *
     * @responseField data.job_id string The job UUID
     * @responseField data.status string Job status (pending, running, completed, failed)
     * @responseField data.progress integer Progress percentage (0-100)
     * @responseField data.products_indexed integer Number of products indexed
     * @responseField data.started_at timestamp Job start time
     * @responseField data.completed_at timestamp Job completion time (null if running)
     * @responseField data.error_message string Error message (null if no error)
     *
     * @response {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "running",
     *     "progress": 45,
     *     "products_indexed": 234,
     *     "started_at": "2026-03-15T10:00:00Z",
     *     "completed_at": null,
     *     "error_message": null
     *   }
     * }
     */
    public function status(string $syncLogId)
    {
        // Implementation...
    }
}
```

### Adding /docs Route
```php
// routes/web.php
use Illuminate\Support\Facades\Route;

// Documentation route (accessible without authentication)
Route::view('/docs', 'scribe::index')->name('docs');

// Or use Scribe's automatic route (if enabled in config)
// Scribe automatically registers /docs and /docs/postman routes
```

### Excluding Routes from Documentation
```php
// config/scribe.php
'routes' => [
    'match' => [
        'domains' => ['*'],
        'prefixes' => ['api/*'],
        'exclude' => [
            'api/v1/health',      // Health check endpoint
            'api/v1/debug',       // Debug endpoints
            'api/v1/internal/*',  // All internal routes
        ],
    ],
],
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual Postman collections | Auto-generated API docs from code | 2020+ | Documentation stays synchronized with code |
| Static markdown files | Interactive "Try it out" UI | 2021+ | Employers can test API without coding |
| Separate OpenAPI specs | DocBlock-driven generation | 2022+ | Single source of truth in code |

**Deprecated/outdated:**
- Manual API documentation in wiki/Confluence: Hard to maintain, quickly outdated
- Postman-only documentation: Requires manual updates, no integration with code
- Swagger UI with manual YAML maintenance: High overhead, easy to forget to update

**Current best practice (2026):**
- Laravel Scribe for automatic documentation generation from code
- DocBlock annotations for detailed descriptions and examples
- FormRequest classes for automatic request parameter documentation
- API Resource classes for automatic response structure documentation
- Integration with deployment pipeline for auto-regeneration

## Open Questions

1. **Should documentation be publicly accessible or require authentication?**
   - What we know: Phase 10 deployment script and CI/CD are complete
   - What's unclear: Whether /docs endpoint should be public (portfolio-friendly) or protected
   - Recommendation: Make /docs publicly accessible for portfolio demos, but add optional authentication via config for production deployments

2. **How to handle multi-tenant context in documentation examples?**
   - What we know: Most endpoints require X-Tenant-ID header
   - What's unclear: How Scribe handles custom headers in "Try it out" functionality
   - Recommendation: Investigate Scribe's support for custom headers, add @header annotations if needed

3. **Should we export Postman collection for offline testing?**
   - What we know: Scribe can generate Postman collections automatically
   - What's unclear: Whether this is required for portfolio demonstration
   - Recommendation: Enable Postman export as optional feature, document in README

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 |
| Config file | phpunit.xml (root directory) |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test --coverage --min=70` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| APIDOCS-01 | Scribe generates docs from docblocks | integration | `php artisan test tests/Feature/ScribeGenerationTest.php` | ❌ Wave 0 |
| APIDOCS-02 | /docs endpoint returns HTML | integration | `php artisan test tests/Feature/DocumentationEndpointTest.php` | ❌ Wave 0 |
| APIDOCS-03 | All endpoints documented with examples | integration | `php artisan test tests/Feature/EndpointCoverageTest.php` | ❌ Wave 0 |
| APIDOCS-04 | curl commands present in docs | integration | `php artisan test tests/Feature/CurlCommandsTest.php` | ❌ Wave 0 |
| APIDOCS-05 | Response schemas documented | integration | `php artisan test tests/Feature/ResponseSchemaTest.php` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test tests/Feature/Scribe*`
- **Per wave merge:** `php artisan test --coverage`
- **Phase gate:** Full documentation generation with all endpoints covered, /docs accessible in browser

### Wave 0 Gaps
- [ ] `tests/Feature/ScribeGenerationTest.php` — covers APIDOCS-01, APIDOCS-02
- [ ] `tests/Feature/EndpointCoverageTest.php` — covers APIDOCS-03
- [ ] `tests/Feature/CurlCommandsTest.php` — covers APIDOCS-04
- [ ] `tests/Feature/ResponseSchemaTest.php` — covers APIDOCS-05
- [ ] Scribe installation: `composer require --dev knuckleswtf/scribe` — not yet installed

## Sources

### Primary (HIGH confidence)
- Project source code analysis (routes/api.php, controllers) - Verified existing API structure
- Laravel 11 documentation - Confirmed API routing and middleware patterns
- Existing FormRequest classes - Verified validation rule patterns for auto-documentation

### Secondary (MEDIUM confidence)
- Laravel Scribe GitHub repository (https://github.com/knuckleswtf/scribe) - Package documentation and examples
- Laravel Scribe official docs (https://scribe.knuckles.wtf/) - Configuration and annotation reference
- Project STATE.md - Confirmed tech stack and authentication approach (Sanctum)

### Tertiary (LOW confidence)
- **Note:** Web search rate limit prevented accessing 2026-specific documentation
- Laravel Scribe best practices based on general knowledge of Laravel ecosystem patterns
- Assumptions about Scribe 4.x features based on typical Laravel package patterns
- **Flagged for validation:** Multi-tenant header handling in Scribe, custom authentication examples

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - Scribe is well-established but web search was blocked
- Architecture: HIGH - Based on actual project code structure and Laravel best practices
- Pitfalls: MEDIUM - Common documentation issues observed in similar projects, but 2026-specific issues unknown

**Research date:** 2026-03-15
**Valid until:** 2026-04-15 (30 days - stable domain, but web search limitation reduces confidence)
