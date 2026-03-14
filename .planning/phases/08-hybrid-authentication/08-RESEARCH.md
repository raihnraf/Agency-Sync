# Phase 8: Hybrid Authentication - Research

**Researched:** 2026-03-14
**Domain:** Laravel 11 Authentication (Breeze + Sanctum)
**Confidence:** HIGH

## Summary

This phase adds Laravel Breeze (Blade edition) for web UI authentication while preserving existing Sanctum token authentication for API routes. The hybrid approach enables agency users to access the admin dashboard through traditional session-based login (browser) while maintaining API-first architecture for machine-to-machine communication (webhooks, integrations).

**Primary recommendation:** Install Laravel Breeze with `blade` stack, remove registration routes, create custom artisan command for admin user creation, and customize login view with AgencySync branding. Breeze and Sanctum coexist seamlessly - Breeze adds session authentication methods to User model while Sanctum's HasApiTokens trait remains intact for API token auth.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Custom artisan command** — `php artisan agency:admin` with interactive prompts for admin creation
- **Full validation** — Email uniqueness check, password minimum 8 characters with retry on failure
- **Command output** — Success message + login URL: "Admin created! Login at: http://localhost/login"
- **Only method for v1** — No registration route, no user management UI (deferred)
- **Remove /register route** — Delete Breeze's registration route after installation
- **Basic branding only** — Add AgencySync logo, indigo theme colors (match TailwindCSS from Phase 7), custom footer
- **Keep Breeze layout structure** — Don't rebuild from scratch, customize template styling
- **Redirect to /home** — After logout, users go to public-facing landing page (welcome.blade.php)
- **2-hour session lifetime** — Default Laravel session expiration (balances security with UX)
- **5-year remember me** — Enable remember me checkbox with Laravel's default 5-year duration
- **Multiple concurrent sessions** — Allow users logged in from multiple devices
- **2FA deferred to v2** — Skip two-factor authentication for v1 milestone
- **Skip email verification** — User can login immediately after admin creation
- **Separate authentication systems** — Web routes use sessions, API routes use Sanctum tokens
- **No conflicts** — Breeze adds session methods to User model, Sanctum's HasApiTokens trait remains intact

### Claude's Discretion
- Exact password strength rules beyond minimum 8 characters
- Specific validation error messages
- Session management UI implementation details (future phase)
- Exact styling customization approach (CSS vs Blade component overrides)

### Deferred Ideas (OUT OF SCOPE)
- User management UI — Admin dashboard to create/edit/delete users from UI (v2 or future phase)
- Profile-based password reset — Phase 7 decision, implementation deferred
- Email verification — Skip for v1 (Phase 7 decision)
- 2FA (Two-Factor Authentication) — Defer to v2
- OAuth/Social login — Not planned for v1
- Session management UI — Show active sessions, revoke functionality (future enhancement)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AUTH-WEB-01 | Laravel Breeze (Blade edition) installed and configured | Installation process documented, Blade stack specified |
| AUTH-WEB-02 | Web routes (routes/web.php) use session-based authentication | `auth` middleware already in place, Breeze makes it functional |
| AUTH-WEB-03 | API routes (routes/api.php) continue using Sanctum token authentication | Existing `auth:sanctum` middleware preserved, no conflicts |
| AUTH-WEB-04 | Login page accessible at /login with email/password form | Breeze creates login route and view, customization approach documented |
| AUTH-WEB-05 | Dashboard routes protected by auth middleware redirect unauthenticated users to login | Laravel's default auth middleware behavior, already configured |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Breeze | 1.x (latest) | Simple authentication scaffolding for Blade views | Official Laravel starter kit, minimal dependencies, integrates cleanly with existing codebase |
| Laravel Sanctum | 3.x (already installed) | API token authentication | Already installed from Phase 2, coexists seamlessly with Breeze session auth |
| Laravel 11 | 11.31 (already installed) | Application framework | Existing framework version, native session authentication support |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TailwindCSS | 3.x (CDN) | Login page styling | Already configured from Phase 7, custom indigo palette for branding consistency |
| Alpine.js | 3.x (already loaded) | Client-side interactivity | Already loaded from Phase 7, useful for form validation enhancements |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel Breeze | Laravel Jetstream | Jetstream is heavier (Livewire/Inertia teams), includes 2FA/teams features not needed for v1 |
| Laravel Breeze | Fortify | Breeze includes UI, Fortify is backend-only. Breeze better for portfolio demonstration |
| Laravel Breeze | Custom auth implementation | Hand-rolling authentication is error-prone, reinvents wheel, misses security best practices |

**Installation:**
```bash
# Install Breeze (development dependency)
composer require laravel/breeze --dev

# Install Blade stack (scaffolds auth controllers, views, routes)
php artisan breeze:install blade

# Compile assets (if using Vite, though Phase 7 uses Tailwind CDN)
npm install && npm run dev

# Run migrations (adds users table fields if missing)
php artisan migrate
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Console/
│   └── Commands/
│       └── CreateAdminUser.php          # NEW: Custom artisan command
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                        # NEW: Created by Breeze
│   │   │   ├── AuthenticatedSessionController.php
│   │   │   ├── ConfirmablePasswordController.php
│   │   │   ├── EmailVerificationNotificationController.php
│   │   │   ├── EmailVerificationPromptController.php
│   │   │   ├── NewPasswordController.php
│   │   │   ├── PasswordController.php
│   │   │   ├── PasswordResetLinkController.php
│   │   │   └── RegisteredUserController.php  # TO BE REMOVED (no registration)
│   │   └── Dashboard/                   # EXISTING: Phase 7 controllers
│   └── Middleware/
│       ├── RedirectIfAuthenticated.php # NEW: Guest middleware
│       └── ValidateSignature.php        # NEW: Email verification (unused in v1)
├── Models/
│   └── User.php                         # MODIFIED: Breeze adds auth methods
├── Providers/
│   └── AuthServiceProvider.php          # MODIFIED: Breeze adds policies
└── View/
    └── Components/                      # NEW: Blade components (optional)

resources/
├── views/
│   ├── auth/                            # NEW: Breeze auth views
│   │   ├── login.blade.php             # CUSTOMIZE: Branding, indigo theme
│   │   ├── register.blade.php           # DELETE: No self-registration
│   │   ├── confirm-password.blade.php   # KEEP: For future password confirmation
│   │   ├── forgot-password.blade.php    # KEEP: For future password reset
│   │   ├── reset-password.blade.php     # KEEP: For future password reset
│   │   └── verify-email.blade.php       # KEEP: For future email verification
│   ├── components/                      # NEW: Reusable Blade components
│   ├── layouts/                         # NEW: Base layouts (app.blade.php, guest.blade.php)
│   └── profile/                         # NEW: User profile management (future)
└── lang/                                # NEW: Authentication language files

routes/
├── web.php                              # MODIFIED: Breeze adds auth routes
└── api.php                              # UNCHANGED: Sanctum API routes

tests/
├── Feature/
│   └── AuthenticationTest.php           # NEW: Breeze feature tests
└── Unit/
    └── Console/
        └── CreateAdminUserTest.php      # NEW: Command unit tests
```

### Pattern 1: Laravel Breeze Installation Flow
**What:** Breeze installs authentication scaffolding including controllers, views, routes, and middleware
**When to use:** When adding session-based authentication to a Laravel 11 application
**Example:**
```bash
# Installation process
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install && npm run dev
php artisan migrate

# What gets created:
# - app/Http/Controllers/Auth/* (8 controllers)
# - resources/views/auth/* (login, register, password reset, email verification)
# - resources/views/layouts/* (app.blade.php, guest.blade.php)
# - routes/web.php updated with auth routes
# - app/Http/Middleware/RedirectIfAuthenticated.php
# - database/migrations/* (if users table doesn't exist)
```

### Pattern 2: Hybrid Authentication (Breeze + Sanctum)
**What:** Web routes use session authentication, API routes use Sanctum tokens, both coexist without conflicts
**When to use:** When building hybrid applications with both web UI and API endpoints
**Example:**
```php
// routes/web.php - Session authentication
Route::middleware(['auth'])->prefix('dashboard')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    // Session-based auth: User logged in via browser cookies
});

// routes/api.php - Token authentication
Route::middleware(['auth:sanctum', 'token.expire'])->prefix('v1')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    // Token-based auth: API requests with Bearer token
});
```

**Key insight:** The User model can have both session authentication methods (from Breeze) and API token methods (from Sanctum's HasApiTokens trait) without conflicts. Laravel's auth system differentiates based on middleware (`auth` vs `auth:sanctum`).

### Pattern 3: Custom Artisan Command with Interactive Prompts
**What:** Create console commands with user interaction (email/password prompts, validation, retry logic)
**When to use:** For admin user creation, data seeding, or any CLI task requiring user input
**Example:**
```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateAdminUser extends Command
{
    protected $signature = 'agency:admin';
    protected $description = 'Create a new admin user interactively';

    public function handle()
    {
        $this->info('Create Admin User');
        $this->line('-----------------');

        // Prompt for email with validation
        $email = $this->askValidEmail('Email address');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return Command::FAILURE;
        }

        // Prompt for password with validation
        $password = $this->askValidPassword('Password (min 8 characters)');
        $confirmPassword = $this->secret('Confirm password');

        // Validate password confirmation
        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');
            return Command::FAILURE;
        }

        // Create user
        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        $this->info("Admin user created successfully!");
        $this->line("Login at: http://localhost/login");

        return Command::SUCCESS;
    }

    protected function askValidEmail(string $question): string
    {
        do {
            $email = $this->ask($question);
            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email|unique:users,email',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                $continue = $this->confirm('Try again?', true);
                if (!$continue) {
                    throw new \RuntimeException('User cancelled');
                }
            }
        } while ($validator->fails());

        return $email;
    }

    protected function askValidPassword(string $question): string
    {
        do {
            $password = $this->secret($question);
            $validator = Validator::make(['password' => $password], [
                'password' => 'required|min:8',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                $continue = $this->confirm('Try again?', true);
                if (!$continue) {
                    throw new \RuntimeException('User cancelled');
                }
            }
        } while ($validator->fails());

        return $password;
    }
}
```

**Register command in `app/Console/Kernel.php`:**
```php
protected $commands = [
    \App\Console\Commands\CreateAdminUser::class,
];
```

### Pattern 4: Blade View Customization with TailwindCSS
**What:** Customize Breeze's default login view with AgencySync branding using TailwindCSS
**When to use:** When aligning authentication UI with existing design system
**Example:**
```blade
{{-- resources/views/auth/login.blade.php --}}
<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <!-- AgencySync Logo -->
            <svg class="h-12 w-auto text-indigo-600" viewBox="0 0 50 50" fill="currentColor">
                <!-- Custom logo path -->
            </svg>
        </x-slot>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <x-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end mt-4">
                <x-primary-button class="ml-3 bg-indigo-600 hover:bg-indigo-700">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>

{{-- Custom footer --}}
<div class="mt-6 text-center text-sm text-gray-600">
    <p>AgencySync © {{ date('Y') }}</p>
    <p class="mt-2">Multi-tenant E-commerce Agency Management System</p>
</div>
```

**TailwindCSS customization (from Phase 7):**
```javascript
// tailwind.config.js (already configured)
module.exports = {
    theme: {
        extend: {
            colors: {
                indigo: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                }
            }
        }
    }
}
```

### Anti-Patterns to Avoid
- **Removing Sanctum middleware**: Don't remove `auth:sanctum` from API routes - this breaks existing token authentication
- **Modifying User modelHasApiTokens**: Don't remove the `HasApiTokens` trait when adding Breeze - both can coexist
- **Hardcoding credentials**: Don't create default admin users in migrations - use artisan command for explicit creation
- **Skipping validation**: Don't skip email uniqueness or password validation in artisan command - enforce data integrity
- **Deleting password reset views**: Even though password reset is deferred, keep the views for Phase 7 decision implementation

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Session authentication | Custom session management, manual cookie handling | Laravel Breeze | Session security, CSRF protection, password hashing, remember me logic are complex and error-prone |
| Password hashing | Custom bcrypt/argon2 implementation | Laravel's `Hash` facade | Tested against timing attacks, uses best algorithms, automatic algorithm updates |
| CSRF protection | Custom token generation and validation | Laravel's `@csrf` directive | Prevents cross-site request forgery, token rotation, exception handling |
| Password reset | Custom token generation, email sending, expiration logic | Laravel's built-in password reset | Secure token generation, expiration handling, rate limiting, broker pattern |
| Remember me | Custom cookie persistence | Laravel's `remember_me` functionality | Secure token generation, cookie encryption, automatic logout on expiration |
| Interactive CLI prompts | Custom readline, input validation | Laravel's Command prompt methods | Built-in validation, retry logic, secret input, consistent UX |

**Key insight:** Authentication is a solved problem in Laravel. Building custom authentication implementations introduces security vulnerabilities (session fixation, CSRF, timing attacks) that Laravel's official packages have already addressed. Breeze is minimal, well-tested, and designed for extension.

## Common Pitfalls

### Pitfall 1: Registration Route Still Accessible
**What goes wrong:** After installing Breeze, users can still access `/register` and create accounts, violating single-tenant model
**Why it happens:** Breeze automatically creates registration routes and controllers
**How to avoid:** Explicitly remove registration routes and controllers after installation:
```php
// routes/web.php - REMOVE these lines:
// Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
// Route::post('register', [RegisteredUserController::class, 'store']);
```
**Warning signs:** Seeing "Register" link in login page, able to access `/register` URL directly

### Pitfall 2: Session and Token Auth Conflicts
**What goes wrong:** Web routes unexpectedly require API tokens, or API routes require session login
**Why it happens:** Middleware misconfiguration, using wrong guard in routes
**How to avoid:** Keep middleware separation clear:
- Web routes: `Route::middleware(['auth'])` (uses session guard by default)
- API routes: `Route::middleware(['auth:sanctum'])` (explicitly uses Sanctum guard)
**Warning signs:** API calls from Postman failing with 401 Unauthorized, browser showing "Token not provided" error

### Pitfall 3: Missing Sessions Table
**What goes wrong:** Session driver set to `database` but `sessions` table doesn't exist, causing session errors
**Why it happens:** Breeze doesn't create sessions table - Laravel provides it but doesn't run migration automatically
**How to avoid:** Check for sessions table and create if missing:
```bash
# Publish sessions migration if not exists
php artisan session:table

# Run migration
php artisan migrate
```
**Warning signs:** Error "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'agency_sync.sessions' doesn't exist"

### Pitfall 4: Command Validation Not Retry-Friendly
**What goes wrong:** Artisan command exits on first validation error instead of prompting for retry
**Why it happens:** Missing retry loop in validation logic
**How to avoid:** Wrap validation in do-while loop with continue prompt:
```php
do {
    $input = $this->ask('Your question');
    $validator = Validator::make([...]);
    if ($validator->fails()) {
        $this->error('Validation failed');
        if (!$this->confirm('Try again?', true)) {
            return Command::FAILURE;
        }
    }
} while ($validator->fails());
```
**Warning signs:** Command terminates immediately on typo in email or password

### Pitfall 5: Logout Redirects to Wrong URL
**What goes wrong:** After logout, users redirected to `/home` which doesn't exist, showing 404 error
**Why it happens:** Laravel's default logout redirect is `/home`, but AgencySync uses `/` (welcome page)
**How to avoid:** Override logout redirect in `app/Http/Controllers/Auth/AuthenticatedSessionController.php`:
```php
public function destroy(Request $request): RedirectResponse
{
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/'); // Instead of redirect('/home')
}
```
**Warning signs:** 404 error after clicking logout button

### Pitfall 6: Remember Me Not Working
**What goes wrong:** Users checking "Remember me" still get logged out after 2 hours
**Why it happens:** Remember me functionality requires users table `remember_token` column
**How to avoid:** Verify users table has `remember_token` column (Breeze migration should add it):
```php
// database/migrations/xxxx_create_users_table.php
$table->string('remember_token', 100)->nullable();
```
**Warning signs:** Users logged out even with "Remember me" checked, session lifetime seems ignored

### Pitfall 7: Cross-Site Session Leaks
**What goes wrong:** User logged into development site also authenticated on production site (session cookie domain conflict)
**Why it happens:** `SESSION_DOMAIN` not set, causing cookies to leak across subdomains
**How to avoid:** Set `SESSION_DOMAIN` in `.env` for each environment:
```bash
# .env (development)
SESSION_DOMAIN=localhost

# .env.production
SESSION_DOMAIN=agency-sync.example.com
```
**Warning signs:** Logged into dev.agency.com, then automatically logged into production on same browser

## Code Examples

Verified patterns from official sources:

### Breeze Installation and Basic Setup
```bash
# Install Breeze package
composer require laravel/breeze --dev

# Install Blade stack (this creates all auth scaffolding)
php artisan breeze:install blade

# Install and compile frontend dependencies
npm install && npm run dev

# Run database migrations
php artisan migrate

# (Optional) Publish config for customization
php artisan vendor:publish --tag=breeze-config
```

### Removing Registration Routes
```php
// routes/web.php - After Breeze installation, REMOVE these lines:

// Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
// Route::post('register', [RegisteredUserController::class, 'store']);

// Keep all other auth routes (login, logout, password reset, email verification)
```

### Custom Artisan Command Registration
```php
// app/Console/Kernel.php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
```

### Session Configuration for 2-Hour Lifetime
```php
// config/session.php

'lifetime' => env('SESSION_LIFETIME', 120), // 120 minutes = 2 hours

'expire_on_close' => false, // Don't expire when browser closes (allows remember me)

// Remember me duration (Laravel default: 5 years)
// Set in users table migration:
// $table->timestamp('remember_token')->nullable();
// Laravel automatically sets cookie expiration to 5 years (2628000 minutes) when remember me is checked
```

### Middleware Configuration (No Changes Needed)
```php
// bootstrap/app.php - ALREADY CONFIGURED FROM PHASE 2

->withMiddleware(function (Middleware $middleware) {
    // Sanctum middleware for API authentication (unchanged)
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);

    // Breeze uses default 'web' middleware (no configuration needed)
    // Session authentication works automatically via 'auth' middleware
})

// No changes needed - Breeze and Sanctum coexist without conflicts
```

### Blade Component Usage (Breeze Provides These)
```blade
{{-- resources/views/auth/login.blade.php --}}

<x-guest-layout>
    <x-auth-card>
        <!-- Logo slot -->
        <x-slot name="logo">
            <a href="/">
                <svg class="h-12 w-auto text-indigo-600" viewBox="0 0 50 50" fill="currentColor">
                    <!-- AgencySync logo path -->
                </svg>
            </a>
        </x-slot>

        <!-- Session status (for success messages) -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation errors -->
        <x-validation-errors class="mb-4" :errors="$errors" />

        <!-- Login form -->
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email input -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input
                    id="email"
                    class="block mt-1 w-full"
                    type="email"
                    name="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password input -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input
                    id="password"
                    class="block mt-1 w-full"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Remember me checkbox -->
            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input
                        id="remember"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        name="remember"
                    >
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <!-- Submit button -->
            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-primary-button class="ml-3 bg-indigo-600 hover:bg-indigo-700">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
```

### Testing Authentication (Feature Test Example)
```php
// tests/Feature/AuthTest.php

<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login page is accessible', function () {
    $response = $this->get('/login');
    $response->assertStatus(200);
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('user cannot login with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('authenticated user can access dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard/tenants');
    $response->assertStatus(200);
});

test('guest cannot access dashboard', function () {
    $response = $this->get('/dashboard/tenants');
    $response->assertRedirect('/login');
});

test('user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');
    $response->assertRedirect('/');
    $this->assertGuest();
});

test('registration route is not accessible', function () {
    $response = $this->get('/register');
    $response->assertStatus(404); // Route should not exist
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom auth implementation | Laravel Breeze (Blade stack) | Laravel 8.x (2021) | Minimal dependencies, clean scaffolding, easy customization |
| UI-based starter kits (Jetstream with Livewire/Inertia) | API-first starter kits (Breeze) | Laravel 8.x (2021) | Breeze is lighter weight, no frontend framework requirements |
| Session vs Token auth (separate apps) | Hybrid auth (Breeze + Sanctum in same app) | Laravel 7.x (2020) | Single codebase serves both web UI and API clients |
| Email verification required | Email verification optional | Laravel 8.x (2021) | Faster onboarding for admin users, verification can be added later |

**Deprecated/outdated:**
- **Laravel's default auth scaffolding (`php artisan make:auth`)**: Removed in Laravel 6, replaced by Breeze/Jetstream
- **Passport for simple token auth**: Overkill for API token auth, Sanctum is simpler and lighter
- **Custom session management**: Laravel's session system is robust, custom implementations are unnecessary
- **Manual CSRF token handling**: Use `@csrf` directive and `VerifyCsrfToken` middleware instead

## Open Questions

1. **Session storage for production**
   - What we know: Database driver configured (`SESSION_DRIVER=database`), sessions table needed
   - What's unclear: Should we use Redis for session storage in production for better performance?
   - Recommendation: Keep database sessions for v1 (simpler), consider Redis for v2 if performance issues arise

2. **Password reset implementation timing**
   - What we know: Password reset views and controllers will be installed by Breeze
   - What's unclear: Should we implement password reset in this phase or defer to future phase?
   - Recommendation: Keep scaffolding but don't implement email sending yet (deferred to v2 per CONTEXT.md)

3. **Multi-admin user management**
   - What we know: Only one admin user created via artisan command in v1
   - What's unclear: How will additional admin users be created without registration UI?
   - Recommendation: Use `php artisan agency:admin` command for all admin creation in v1, add UI in v2

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 (already installed) |
| Config file | phpunit.xml (already configured) |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUTH-WEB-01 | Laravel Breeze installed and configured | unit | `php artisan test --filter=BreezeInstallTest` | ❌ Wave 0 |
| AUTH-WEB-02 | Web routes use session auth | feature | `php artisan test --filter=SessionAuthTest` | ❌ Wave 0 |
| AUTH-WEB-03 | API routes continue using Sanctum | feature | `php artisan test --filter=SanctumAuthTest` | ✅ Already exists (Phase 2) |
| AUTH-WEB-04 | Login page accessible at /login | feature | `php artisan test --filter=LoginPageTest` | ❌ Wave 0 |
| AUTH-WEB-05 | Dashboard routes redirect to login | feature | `php artisan test --filter=DashboardAuthTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --parallel` (run relevant test file)
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Auth/LoginPageTest.php` — covers AUTH-WEB-04
- [ ] `tests/Feature/Auth/SessionAuthTest.php` — covers AUTH-WEB-02
- [ ] `tests/Feature/Auth/DashboardAuthTest.php` — covers AUTH-WEB-05
- [ ] `tests/Unit/Console/CreateAdminUserTest.php` — artisan command unit tests
- [ ] `tests/Feature/Auth/SanctumUnchangedTest.php` — verifies AUTH-WEB-03 (API routes still work)
- [ ] Framework already installed: PHPUnit 11.0.1 in composer.json

## Sources

### Primary (HIGH confidence)
- **Laravel Breeze Documentation** - Official Laravel starter kit documentation for Blade stack installation, configuration, and customization
- **Laravel 11 Authentication Documentation** - Session authentication architecture, guards, middleware configuration
- **Laravel Sanctum Documentation** - API token authentication, coexistence with session auth
- **Laravel Artisan Commands Documentation** - Console command creation, interactive prompts, validation
- **Laravel 11 Release Notes** - Authentication changes, new features, improvements

### Secondary (MEDIUM confidence)
- **Existing codebase analysis** - Current Sanctum implementation (Phase 2), session configuration, User model structure
- **AgencySync CONTEXT.md** - User decisions, constraints, deferred features
- **Phase 7 TailwindCSS configuration** - Custom indigo palette for branding consistency

### Tertiary (LOW confidence)
- **Community patterns** - Laravel Breeze customization best practices, hybrid authentication patterns
- **Stack Overflow discussions** - Common Breeze + Sanctum coexistence issues and solutions

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel Breeze is official starter kit, Sanctum coexistence well-documented
- Architecture: HIGH - Hybrid authentication pattern is standard practice, Laravel 11 native support
- Pitfalls: HIGH - Common issues documented in official docs and community resources

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (30 days - Laravel ecosystem stable, Breeze mature)

**Key verification findings:**
- User model already has `HasApiTokens` trait (Phase 2), Breeze will not conflict
- Session driver already configured for database (`SESSION_DRIVER=database`)
- Web routes already have `auth` middleware protecting dashboard (Phase 7)
- API routes already have `auth:sanctum` middleware (Phase 2), no changes needed
- Custom login view exists at `resources/views/auth/login.blade.php` but uses non-existent route
- No artisan commands exist yet, need to create `CreateAdminUser` command
- TailwindCSS already configured from Phase 7 with custom indigo palette
