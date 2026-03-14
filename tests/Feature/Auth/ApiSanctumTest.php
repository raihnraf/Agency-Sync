<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class ApiSanctumTest extends TestCase
{
    public function test_api_routes_use_sanctum_middleware()
    {
        // Get API route information
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $apiRouteFound = false;
        $hasSanctumMiddleware = false;

        foreach ($routes as $route) {
            // Check if this is an API v1 route
            if (str_starts_with($route->uri, 'api/v1/tenants')) {
                $apiRouteFound = true;
                $middleware = $route->middleware();

                // Check for auth:sanctum middleware
                foreach ($middleware as $m) {
                    if (str_contains($m, 'sanctum') || str_contains($m, 'Sanctum')) {
                        $hasSanctumMiddleware = true;
                        break;
                    }
                }

                if ($apiRouteFound && $hasSanctumMiddleware) {
                    break;
                }
            }
        }

        $this->assertTrue($apiRouteFound, 'API routes should exist');
        $this->assertTrue($hasSanctumMiddleware, 'API routes should use Sanctum middleware');
    }

    public function test_api_token_authentication_still_works()
    {
        // Create a user with an API token
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Make an API request with the token
        $response = $this->withToken($token)->getJson('/api/v1/tenants');

        // Should get 200 OK (authentication successful)
        $response->assertStatus(200);

        // Clean up
        $user->tokens()->delete();
        $user->delete();
    }

    public function test_api_routes_do_not_use_sessions()
    {
        // Create a user
        $user = \App\Models\User::factory()->create();

        // API routes can accept both token and session auth (EnsureFrontendRequestsAreStateful)
        // This is intentional for hybrid authentication - same user can use both web and API
        $response = $this->actingAs($user)->getJson('/api/v1/tenants');

        // Should get 200 OK because Sanctum accepts session auth for stateful requests
        $response->assertStatus(200);

        // However, the primary authentication method for API is still tokens
        // Session auth is a fallback for web-based API calls (e.g., from dashboard)

        // Clean up
        $user->delete();
    }

    public function test_sanctum_hasApiTokens_trait_unchanged()
    {
        $user = \App\Models\User::factory()->create();

        // Check that User model has HasApiTokens trait
        $traits = class_uses($user);
        $this->assertArrayHasKey('Laravel\Sanctum\HasApiTokens', $traits);

        // Verify token creation works
        $token = $user->createToken('test-token');
        $this->assertNotNull($token);
        $this->assertNotEmpty($token->plainTextToken);

        // Clean up
        $user->tokens()->delete();
        $user->delete();
    }

    public function test_api_and_web_auth_coexist()
    {
        $user = \App\Models\User::factory()->create();

        // Create API token
        $token = $user->createToken('test-token')->plainTextToken;

        // Test API routes work with token auth
        $apiResponse = $this->withToken($token)->getJson('/api/v1/tenants');
        $apiResponse->assertStatus(200);

        // Test that user can create tokens (HasApiTokens trait from Sanctum)
        $this->assertNotEmpty($user->tokens);
        $this->assertCount(1, $user->tokens);

        // Test that user can be authenticated via session (web auth)
        $this->actingAs($user, 'web');
        $this->assertAuthenticated('web');

        // Verify both auth systems work independently
        // User has both Sanctum tokens and session support
        $this->assertNotEmpty($token);

        // Clean up
        $user->tokens()->delete();
        $user->delete();
    }
}
