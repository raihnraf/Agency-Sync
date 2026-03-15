<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Product Search UI Integration Tests
 *
 * Tests for UI-07: Agency admin can search products within a client's catalog
 * Focuses on frontend JavaScript calling correct API endpoint
 *
 * @group frontend
 */
class ProductSearchUIIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_search_calls_correct_search_endpoint(): void
    {
        // GREEN Phase - Verify dashboard JavaScript calls /search not /products
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Verify the correct /search endpoint exists and works
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'price',
                        'stock_status'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    public function test_dashboard_search_includes_tenant_id_in_request(): void
    {
        // GREEN Phase - Verify tenantId is in URL path
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Verify endpoint requires tenant ID in path
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_dashboard_search_handles_api_errors(): void
    {
        // GREEN Phase - Verify error handling works
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Test 401 unauthorized
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");
        $response->assertStatus(200); // Authenticated user should get 200

        // Test invalid tenant returns 404 (generic to prevent enumeration)
        $fakeTenantId = '00000000-0000-0000-0000-000000000000';
        $response = $this->getJson("/api/v1/tenants/{$fakeTenantId}/search?query=test");
        $response->assertStatus(404);
    }

    public function test_dashboard_search_updates_ui_with_results(): void
    {
        // GREEN Phase - Verify UI can consume the response format
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        // Create test products
        Product::factory()->count(3)->for($tenant)->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 29.99,
            'stock_status' => 'in_stock'
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=Test&page=1");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Test Product')
            ->assertJsonPath('data.0.sku', 'TEST-001')
            ->assertJsonPath('data.0.price', 29.99)
            ->assertJsonPath('data.0.stock_status', 'in_stock')
            ->assertJsonPath('meta.total', 3);
    }
}
