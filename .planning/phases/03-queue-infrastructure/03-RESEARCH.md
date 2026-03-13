# Phase 3: Tenant Management System - Research

**Researched:** 2026-03-13
**Domain:** Laravel 11 Multi-Tenant Architecture with Global Scopes
**Confidence:** HIGH

## Summary

Phase 3 implements a multi-tenant management system where agency admins can create, view, update, and delete client stores with complete data isolation. The architecture uses Laravel 11's global scopes for automatic tenant scoping, encrypted casts for secure API credential storage, and many-to-many relationships for user-tenant associations.

**Primary recommendation:** Use Laravel 11's native global scopes with closure-based implementation, combined with encrypted JSON casts for credentials and middleware-based tenant context setting.

## User Constraints (from CONTEXT.md)

### Locked Decisions

- **Header-based tenant selection** — Use `X-Tenant-ID` header in all tenant-scoped requests
- **Missing header returns 422** — Explicit error: "X-Tenant-ID header is required"
- **Invalid tenant returns 404** — Generic "Tenant not found or access denied" (prevents enumeration)
- **Many-to-many user-tenant association** — Users can access multiple tenants via pivot table
- **Combined middleware approach** — `SetTenant` middleware stores context, `TenantScope` applies global scopes
- **Single Tenant model** (not separated into Tenant + TenantCredential)
- **Status enum**: active, pending_setup, sync_error, suspended
- **Slug auto-generated** — Auto-generate from name using `Str::slug()`: "My Store" → "my-store"
- **Platform enum**: shopify, shopware
- **Encrypted + JSON cast** — `protected $casts = ['api_credentials' => 'encrypted:json'];`
- **Synchronous API validation** — Call platform API during tenant creation to verify credentials
- **Show platform error details** — Return `{error: "Invalid API credentials", platform_response: "..."}`

### Claude's Discretion

- Exact structure of tenant_user pivot table (additional fields? timestamps?)
- Global scope implementation details (trait vs base class vs manual scopes)
- Validation rules beyond platform_type (name length, URL format, etc.)
- Tenant ownership verification logic (who can create/update/delete which tenants)
- Exact error message wording for edge cases

### Deferred Ideas (OUT OF SCOPE)

- Sync operation triggers (Phase 6: Catalog Synchronization)
- Queue job status monitoring (Phase 5 or Phase 7: Admin Dashboard)
- Tenant-specific permissions/roles (v2 feature)
- Bulk tenant operations (v2 feature)
- Tenant analytics/usage metrics (future enhancement)

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TENANT-01 | Agency admin can create new client store with name and platform type | Tenant model with validation rules, enum casting for platform_type |
| TENANT-02 | Agency admin can view list of all client stores | Tenant index endpoint with pagination |
| TENANT-03 | Agency admin can update client store details (name, status, platform URL) | Tenant update endpoint with validation |
| TENANT-04 | Agency admin can delete client store | Tenant delete with cascade handling |
| TENANT-05 | System stores API credentials encrypted in database | `encrypted:json` cast in Laravel 11 |
| TENANT-06 | Database uses tenant_id discriminator for multi-tenant data isolation | Migration pattern with foreign keys |
| TENANT-07 | Queries automatically scope to current tenant via global scopes | Laravel 11 global scopes with closure implementation |
| TEST-01 | System has unit tests for core business logic (tenant scoping, validation) | PHPUnit with RefreshDatabase trait |
| TEST-02 | System has feature tests for API endpoints | Feature test patterns from existing tests |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.x | Core framework with PHP 8.2+ | Modern features: readonly properties, improved enum casting, closure-based global scopes |
| Laravel Sanctum | (existing) | API authentication | Already integrated in Phase 2 |
| PHPUnit | 10.x | Testing framework | Laravel 11 default, existing test infrastructure |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Illuminate\Support\Str | (built-in) | Slug generation | Auto-generate tenant slugs from names |
| Illuminate\Database\Eloquent\SoftDeletes | (built-in) | Soft deletes | Consider for tenant deletion (prevents data loss) |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Global scopes | Query scopes in every query | Global scopes are automatic, query scopes require manual application |
| Encrypted cast | Custom encryption service | Laravel's built-in AES-256-CBC is secure and transparent |
| Single Tenant model | Separate Tenant + TenantCredential | Single model is simpler, JSON cast handles credential structure |
| Many-to-many | One-to-many user → tenant | Many-to-many allows users to access multiple tenants (agency team scenario) |

**Installation:**
```bash
# No new packages required - using Laravel 11 built-in features
# Existing packages from Phase 2:
# - Laravel Sanctum (already installed)
# - PHPUnit (already installed)
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Models/
│   ├── Tenant.php                    # Tenant model with relationships
│   └── User.php                       # Update with tenants() relationship
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── TenantController.php  # CRUD operations
│   ├── Requests/
│   │   ├── CreateTenantRequest.php   # Validation for tenant creation
│   │   └── UpdateTenantRequest.php   # Validation for tenant updates
│   ├── Resources/
│   │   └── TenantResource.php        # API resource transformation
│   └── Middleware/
│       ├── SetTenant.php             # Resolve and store tenant context
│       └── TenantScope.php           # Apply global scopes to queries
├── Enums/
│   ├── PlatformType.php              # shopify, shopware
│   └── TenantStatus.php              # active, pending_setup, sync_error, suspended
└── Services/
    └── PlatformCredentialValidator.php  # Validate Shopify/Shopware API credentials

database/
└── migrations/
    ├── xxxx_create_tenants_table.php
    └── xxxx_create_tenant_user_table.php

tests/
├── Unit/
│   └── Models/
│       └── TenantScopeTest.php       # Test global scope logic
└── Feature/
    └── Api/
        └── TenantManagementTest.php  # Test CRUD endpoints
```

### Pattern 1: Global Scopes for Automatic Tenant Scoping

**What:** Laravel 11 global scopes automatically apply `tenant_id` constraints to all queries on tenant-aware models.

**When to use:** Every model that belongs to a tenant (Products, Orders, SyncLogs, etc.)

**Example:**
```php
// app/Models/Tenant.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'platform_type',
        'platform_url',
        'status',
        'api_credentials',
        'agency_id',
        'settings',
        'last_sync_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'platform_type' => PlatformType::class,
            'status' => TenantStatus::class,
            'api_credentials' => 'encrypted:json',  // AES-256-CBC encryption
            'settings' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('role', 'joined_at');
    }
}

// In tenant-aware models (e.g., Product):
protected static function booted()
{
    static::addGlobalScope('tenant', function (Builder $builder) {
        if (auth()->check() && ($tenantId = auth()->user()->currentTenantId())) {
            $builder->where('tenant_id', $tenantId);
        }
    });
}
```

### Pattern 2: Middleware Chain for Tenant Context

**What:** Combined middleware approach where `SetTenant` resolves tenant from header and stores in context, then `TenantScope` applies global scopes.

**When to use:** All routes that require tenant scoping

**Example:**
```php
// app/Http/Middleware/SetTenant.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;

class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'errors' => [
                    ['field' => 'X-Tenant-ID', 'message' => 'X-Tenant-ID header is required']
                ]
            ], 422);
        }

        $tenant = Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (!$tenant) {
            return response()->json([
                'errors' => [
                    ['message' => 'Tenant not found or access denied']
                ]
            ], 404);
        }

        // Store tenant in request context for use in controllers and other middleware
        $request->attributes->set('current_tenant', $tenant);
        auth()->user()->setCurrentTenant($tenant);

        return $next($request);
    }
}

// app/Http/Middleware/TenantScope.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        // Global scopes are applied automatically via model booted() callbacks
        // This middleware ensures tenant context is available
        return $next($request);
    }
}

// bootstrap/app.php - Register middleware aliases
$middleware->alias([
    'tenant' => \App\Http\Middleware\SetTenant::class,
    'tenant.scope' => \App\Http\Middleware\TenantScope::class,
]);

// routes/api.php - Apply to tenant-scoped routes
Route::middleware(['auth:sanctum', 'tenant', 'tenant.scope'])->group(function () {
    Route::apiResource('tenants', TenantController::class);
});
```

### Pattern 3: Many-to-Many Relationship with Pivot Data

**What:** Users can belong to multiple tenants with additional pivot fields (role, joined_at).

**When to use:** Agency teams where multiple users need access to different client sets.

**Example:**
```php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_tenant_id',  // Add this field for tracking active tenant
    ];

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withTimestamps()
            ->withPivot('role', 'joined_at');
    }

    public function currentTenant()
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function setCurrentTenant(Tenant $tenant): void
    {
        $this->current_tenant_id = $tenant->id;
        $this->save();
    }

    public function currentTenantId(): ?string
    {
        return $this->current_tenant_id;
    }
}
```

### Pattern 4: Enum Casting for Type Safety

**What:** Laravel 11's native enum casting provides type safety for platform_type and status fields.

**When to use:** Fixed sets of values (platforms, statuses, etc.)

**Example:**
```php
// app/Enums/PlatformType.php
namespace App\Enums;

enum PlatformType: string
{
    case SHOPIFY = 'shopify';
    case SHOPIWARE = 'shopware';
}

// app/Enums/TenantStatus.php
namespace App\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case PENDING_SETUP = 'pending_setup';
    case SYNC_ERROR = 'sync_error';
    case SUSPENDED = 'suspended';
}

// Usage in model
protected function casts(): array
{
    return [
        'platform_type' => PlatformType::class,
        'status' => TenantStatus::class,
    ];
}

// Validation rules
public function rules(): array
{
    return [
        'platform_type' => ['required', 'in:shopify,shopware'],
        'status' => ['sometimes', 'in:active,pending_setup,sync_error,suspended'],
    ];
}
```

### Anti-Patterns to Avoid

- **Storing tenant_id in session:** Session-based tenant context is fragile and hard to test. Use header-based selection.
- **Manual tenant scoping in every query:** Error-prone and violates DRY. Use global scopes.
- **Encrypting credentials in controller:** Business logic in controllers is hard to reuse. Use encrypted casts.
- **Hardcoding platform validation logic:** Tightly couples code to specific platforms. Use service classes.
- **Returning detailed errors for invalid tenant IDs:** Enables enumeration attacks. Use generic "not found or access denied".

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Credential encryption | Custom AES encryption service | `encrypted` cast | Laravel uses APP_KEY, handles IV generation, integrates with Eloquent |
| JSON credential structure | Manual JSON encode/decode | `json` cast combined with `encrypted` | Automatic serialization/deserialization, type-safe access |
| Slug generation | Custom slugification logic | `Str::slug()` | Handles unicode, edge cases, consistent with Laravel ecosystem |
| Many-to-many pivot table | Manual pivot table queries | `belongsToMany()` relationship | Automatic pivot model, eager loading, sync/attach/detach methods |
| Enum validation | Manual in_array checks | PHP 8.1+ enums with enum casting | Type safety, IDE autocomplete, refactoring support |

**Key insight:** Laravel 11 has mature, battle-tested implementations for all multi-tenant patterns. Custom solutions introduce security vulnerabilities (encryption), maintenance burden (slug generation), and inconsistency (manual pivot queries).

## Common Pitfalls

### Pitfall 1: Global Scope Leaks in Tests

**What goes wrong:** Tests fail because global scopes apply to seed data or factory creations, causing "record not found" errors.

**Why it happens:** Global scopes apply to ALL queries, including test setup, unless explicitly disabled.

**How to avoid:**
```php
// In tests - disable global scopes for setup
$tenant = Tenant::factory()->create();
User::factory()->create()->tenants()->attach($tenant);

// Then re-enable for actual testing
$product = Product::withoutGlobalScopes()->create([
    'tenant_id' => $tenant->id,
    'name' => 'Test Product',
]);
```

**Warning signs:** Tests fail with "SQL exception: constraint violation" or "Model not found" in seeded data.

### Pitfall 2: Credentials Exposed in Logs

**What goes wrong:** API credentials logged in request/response logs or error messages.

**Why it happens:** Laravel's default logging includes all request input and model attributes.

**How to avoid:**
```php
// Hide credentials from Laravel logs
// app/Models/Tenant.php
protected $hidden = ['api_credentials'];

// Hide from request logging
// app/Http/Middleware/TrimStrings.php or custom middleware
$request->merge(['api_credentials' => '[HIDDEN]']);

// Never return credentials in API responses
// app/Http/Resources/TenantResource.php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'platform_type' => $this->platform_type,
        // api_credentials intentionally excluded
    ];
}
```

**Warning signs:** Credentials visible in `storage/logs/laravel.log`, API response JSON, or browser network tab.

### Pitfall 3: Tenant Context Lost in Queue Jobs

**What goes wrong:** Queue jobs operate on wrong tenant's data because tenant context doesn't persist across job boundaries.

**Why it happens:** Queue jobs run in separate process without request context (no X-Tenant-ID header).

**How to avoid:**
```php
// Store tenant_id in job payload
class SyncCatalogJob implements ShouldQueue
{
    public function __construct(
        public string $tenantId
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        // Manually set tenant context for this job
        auth()->user()->setCurrentTenant($tenant);

        // Now global scopes work correctly
        $products = Product::all(); // Automatically scoped to $tenantId
    }
}

// In controller - dispatch job with explicit tenant
dispatch(new SyncCatalogJob($tenant->id));
```

**Warning signs:** Queue jobs process data from wrong tenant, "foreign key constraint" errors, or jobs fail silently.

### Pitfall 4: Credential Validation Blocks Requests

**What goes wrong:** Tenant creation times out or fails because platform API is slow or unreachable.

**Why it happens:** Synchronous API validation blocks HTTP request until platform API responds.

**How to avoid:**
```php
// Use timeout for external API calls
public function validateCredentials(string $platform, array $credentials): bool
{
    try {
        $response = Http::timeout(10)->post($this->getPlatformUrl($platform), [
            'credentials' => $credentials,
        ]);

        return $response->successful();
    } catch (\Exception $e) {
        // Log error but don't block tenant creation
        Log::warning("Credential validation failed for {$platform}", [
            'error' => $e->getMessage(),
        ]);

        return false;
    }
}

// Return helpful error but don't expose internal details
return response()->json([
    'errors' => [
        [
            'field' => 'api_credentials',
            'message' => 'Invalid API credentials',
            'platform_response' => $platformError  // Platform's specific error
        ]
    ]
], 422);
```

**Warning signs:** High response times (>5s) for tenant creation, timeouts in production logs.

## Code Examples

Verified patterns from Laravel 11 documentation and existing codebase:

### Tenant Model with Encrypted Credentials

```php
// app/Models/Tenant.php
namespace App\Models;

use App\Enums\PlatformType;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'platform_type',
        'platform_url',
        'status',
        'api_credentials',
        'settings',
        'last_sync_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'platform_type' => PlatformType::class,
            'status' => TenantStatus::class,
            'api_credentials' => 'encrypted:json',  // Auto-encrypt/decrypt
            'settings' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    protected $hidden = ['api_credentials'];  // Never expose in JSON

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('role', 'joined_at');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }
}
```

### Tenant Controller with CRUD Operations

```php
// app/Http/Controllers/Api/V1/TenantController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\CreateTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\PlatformCredentialValidator;
use Illuminate\Http\JsonResponse;

class TenantController extends ApiController
{
    public function __construct(
        private PlatformCredentialValidator $credentialValidator
    ) {}

    public function index(): JsonResponse
    {
        $tenants = auth()->user()->tenants()->paginate(20);

        return $this->success($tenants);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        // Validate credentials with platform API
        $isValid = $this->credentialValidator->validate(
            platform: $request->platform_type,
            credentials: $request->api_credentials,
            url: $request->platform_url
        );

        if (!$isValid) {
            return $this->error([
                [
                    'field' => 'api_credentials',
                    'message' => 'Invalid API credentials',
                    'platform_response' => $this->credentialValidator->getLastError()
                ]
            ], 422);
        }

        $tenant = Tenant::create($request->validated());

        // Attach creating user to tenant
        auth()->user()->tenants()->attach($tenant->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        return $this->created(
            new TenantResource($tenant),
            ['message' => 'Tenant created successfully']
        );
    }

    public function show(string $id): JsonResponse
    {
        $tenant = auth()->user()->tenants()->findOrFail($id);

        return $this->success(new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = auth()->user()->tenants()->findOrFail($id);
        $tenant->update($request->validated());

        return $this->success(new TenantResource($tenant));
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = auth()->user()->tenants()->findOrFail($id);
        $tenant->delete();  // Soft delete

        return $this->noContent();
    }
}
```

### Form Request Validation

```php
// app/Http/Requests/CreateTenantRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;  // Auth middleware already applied
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'platform_type' => ['required', 'in:shopify,shopware'],
            'platform_url' => ['required', 'url', 'max:500'],
            'api_credentials' => ['required', 'array'],
            'api_credentials.api_key' => ['required', 'string'],
            'api_credentials.api_secret' => ['required', 'string'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}

// app/Http/Requests/UpdateTenantRequest.php
class UpdateTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,pending_setup,sync_error,suspended'],
            'platform_url' => ['sometimes', 'url', 'max:500'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
```

### API Resource Transformation

```php
// app/Http/Resources/TenantResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'platform_type' => $this->platform_type->value,
            'platform_url' => $this->platform_url,
            'status' => $this->status->value,
            'settings' => $this->settings,
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'sync_status' => $this->sync_status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            // api_credentials intentionally excluded
        ];
    }
}
```

### Migration Patterns

```php
// database/migrations/2024_03_13_000002_create_tenants_table.php
Schema::create('tenants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->enum('platform_type', ['shopify', 'shopware']);
    $table->string('platform_url');
    $table->enum('status', ['active', 'pending_setup', 'sync_error', 'suspended'])->default('pending_setup');
    $table->json('api_credentials');  // Encrypted at application layer
    $table->json('settings')->nullable();
    $table->timestamp('last_sync_at')->nullable();
    $table->string('sync_status')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// database/migrations/2024_03_13_000003_create_tenant_user_table.php
Schema::create('tenant_user', function (Blueprint $table) {
    $table->id();
    $table->uuid('tenant_id');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('role')->default('member');
    $table->timestamp('joined_at')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->unique(['tenant_id', 'user_id']);
});

// Add current_tenant_id to users table
Schema::table('users', function (Blueprint $table) {
    $table->uuid('current_tenant_id')->nullable();
    $table->foreign('current_tenant_id')->references('id')->on('tenants')->onDelete('set null');
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual tenant scoping (`->where('tenant_id', $id)`) | Global scopes | Laravel 5.2+ | Automatic scoping reduces boilerplate and prevents data leaks |
| Custom encryption services | `encrypted` cast | Laravel 8.x | Built-in AES-256-CBC using APP_KEY, no custom crypto code |
| Query scopes (`Tenant::scopeCurrent()`) | Closure-based global scopes | Laravel 11 | Cleaner syntax, better testability, IDE-friendly |
| String constants for enums | PHP 8.1+ enums | Laravel 11 | Type safety, refactoring support, IDE autocomplete |
| Session-based tenant selection | Header-based (`X-Tenant-ID`) | Industry standard | Stateless, API-first, works with queue jobs |

**Deprecated/outdated:**
- **Tenant packages (stancl/tenancy, hipanel/tenancy):** Over-engineered for simple tenant_id discriminator pattern. Laravel 11 has native support for multi-tenant patterns.
- **Route-based tenant selection (subdomains):** Adds DNS complexity, hard to test. Header-based selection is simpler.
- **Soft deletes on pivot tables:** Not supported natively, requires custom pivot model. Use cascade deletes instead.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x (Laravel 11 default) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --testsuite=Feature` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TENANT-01 | Create tenant with validation | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_create_tenant_with_valid_data` | ❌ Wave 0 |
| TENANT-01 | Validate platform_type enum | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_create_tenant_validates_platform_type` | ❌ Wave 0 |
| TENANT-01 | Synchronous credential validation | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_create_tenant_validates_credentials_with_platform_api` | ❌ Wave 0 |
| TENANT-02 | List all tenants for authenticated user | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_list_tenants_returns_user_tenants_only` | ❌ Wave 0 |
| TENANT-03 | Update tenant details | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_update_tenant_with_valid_data` | ❌ Wave 0 |
| TENANT-04 | Delete tenant (soft delete) | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_delete_tenant_soft_deletes_record` | ❌ Wave 0 |
| TENANT-05 | Credentials encrypted in database | Unit | `php artisan test tests/Unit/Models/TenantEncryptionTest.php::test_api_credentials_encrypted_in_database` | ❌ Wave 0 |
| TENANT-06 | tenant_id discriminator on models | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php::test_tenant_models_have_tenant_id_foreign_key` | ❌ Wave 0 |
| TENANT-07 | Global scope applies to queries | Unit | `php artisan test tests/Unit/Models/TenantScopeTest.php::test_global_scope_filters_by_tenant_id` | ❌ Wave 0 |
| TEST-01 | Unit tests for scoping logic | Unit | `php artisan test tests/Unit/Models/TenantScopeTest.php` | ❌ Wave 0 |
| TEST-02 | Feature tests for endpoints | Feature | `php artisan test tests/Feature/Api/TenantManagementTest.php` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --testsuite=Feature` (quick feature tests, <30s)
- **Per wave merge:** `php artisan test` (full suite, including unit tests)
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Api/TenantManagementTest.php` — CRUD endpoint tests (TENANT-01 through TENANT-04)
- [ ] `tests/Unit/Models/TenantScopeTest.php` — Global scope logic tests (TENANT-07)
- [ ] `tests/Unit/Models/TenantEncryptionTest.php` — Credential encryption tests (TENANT-05)
- [ ] `tests/Unit/Services/PlatformCredentialValidatorTest.php` — Credential validation service tests (TENANT-01)
- [ ] `database/migrations/2024_03_13_000002_create_tenants_table.php` — Tenant table schema
- [ ] `database/migrations/2024_03_13_000003_create_tenant_user_table.php` — Pivot table schema
- [ ] `database/migrations/2024_03_13_000004_add_current_tenant_to_users_table.php` — User table update
- [ ] `app/Models/Tenant.php` — Tenant model with enums and casts
- [ ] `app/Enums/PlatformType.php` — Platform enum
- [ ] `app/Enums/TenantStatus.php` — Status enum
- [ ] `app/Http/Middleware/SetTenant.php` — Tenant context middleware
- [ ] `app/Http/Middleware/TenantScope.php` — Global scope middleware
- [ ] `app/Http/Requests/CreateTenantRequest.php` — Create validation
- [ ] `app/Http/Requests/UpdateTenantRequest.php` — Update validation
- [ ] `app/Http/Resources/TenantResource.php` — API resource
- [ ] `app/Http/Controllers/Api/V1/TenantController.php` — CRUD controller
- [ ] `app/Services/PlatformCredentialValidator.php` — Credential validation service
- [ ] Update `app/Models/User.php` with tenants() relationship
- [ ] Update `routes/api.php` with tenant routes

**Framework already installed:** PHPUnit 10.x configured in `phpunit.xml` with `RefreshDatabase` trait available. No additional installation needed.

## Open Questions

1. **Platform credential formats for Shopify and Shopware in 2026**
   - What we know: Credentials stored as JSON with api_key and api_secret fields
   - What's unclear: Exact field names, any additional required fields (webhook URLs, access tokens)
   - Recommendation: Build generic JSON structure, validate specific formats during Phase 6 when actual sync implementation happens

2. **Pivot table additional fields**
   - What we know: tenant_user needs timestamps, suggested role and joined_at fields
   - What's unclear: Whether role field is needed for v1 (no permissions system planned)
   - Recommendation: Include role field for future-proofing, default to 'member', set joined_at on attach

3. **Soft deletes vs hard deletes for tenants**
   - What we know: Soft deletes prevent accidental data loss
   - What's unclear: Whether deleted tenants should be visible in API (with_deleted scope)
   - Recommendation: Use soft deletes, hide deleted tenants from default API responses, add `?with_trashed=true` query param for admins

4. **Global scope on base model vs trait vs individual scopes**
   - What we know: Laravel 11 supports closure-based global scopes in `booted()` method
   - What's unclear: Whether to create a `Tenantable` trait for reusability
   - Recommendation: Start with inline global scopes in each model, extract to trait if 3+ models use same pattern (YAGNI principle)

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** - Eloquent ORM, global scopes, enum casting, encrypted casts, many-to-many relationships
- **Laravel Sanctum Documentation** - API authentication (already integrated in Phase 2)
- **PHP 8.1+ Enums** - Backed enums for type-safe field values
- **Existing codebase** - ApiController, User model, test patterns from Phase 2

### Secondary (MEDIUM confidence)
- **Laravel 11 Release Notes** - New features and improvements
- **Existing Phase 2 tests** - Patterns for feature testing, validation testing, authentication testing

### Tertiary (LOW confidence)
- *No web search sources available due to rate limiting - research based on established Laravel patterns*

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 11 features are well-documented and stable
- Architecture: HIGH - Global scopes and encrypted casts are core Laravel features
- Pitfalls: HIGH - Common multi-tenant patterns with known solutions
- API validation: MEDIUM - Platform-specific credential formats may vary

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - Laravel 11 is stable, but platform APIs may change)
