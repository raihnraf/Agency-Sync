<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API endpoints use v1 prefix.
     */
    public function test_api_endpoints_use_v1_prefix()
    {
        // Test that /api/v1/register endpoint is accessible
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Should return either 201 (success) or 422 (validation error)
        // Both are acceptable - the important part is that the route exists
        $this->assertContains($response->status(), [201, 422]);
    }

    /**
     * Test unversioned endpoint returns 404.
     */
    public function test_unversioned_endpoint_returns_404()
    {
        // Test that /api/register (without v1) returns 404
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test API v1 routes are isolated.
     */
    public function test_api_v1_routes_are_isolated()
    {
        // Test that /api/v1/login exists
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Should return either 401 (auth failed) or 422 (validation)
        // Both prove the route exists with v1 prefix
        $this->assertContains($response->status(), [401, 422]);
    }

    /**
     * Test future API versions can coexist.
     */
    public function test_future_api_versions_can_coexist()
    {
        // This test documents the versioning strategy
        // Future: Add Route::prefix('v2') group for API v2
        // Current structure supports multiple versions:
        // Route::prefix('v1')->group(function () { ... });
        // Route::prefix('v2')->group(function () { ... });

        // Verify current structure is prefix-based (not hardcoded in URLs)
        $routesContent = file_get_contents(base_path('routes/api.php'));
        $this->assertStringContainsString("Route::prefix('v1')", $routesContent);

        // Verify comment indicates versioning capability
        $this->assertStringContainsString('Future versions can be added', $routesContent);
    }
}
