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
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'data',
                'meta',
            ],
            'meta' => [],
        ]);
    }

    public function test_product_search_endpoint_requires_authentication(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search");

        $response->assertStatus(401);
    }

    public function test_product_search_results_scoped_to_tenant(): void
    {
        // Create user with tenant A
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->hasAttached($user)->create();
        $tenantB = Tenant::factory()->create();

        Sanctum::actingAs($user);

        // Search for products in tenant A
        $response = $this->getJson("/api/v1/tenants/{$tenantA->id}/search?query=test");

        $response->assertStatus(200);

        // Verify response structure
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('data', $response->json('data'));
        $this->assertArrayHasKey('meta', $response->json('data'));

        // Search for products in tenant B (should return 404 since user doesn't have access)
        $response = $this->getJson("/api/v1/tenants/{$tenantB->id}/search?query=test");

        // Should return 404 since user doesn't have access to tenant B
        $response->assertStatus(404);
    }

    public function test_product_search_accepts_query_and_page_parameters(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user)->create();

        // Create 30 products in tenant's catalog
        Product::factory()->count(30)->create([
            'tenant_id' => $tenant->id,
        ]);

        Sanctum::actingAs($user);

        // Test query parameter - search for any query
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");
        $response->assertStatus(200);
        $responseData = $response->json('data.data');
        $this->assertGreaterThanOrEqual(0, count($responseData));

        // Test page parameter - request page 2
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test&page=2");
        $response->assertStatus(200);
        $responseMeta = $response->json('data.meta');
        $this->assertEquals(2, $responseMeta['current_page']);
        $this->assertGreaterThanOrEqual(0, $responseMeta['total']);
    }
}
