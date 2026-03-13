<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Enums\PlatformType;

class UserTenantRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_belongs_to_many_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create([
            'name' => 'Store 1',
            'slug' => 'store-1',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store1.myshopify.com',
            'api_credentials' => ['api_key' => 'key1'],
        ]);
        $tenant2 = Tenant::factory()->create([
            'name' => 'Store 2',
            'slug' => 'store-2',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store2.myshopify.com',
            'api_credentials' => ['api_key' => 'key2'],
        ]);

        $user->tenants()->attach([$tenant1->id, $tenant2->id]);

        $this->assertCount(2, $user->tenants);
        $this->assertTrue($user->tenants->contains($tenant1));
        $this->assertTrue($user->tenants->contains($tenant2));
    }

    public function test_relationship_uses_with_timestamps_and_with_pivot(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Store',
            'slug' => 'store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store.myshopify.com',
            'api_credentials' => ['api_key' => 'key'],
        ]);

        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $pivot = $user->tenants()->first()->pivot;
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
        $this->assertEquals('admin', $pivot->role);
        $this->assertNotNull($pivot->joined_at);
    }

    public function test_user_can_access_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Store',
            'slug' => 'store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store.myshopify.com',
            'api_credentials' => ['api_key' => 'key'],
        ]);

        $user->current_tenant_id = $tenant->id;
        $user->save();

        $this->assertInstanceOf(Tenant::class, $user->currentTenant);
        $this->assertEquals($tenant->id, $user->currentTenant->id);
    }

    public function test_set_current_tenant_method(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Store',
            'slug' => 'store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store.myshopify.com',
            'api_credentials' => ['api_key' => 'key'],
        ]);

        $user->setCurrentTenant($tenant);

        $this->assertEquals($tenant->id, $user->current_tenant_id);
        $this->assertEquals($tenant->id, $user->currentTenantId());
    }

    public function test_current_tenant_id_returns_null_when_not_set(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->currentTenantId());
        $this->assertNull($user->currentTenant);
    }

    public function test_can_attach_and_detach_tenants_with_pivot_data(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Store',
            'slug' => 'store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://store.myshopify.com',
            'api_credentials' => ['api_key' => 'key'],
        ]);

        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $this->assertCount(1, $user->tenants);
        $this->assertEquals('admin', $user->tenants()->first()->pivot->role);

        $user->tenants()->detach($tenant->id);

        $this->assertCount(0, $user->fresh()->tenants);
    }
}
