<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Enums\PlatformType;
use App\Enums\TenantStatus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class TenantManagementTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_create_tenant_with_valid_data_returns_201()
    {
        Sanctum::actingAs($this->user);

        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://test.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Tenant')
            ->assertJsonPath('data.platform_type', 'shopify')
            ->assertJsonPath('meta.message', 'Tenant created successfully');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'platform_type' => PlatformType::SHOPIFY,
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $this->user->id,
            'role' => 'admin',
        ]);
    }

    public function test_create_tenant_with_invalid_credentials_returns_422()
    {
        Sanctum::actingAs($this->user);

        Http::fake([
            '*' => Http::response(['error' => 'Invalid credentials'], 401),
        ]);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://test.myshopify.com',
            'api_credentials' => [
                'api_key' => 'invalid_key',
                'api_secret' => 'invalid_secret',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'api_credentials')
            ->assertJsonPath('errors.0.message', 'Invalid API credentials');
    }

    public function test_list_tenants_returns_only_user_tenants()
    {
        Sanctum::actingAs($this->user);

        // Create tenants for this user
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $this->user->tenants()->attach($tenant1->id, ['role' => 'admin', 'joined_at' => now()]);
        $this->user->tenants()->attach($tenant2->id, ['role' => 'admin', 'joined_at' => now()]);

        // Create tenant for another user
        $otherUser = User::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $otherUser->tenants()->attach($otherTenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $response = $this->getJson('/api/v1/tenants');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $tenant1->id)
            ->assertJsonPath('data.data.1.id', $tenant2->id)
            ->assertJsonMissingPath('data.data.2');

        $this->assertEquals(2, count($response->json('data.data')));
    }

    public function test_show_tenant_returns_tenant_data()
    {
        Sanctum::actingAs($this->user);

        $tenant = Tenant::factory()->create();
        $this->user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);
        $this->user->setCurrentTenant($tenant);

        $response = $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/tenants/' . $tenant->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.name', $tenant->name)
            ->assertJsonMissingPath('data.api_credentials');
    }

    public function test_update_tenant_modifies_fields()
    {
        Sanctum::actingAs($this->user);

        $tenant = Tenant::factory()->create(['name' => 'Old Name']);
        $this->user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);
        $this->user->setCurrentTenant($tenant);

        $response = $this->withHeader('X-Tenant-ID', $tenant->id)
            ->putJson('/api/v1/tenants/' . $tenant->id, [
                'name' => 'Updated Name',
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_delete_tenant_soft_deletes()
    {
        Sanctum::actingAs($this->user);

        $tenant = Tenant::factory()->create();
        $this->user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);
        $this->user->setCurrentTenant($tenant);

        $response = $this->withHeader('X-Tenant-ID', $tenant->id)
            ->deleteJson('/api/v1/tenants/' . $tenant->id);

        $response->assertStatus(204);

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);
    }

    public function test_unauthorized_tenant_access_returns_404()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $otherUser->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $response = $this->withHeader('X-Tenant-ID', $tenant->id)
            ->getJson('/api/v1/tenants/' . $tenant->id);

        $response->assertStatus(404);
    }
}
