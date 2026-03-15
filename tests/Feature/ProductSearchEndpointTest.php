<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Product Search API Endpoint Tests
 *
 * Tests for SEARCH-01: Agency admin can search products within a single client's catalog
 * Tests for SEARCH-07: Search results only include products from selected client store (tenant isolation)
 *
 * @group frontend
 */
class ProductSearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_endpoint_returns_200_for_authenticated_user(): void
    {
        // GREEN Phase - Real API call test
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    public function test_product_search_endpoint_requires_authentication(): void
    {
        // GREEN Phase - Verify 401 without auth
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        // Without authentication
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");

        $response->assertStatus(401);
    }

    public function test_product_search_results_scoped_to_tenant(): void
    {
        // GREEN Phase - Verify tenant isolation
        $user = User::factory()->create();

        // Create two tenants
        $tenant1 = Tenant::factory()->for($user)->create();
        $tenant2 = Tenant::factory()->for($user)->create();

        // Create products for each tenant
        Product::factory()->count(5)->for($tenant1)->create(['name' => 'Tenant1 Product']);
        Product::factory()->count(3)->for($tenant2)->create(['name' => 'Tenant2 Product']);

        Sanctum::actingAs($user);

        // Search in tenant1 should only return tenant1 products
        $response = $this->getJson("/api/v1/tenants/{$tenant1->id}/search?query=Product");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Tenant1 Product');
    }

    public function test_product_search_accepts_query_and_page_parameters(): void
    {
        // GREEN Phase - Verify parameter handling
        $user = User::factory()->create();
        $tenant = Tenant::factory()->for($user)->create();

        // Create 15 products to test pagination
        Product::factory()->count(15)->for($tenant)->create(['name' => 'Searchable Product']);

        Sanctum::actingAs($user);

        // Test page 1
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=Searchable&page=1");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10);

        // Test page 2
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=Searchable&page=2");

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(5, 'data');
    }
}
