<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class DashboardRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_dashboard_metrics_route_registered()
    {
        // Verify the route is registered
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByAction([
            'uses' => [App\Http\Controllers\DashboardController::class, 'metrics'],
        ]);

        $this->assertNotNull($route, 'Dashboard metrics route should be registered');
        $this->assertEquals('/dashboard/metrics', $route->uri);
    }

    public function test_dashboard_metrics_route_requires_authentication()
    {
        // Test that unauthenticated users cannot access the route
        $response = $this->get('/dashboard/metrics');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_metrics_route_accessible_to_authenticated_users()
    {
        // Test that authenticated users can access the route
        $response = $this->actingAs($this->user)
            ->get('/dashboard/metrics');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_tenants_api_route_registered()
    {
        // Verify the API tenant list route is registered
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByAction([
            'uses' => [App\Http\Controllers\Api\V1\TenantController::class, 'index'],
        ]);

        $this->assertNotNull($route, 'Tenant list API route should be registered');
        $this->assertStringContainsString('tenants', $route->uri);
    }

    public function test_tenants_api_route_requires_authentication()
    {
        // Test that unauthenticated users cannot access the API route
        $response = $this->getJson('/api/v1/tenants');
        $response->assertStatus(401);
    }

    public function test_tenants_api_route_accessible_to_authenticated_users()
    {
        // Test that authenticated users can access the API route
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    public function test_all_dashboard_routes_use_auth_middleware()
    {
        $dashboardRoutes = [
            '/dashboard/metrics',
            '/dashboard/tenants',
        ];

        foreach ($dashboardRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login', "Route {$route} should require authentication");
        }
    }
}
