<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_can_extract_data_array_from_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(5)->create([
            'status' => 'failed',
        ]);

        $response = $this->getJson('/api/v1/sync-logs?status=failed');

        $response->assertJsonPath('data', fn ($data) => is_array($data) && count($data) === 5);
    }

    public function test_frontend_can_extract_meta_last_page_from_response(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(20)->create([
            'status' => 'failed',
        ]);

        $response = $this->getJson('/api/v1/sync-logs?status=failed&per_page=15');

        // Frontend accesses: data.meta.last_page
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_frontend_pagination_works_with_resource_collection_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(25)->create();

        // Page 1
        $response1 = $this->getJson('/api/v1/sync-logs?page=1&per_page=10');
        $response1->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 25);

        // Page 2
        $response2 = $this->getJson('/api/v1/sync-logs?page=2&per_page=10');
        $response2->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(10, 'data');
    }

    public function test_error_log_filtering_works_with_new_response_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(3)->create(['status' => 'failed']);
        SyncLog::factory()->for($tenant)->count(5)->create(['status' => 'completed']);

        // Frontend filters: data.data.filter(log => log.status === 'failed')
        $response = $this->getJson('/api/v1/sync-logs?status=failed');

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status', 'failed');
    }

    public function test_product_search_already_uses_correct_pagination_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        Product::factory()->for($tenant)->count(15)->create();

        // Product search endpoint (query parameter is required)
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/search?query=test");

        // Product search uses different structure: data.data (array) and data.meta (pagination)
        // This is correct as-is - frontend already accesses it this way
        $response->assertStatus(200)
            ->assertJsonPath('data.data', fn ($val) => is_array($val))
            ->assertJsonPath('data.meta.last_page', fn ($val) => is_int($val))
            ->assertJsonPath('data.meta.total', fn ($val) => is_int($val));
    }
}
