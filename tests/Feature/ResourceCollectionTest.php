<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_logs_index_returns_data_meta_links_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(5)->create();

        $response = $this->getJson('/api/v1/sync-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'total', 'per_page']
            ]);
    }

    public function test_sync_logs_meta_includes_last_page(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(20)->create();

        $response = $this->getJson('/api/v1/sync-logs?per_page=15');

        $response->assertOk()
            ->assertJson(['meta' => ['last_page' => 2]])
            ->assertJsonMissingPath('last_page'); // Not at root level
    }

    public function test_sync_logs_meta_includes_current_page(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/sync-logs');

        $response->assertOk()
            ->assertJson(['meta' => ['current_page' => 1]]);
    }

    public function test_sync_logs_meta_includes_total_and_per_page(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        SyncLog::factory()->for($tenant)->count(25)->create();

        $response = $this->getJson('/api/v1/sync-logs?per_page=10');

        $response->assertOk()
            ->assertJson(['meta' => ['total' => 25, 'per_page' => 10]]);
    }

    public function test_sync_logs_links_includes_first_last_prev_next(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/sync-logs');

        $response->assertJsonStructure([
            'links' => ['first', 'last', 'prev', 'next']
        ]);
    }

    public function test_sync_logs_data_array_contains_resource_transformed_items(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tenant = Tenant::factory()->create();
        $tenant->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        $syncLog = SyncLog::factory()->for($tenant)->create([
            'status' => 'completed',
            'total_products' => 100,
        ]);

        $response = $this->getJson('/api/v1/sync-logs');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($syncLog->id, $data[0]['id']);
        $this->assertEquals('completed', $data[0]['status']);
        $this->assertEquals(100, $data[0]['total_products']);
    }
}
