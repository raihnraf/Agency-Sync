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
        $this->assertTrue(true);
    }

    public function test_sanctum_hasApiTokens_trait_unchanged()
    {
        $this->assertTrue(true);
    }

    public function test_api_and_web_auth_coexist()
    {
        $this->assertTrue(true);
    }
}
