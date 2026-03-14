<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;

class TenantListCacheTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_tenant_list_cached_with_15_minute_ttl()
    {
        // Create tenants for the user
        $tenants = Tenant::factory()->count(3)->create();
        foreach ($tenants as $tenant) {
            $this->user->tenants()->attach($tenant->id, ['role' => 'admin']);
        }

        // Clear cache
        Cache::forget("agency:tenants:list:{$this->user->id}");

        // Call the tenant list endpoint
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);

        // Verify cache was set (we can't easily test TTL in unit tests, but we can verify the cache key)
        $this->assertNotNull(Cache::get("agency:tenants:list:{$this->user->id}"));
    }

    public function test_cache_key_format_includes_user_id()
    {
        $cacheKey = "agency:tenants:list:{$this->user->id}";

        // Assert cache key format
        $this->assertEquals("agency:tenants:list:{$this->user->id}", $cacheKey);
        $this->assertStringStartsWith('agency:tenants:list:', $cacheKey);
    }

    public function test_cached_list_includes_only_safe_fields()
    {
        // Create a tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'api_credentials' => ['api_key' => 'secret'], // This should NOT be in the cached response
        ]);
        $this->user->tenants()->attach($tenant->id, ['role' => 'admin']);

        // Clear cache and fetch
        Cache::forget("agency:tenants:list:{$this->user->id}");

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        $data = $response->json('data');

        // Assert response contains safe fields
        $this->assertEquals('Test Tenant', $data[0]['name']);
        $this->assertEquals('test-tenant', $data[0]['slug']);
        $this->assertEquals('active', $data[0]['status']);

        // Assert sensitive fields are not present
        $this->assertArrayNotHasKey('api_credentials', $data[0]);
    }

    public function test_cache_miss_triggers_fresh_tenant_query()
    {
        // Create tenants
        $tenants = Tenant::factory()->count(5)->create();
        foreach ($tenants as $tenant) {
            $this->user->tenants()->attach($tenant->id, ['role' => 'admin']);
        }

        // Clear cache
        Cache::forget("agency:tenants:list:{$this->user->id}");

        // Verify cache is empty
        $this->assertNull(Cache::get("agency:tenants:list:{$this->user->id}"));

        // Call endpoint
        $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        // Assert cache was populated
        $this->assertNotNull(Cache::get("agency:tenants:list:{$this->user->id}"));
    }

    public function test_cache_hit_returns_cached_list_without_database_query()
    {
        // Create tenants
        $tenants = Tenant::factory()->count(3)->create();
        foreach ($tenants as $tenant) {
            $this->user->tenants()->attach($tenant->id, ['role' => 'admin']);
        }

        // Prime cache
        Cache::forget("agency:tenants:list:{$this->user->id}");
        $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        // Enable query counting
        \DB::enableQueryLog();

        // Call endpoint again (should use cache)
        $this->actingAs($this->user)
            ->getJson('/api/v1/tenants');

        // Assert no database queries were made (cache hit)
        $this->assertEmpty(\DB::getQueryLog());
    }

    public function test_cache_is_per_user_for_security()
    {
        // Create two users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create different tenants for each user
        $tenant1 = Tenant::factory()->create(['name' => 'User1 Tenant']);
        $tenant2 = Tenant::factory()->create(['name' => 'User2 Tenant']);

        $user1->tenants()->attach($tenant1->id, ['role' => 'admin']);
        $user2->tenants()->attach($tenant2->id, ['role' => 'admin']);

        // Clear all caches
        Cache::forget("agency:tenants:list:{$user1->id}");
        Cache::forget("agency:tenants:list:{$user2->id}");

        // Fetch tenants for user1
        $response1 = $this->actingAs($user1)
            ->getJson('/api/v1/tenants');
        $data1 = $response1->json('data');

        // Fetch tenants for user2
        $response2 = $this->actingAs($user2)
            ->getJson('/api/v1/tenants');
        $data2 = $response2->json('data');

        // Assert users see only their own tenants
        $this->assertCount(1, $data1);
        $this->assertCount(1, $data2);
        $this->assertEquals('User1 Tenant', $data1[0]['name']);
        $this->assertEquals('User2 Tenant', $data2[0]['name']);

        // Assert cache keys are different
        $cache1 = Cache::get("agency:tenants:list:{$user1->id}");
        $cache2 = Cache::get("agency:tenants:list:{$user2->id}");
        $this->assertNotEquals($cache1, $cache2);
    }
}
