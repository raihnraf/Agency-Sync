<?php

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Enums\PlatformType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShopifySyncServiceFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }
}
