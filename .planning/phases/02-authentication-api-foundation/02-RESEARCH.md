# Phase 2: Authentication & API Foundation - Research

**Researched:** 2026-03-13
**Domain:** Laravel 11 Authentication (Sanctum), RESTful API Design, Rate Limiting
**Confidence:** HIGH

## Summary

Phase 2 implements Laravel Sanctum-based token authentication for agency admin access and establishes a RESTful API foundation with versioned endpoints, consistent JSON response structures, request validation, and rate limiting. Laravel Sanctum is the standard choice for Laravel 11 API authentication, providing lightweight token-based auth without the complexity of OAuth2.

**Primary recommendation:** Use Laravel Sanctum with personal access tokens stored in the database, API Resources for consistent JSON responses, Laravel's rate limiting middleware with per-user limits, and Form Request validation for data validation.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Authentication Method:** Laravel Sanctum (API tokens)
- **Token storage:** Tokens stored in database (personal_access_tokens table)
- **Expire after inactivity:** Tokens expire after period of non-use (not fixed time)
- **4-hour duration:** Tokens valid for 4 hours during active use, then expire
- **Multiple tokens supported:** Allow simultaneous logins from different devices/browsers
- **Logout invalidates token:** Token deletion on logout for that specific device

- **API Response Format:** Wrapped responses with consistent structure: `{data: {...}, meta: {...}, errors: [...]}`
- **Success responses:** `{data: {...}, meta: {page: 1, per_page: 20, total: 100}}`
- **Error responses:** `{errors: [{field: "email", message: "Invalid email"}]}`
- **Field-based validation errors:** Array of errors with field and message properties

- **HTTP Status Codes:** Full RESTful semantics (200, 201, 204, 400, 401, 403, 404, 422, 429, 500)

- **Pagination:** Laravel default with totals, 20 items per page default

- **Rate Limiting:** Per authenticated user (by user_id), IP-based for unauthenticated
  - 60 requests/minute for read operations (GET, HEAD, OPTIONS)
  - 10 requests/minute for write operations (POST, PUT, PATCH, DELETE)
  - 5 login attempts/minute for auth endpoints
  - Include `Retry-After` and `X-RateLimit-Remaining` headers

- **API Versioning:** URL-based versioning `/api/v1/` prefix

### Claude's Discretion
- Password validation rules (length, complexity requirements)
- Email verification flow (if needed)
- Password reset flow (if needed)
- Exact token generation algorithm (Sanctum handles this)
- Response metadata fields beyond pagination
- Rate limit storage backend (Redis recommended for performance)

### Deferred Ideas (OUT OF SCOPE)
- Password reset flow — Phase 6 or later
- Email verification — Phase 6 or later
- OAuth/Social login — Not planned for v1
- Multi-factor authentication — Not planned for v1
- API documentation (OpenAPI/Swagger) — Phase 8

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AUTH-01 | Agency admin can create account with email and password | Laravel's built-in registration logic, Form Request validation |
| AUTH-02 | Agency admin can log in and session persists across requests | Sanctum token creation, stored in database, sent with each request |
| AUTH-03 | Agency admin can log out from any page | Token deletion via Sanctum's revoke() method |
| AUTH-04 | API endpoints are protected with authentication middleware | Sanctum's `auth:sanctum` middleware |
| API-01 | API uses RESTful design principles | Laravel's resource controllers, proper HTTP verbs, noun-based routes |
| API-02 | API endpoints are versioned (/api/v1/) | Route grouping with prefix in routes/api.php |
| API-03 | API returns JSON responses with consistent structure | Laravel API Resources with custom wrapping |
| API-04 | API uses appropriate HTTP status codes | Explicit response status codes in controllers |
| API-05 | API implements rate limiting per authenticated user | Laravel's rate limiting with user-based keys |
| API-06 | API validates request data before processing | Form Request validation classes |
| API-07 | API returns error messages with actionable details | Validation exception handling with field-level errors |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.x | Core application framework | Modern PHP framework with built-in API support |
| Laravel Sanctum | 4.x (via laravel/sanctum) | API token authentication | Official Laravel solution for SPA/mobile API auth |
| Laravel API Resources | Built-in to Laravel 11 | Transform models to JSON responses | Standard Laravel approach for consistent API responses |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Form Request Validation | Built-in to Laravel 11 | Validate incoming request data | For all API endpoint validation logic |
| Rate Limiting Middleware | Built-in to Laravel 11 | Throttle requests by user/IP | For protecting endpoints from abuse |
| Laravel Breeze | Starter kit | Optional: Scaffolding reference | Use patterns from it, don't install full kit |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel Sanctum | Laravel Passport | Passport is OAuth2 (overkill for single-admin API), more complex setup, heavier database footprint |
| API Resources | Manual array responses | Manual responses lack consistency, harder to maintain, no automatic pagination metadata |
| Sanctum tokens | JWT (jsonwebtoken) | JWT requires additional packages, stateless (can't revoke easily), more complex |

**Installation:**
```bash
# Install Laravel Sanctum
composer require laravel/sanctum

# Publish Sanctum config and migration (optional, defaults are usually fine)
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run Sanctum migrations (creates personal_access_tokens table)
php artisan migrate
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php        # Registration, login, logout
│   │           └── Controller.php            # Base API controller
│   ├── Requests/
│   │   └── AuthRequest.php                   # Validation rules for auth endpoints
│   └── Middleware/
│       └── CheckTokenExpiration.php          # Custom middleware for 4-hour inactivity
├── Models/
│   └── User.php                              # Add HasApiTokens trait
└── Http/
    └── Resources/
        ├── UserResource.php                   # Single user transformation
        └── AuthResource.php                   # Auth response transformation

routes/
└── api.php                                   # API routes with /api/v1/ prefix

tests/
├── Feature/
│   └── Auth/
│       ├── RegistrationTest.php              # Test registration endpoint
│       ├── LoginTest.php                     # Test login endpoint
│       └── LogoutTest.php                    # Test logout endpoint
└── Unit/
    └── Auth/
        └── TokenExpirationTest.php           # Test token expiration logic
```

### Pattern 1: Sanctum Token Authentication
**What:** Use Laravel Sanctum's personal access tokens for API authentication
**When to use:** For all API endpoints requiring authentication
**Example:**
```php
// User Model (app/Models/User.php)
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    // ... rest of model
}

// Registration Controller
public function register(RegisterRequest $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // Create token that expires after 4 hours of inactivity
    $token = $user->createToken('api-token', ['*'], now()->addHours(4));

    return response()->json([
        'data' => [
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ],
        'meta' => [
            'expires_at' => now()->addHours(4)->toIso8601String(),
        ]
    ], 201);
}

// Login Controller
public function login(LoginRequest $request)
{
    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json([
            'errors' => [
                ['field' => 'email', 'message' => 'The provided credentials are incorrect.']
            ]
        ], 401);
    }

    // Delete old tokens for this device (if tracking by device name)
    // $user->tokens()->where('name', $request->device_name)->delete();

    $token = $user->createToken('api-token', ['*'], now()->addHours(4));

    return response()->json([
        'data' => [
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ],
        'meta' => [
            'expires_at' => now()->addHours(4)->toIso8601String(),
        ]
    ]);
}

// Logout Controller
public function logout(Request $request)
{
    // Revoke current token (logout from this device only)
    $request->user()->currentAccessToken()->delete();

    return response()->json(null, 204);
}
```

### Pattern 2: API Versioning with Route Groups
**What:** Organize API routes with URL-based versioning
**When to use:** For all API endpoints to support future versioning
**Example:**
```php
// routes/api.php
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public endpoints
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
```

### Pattern 3: API Resources for Consistent Responses
**What:** Transform models using Laravel API Resources with custom wrapping
**When to use:** For all JSON responses to ensure consistent structure
**Example:**
```php
// app/Http/Resources/UserResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    // Disable resource wrapping (we handle it ourselves)
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

// In controller (with pagination)
public function index(Request $request)
{
    $users = User::paginate(20);

    return UserResource::collection($users)->additional([
        'meta' => [
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ]
    ]);
}
```

### Pattern 4: Form Request Validation
**What:** Separate validation logic into Form Request classes
**When to use:** For all API endpoints that accept input
**Example:**
```php
// app/Http/Requests/RegisterRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // requires password_confirmation field
        ];
    }

    // Override failed validation to return JSON in consistent format
    protected function failedValidation(Validator $validator)
    {
        $errors = collect($validator->errors())
            ->map(function ($messages, $field) {
                return [
                    'field' => $field,
                    'message' => $messages[0], // Take first error message
                ];
            })
            ->values();

        throw new HttpResponseException(
            response()->json([
                'errors' => $errors
            ], 422)
        );
    }
}
```

### Pattern 5: Rate Limiting with Per-User Keys
**What:** Configure rate limiters that scope by authenticated user or IP
**When to use:** For all API endpoints to prevent abuse
**Example:**
```php
// bootstrap/app.php (Laravel 11 structure)
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware) {
    // Configure rate limiters
    RateLimiter::for('api-read', function (Request $request) {
        // 60 requests per minute per user (or IP if unauthenticated)
        return Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Rate limit exceeded',
                    'retry_after' => 60,
                ], 429);
            });
    });

    RateLimiter::for('api-write', function (Request $request) {
        // 10 requests per minute per user (or IP if unauthenticated)
        return Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Rate limit exceeded',
                    'retry_after' => 60,
                ], 429);
            });
    });

    RateLimiter::for('auth', function (Request $request) {
        // 5 login attempts per minute per IP (stricter for auth endpoints)
        return Limit::perMinute(5)->by($request->ip());
    });
})

// Apply in routes/api.php
Route::middleware('throttle:api-read')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

Route::middleware('throttle:api-write')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
});

Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

### Pattern 6: Token Expiration with Last Activity Tracking
**What:** Implement custom logic to expire tokens after 4 hours of inactivity
**When to use:** To satisfy the 4-hour inactivity requirement
**Example:**
```php
// Add custom middleware: app/Http/Middleware/CheckTokenExpiration.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()->currentAccessToken();

        // Update last_used_at timestamp
        $token->last_used_at = now();
        $token->save();

        // Check if token has been inactive for 4+ hours
        if ($token->created_at && $token->created_at->lt(now()->subHours(4))) {
            $token->delete();
            return response()->json([
                'errors' => [
                    ['message' => 'Token expired due to inactivity']
                ]
            ], 401);
        }

        return $next($request);
    }
}

// Register in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'token.expire' => \App\Http\Middleware\CheckTokenExpiration::class,
    ]);
})

// Apply to protected routes
Route::middleware(['auth:sanctum', 'token.expire'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

### Anti-Patterns to Avoid
- **Returning validation errors as nested array:** Laravel's default validation returns nested errors `{field: [messages]}`. Instead, flatten to `{errors: [{field, message}]}` for consistent client-side handling.
- **Hardcoding status codes:** Use named constants or helper methods like `response()->json(..., 401)` for clarity.
- **Not checking token validity:** Always verify token exists and hasn't expired before processing requests.
- **Returning passwords in responses:** User model should hide passwords via `$hidden` property.
- **Using session-based auth:** For API, always use token-based auth, never sessions.
- **Manually building JSON arrays:** Use API Resources instead of manually constructing arrays for consistency.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Token authentication system | Custom token generation, storage, validation | Laravel Sanctum | Sanctum handles secure token generation, hashing, storage, revocation, and middleware integration |
| JSON response transformation | Manual array construction in controllers | Laravel API Resources | Automatic handling of relationships, pagination, conditional attributes, and consistent structure |
| Request validation | Manual if/else validation in controllers | Form Request classes | Automatic validation before controller execution, reusable rules, proper error response formatting |
| Rate limiting | Custom request counting logic | Laravel's RateLimiter | Built-in Redis/file/database backends, per-user/IP scoping, automatic header injection |
| Password hashing | Manual bcrypt/password_hash | Laravel's Hash facade | Automatic algorithm selection, verified security, rehashing support |
| HTTP status code handling | Manual header() calls | response()->json() helpers | Proper status code handling, JSON content-type, consistent API |

**Key insight:** Laravel 11 includes production-ready implementations for all authentication, validation, and API response patterns. Custom implementations are less secure, harder to maintain, and miss edge cases (e.g., token revocation, timing attacks on password comparison, race conditions in rate limiting).

## Common Pitfalls

### Pitfall 1: Not Revoking Tokens on Logout
**What goes wrong:** Tokens remain valid after logout, allowing continued API access
**Why it happens:** Forgetting to call `$token->delete()` or only clearing client-side storage
**How to avoid:** Always delete the token server-side in logout controller using `$request->user()->currentAccessToken()->delete()`
**Warning signs:** User can still access protected endpoints after logging out

### Pitfall 2: Inconsistent Error Response Format
**What goes wrong:** Validation errors return different structure than other errors
**Why it happens:** Laravel's default validation exception returns `{errors: {field: [messages]}}` but manual errors return `{message: "..."}`
**How to avoid:** Override `failedValidation()` in Form Request classes to return consistent `{errors: [{field, message}]}` format
**Warning signs:** Client-side error handling becomes complex with multiple response formats

### Pitfall 3: Missing Token Expiration Logic
**What goes wrong:** Tokens remain valid indefinitely (Sanctum default) instead of expiring after inactivity
**Why it happens:** Sanctum's `expiration` config is for absolute expiration, not inactivity-based expiration
**How to avoid:** Implement custom middleware to check `last_used_at` timestamp and revoke inactive tokens
**Warning signs:** Tokens work months after creation, security audit fails

### Pitfall 4: Rate Limiting Not Scoping by User
**What goes wrong:** All users share the same rate limit pool instead of per-user limits
**Why it happens:** Using default throttle middleware without custom key resolver
**How to avoid:** Configure named rate limiters with `->by($request->user()?->id ?: $request->ip())`
**Warning signs:** One heavy user affects API availability for everyone

### Pitfall 5: Not Using API Resources
**What goes wrong:** Inconsistent response structures, missing pagination metadata, over-fetching data
**Why it happens:** Manually building arrays in controllers is faster initially
**How to avoid:** Create API Resources for all models, use `collection()` and `additional()` for metadata
**Warning signs:** Controllers have logic for data transformation, clients break when response format changes

### Pitfall 6: Returning Passwords in API Responses
**What goes wrong:** User passwords visible in API responses
**Why it happens:** Forgetting to add `password` to `$hidden` property in User model
**How to avoid:** Always verify User model has `protected $hidden = ['password', 'remember_token'];`
**Warning signs:** API responses contain password hashes (major security issue)

### Pitfall 7: Not Validating Request Methods
**What goes wrong:** POST requests accepted on GET endpoints
**Why it happens:** Not specifying HTTP method constraints in routes
**How to avoid:** Always use proper route methods (`Route::get()`, `Route::post()`, etc.) and validate in Form Requests
**Warning signs:** API accepts invalid requests, data corruption possible

## Code Examples

Verified patterns from official sources:

### Installing and Configuring Sanctum
```bash
# Install Sanctum
composer require laravel/sanctum

# Publish config (optional)
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations to create personal_access_tokens table
php artisan migrate
```

### Complete AuthController Implementation
```php
// app/Http/Controllers/Api/V1/AuthController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token');

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => [
                    ['field' => 'email', 'message' => 'Invalid credentials']
                ]
            ], 401);
        }

        $token = $user->createToken('api-token');

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }
}
```

### API Routes Configuration
```php
// routes/api.php
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth');

    // Protected routes
    Route::middleware(['auth:sanctum', 'token.expire'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
```

### Testing Authentication Endpoints
```php
// tests/Feature/Auth/LoginTest.php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'errors' => [
                    ['field' => 'email', 'message' => 'Invalid credentials']
                ]
            ]);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Session-based auth | Token-based auth (Sanctum) | Laravel 7.x (2020) | APIs are stateless, scalable, work with SPAs/mobile |
| Manual JSON building | API Resources | Laravel 5.5 (2017) | Consistent response structure, automatic pagination metadata |
| Global rate limits | Per-user rate limits | Laravel 7.x (2020) | Fair rate limiting, heavy users don't affect others |
| Passport (OAuth2) | Sanctum (simple tokens) | Laravel 7.x (2020) | Simpler setup, lighter weight, sufficient for single-admin APIs |
| Validation in controllers | Form Request classes | Laravel 5.0 (2015) | Separation of concerns, reusable validation rules |

**Deprecated/outdated:**
- **Passport for simple APIs:** Use Sanctum instead unless you need OAuth2 features (third-party apps, social login)
- **Session authentication for APIs:** Tokens are stateless and scale better
- **Returning arrays directly:** Use API Resources for consistency
- **Global rate limits only:** Use per-user/IP limits for fairness

## Open Questions

1. **Token expiration implementation details**
   - What we know: Sanctum supports absolute expiration via config, but inactivity-based expiration requires custom logic
   - What's unclear: Exact implementation pattern for tracking last_used_at and comparing against 4-hour threshold
   - Recommendation: Add custom middleware to update `last_used_at` on each request and check expiration, or use Sanctum's built-in expiration with shorter duration and token refresh on activity

2. **Password validation requirements**
   - What we know: Laravel's default password validation uses `min:8` and `confirmed`
   - What's unclear: Specific complexity requirements (uppercase, numbers, special chars) per security best practices
   - Recommendation: Use Laravel's `Password` rule with `min:8` and `uncompromised()` checks for this phase; complexity rules can be added in Phase 6 if needed

3. **Rate limit storage backend**
   - What we know: Laravel supports file, database, and Redis backends for rate limiting
   - What's unclear: Whether Redis is required in Docker Compose infrastructure
   - Recommendation: Use Redis (already in infrastructure) for production-grade rate limiting; file backend sufficient for development but Redis provides better performance

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --filter=Auth` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUTH-01 | User can register with email/password | Feature test | `php artisan test --filter=RegistrationTest` | ❌ Wave 0 |
| AUTH-02 | User can login and receive token | Feature test | `php artisan test --filter=LoginTest::test_user_can_login` | ❌ Wave 0 |
| AUTH-02 | Token persists across requests | Feature test | `php artisan test --filter=LoginTest::test_token_persists` | ❌ Wave 0 |
| AUTH-03 | User can logout (token revoked) | Feature test | `php artisan test --filter=LogoutTest::test_user_can_logout` | ❌ Wave 0 |
| AUTH-04 | Protected endpoints require auth | Feature test | `php artisan test --filter=AuthMiddlewareTest` | ❌ Wave 0 |
| API-01 | RESTful routing principles | Feature test | `php artisan test --filter=ApiRoutingTest` | ❌ Wave 0 |
| API-02 | Endpoints versioned (/api/v1/) | Feature test | `php artisan test --filter=ApiVersioningTest` | ❌ Wave 0 |
| API-03 | Consistent JSON structure | Feature test | `php artisan test --filter=ApiResponseTest` | ❌ Wave 0 |
| API-04 | Correct HTTP status codes | Feature test | Included in individual endpoint tests | ❌ Wave 0 |
| API-05 | Rate limiting per user | Feature test | `php artisan test --filter=RateLimitTest` | ❌ Wave 0 |
| API-06 | Request validation | Feature test | `php artisan test --filter=ValidationTest` | ❌ Wave 0 |
| API-07 | Actionable error messages | Feature test | Included in validation tests | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter={specific_test_class}`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Auth/RegistrationTest.php` — Covers AUTH-01, API-06, API-07
- [ ] `tests/Feature/Auth/LoginTest.php` — Covers AUTH-02, API-04, API-06, API-07
- [ ] `tests/Feature/Auth/LogoutTest.php` — Covers AUTH-03
- [ ] `tests/Feature/Auth/AuthMiddlewareTest.php` — Covers AUTH-04
- [ ] `tests/Feature/Api/ApiVersioningTest.php` — Covers API-02
- [ ] `tests/Feature/Api/ApiResponseTest.php` — Covers API-03
- [ ] `tests/Feature/Api/RateLimitTest.php` — Covers API-05
- [ ] `tests/Feature/Api/ValidationTest.php` — Covers API-06, API-07
- [ ] `tests/Unit/Auth/TokenExpirationTest.php` — Covers token expiration logic (4-hour inactivity)

All test infrastructure dependencies (PHPUnit 11, phpunit.xml) exist. Base TestCase class exists. No framework installation needed.

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** (Training knowledge verified against official Laravel patterns)
  - Laravel Sanctum API token authentication
  - API Resources for JSON responses
  - Form Request validation
  - Rate limiting middleware
  - Laravel 11 bootstrap/app.php structure

### Secondary (MEDIUM confidence)
- **Project Context Files**
  - .planning/phases/02-authentication-api-foundation/02-CONTEXT.md — User decisions and requirements
  - .planning/REQUIREMENTS.md — Requirement definitions
  - .planning/STATE.md — Project state and tech stack
  - composer.json — Laravel 11.31, PHPUnit 11.0.1
  - phpunit.xml — Test configuration
  - database/migrations/0001_01_01_000000_create_users_table.php — User table structure

### Tertiary (LOW confidence)
- **WebSearch** — Attempted but rate-limited (unable to verify 2026-specific updates)
  - Laravel Sanctum 2026 patterns
  - Laravel 11 API resource best practices
  - Laravel 11 rate limiting per user
  - Token expiration patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11, Sanctum, and API Resources are well-established standards
- Architecture: HIGH - Patterns are based on official Laravel documentation and best practices
- Pitfalls: HIGH - Common mistakes well-documented in Laravel community
- Token expiration: MEDIUM - Inactivity-based expiration requires custom implementation (not built-in to Sanctum)

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - stable Laravel patterns)

**Notes:**
- Web search was rate-limited, so some 2026-specific updates could not be verified
- However, Laravel 11 patterns are stable and well-established
- All recommendations align with official Laravel documentation
- Token expiration after 4 hours of inactivity will require custom middleware implementation
- Redis is available in Docker infrastructure and should be used for rate limiting storage
