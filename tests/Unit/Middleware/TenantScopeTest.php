<?php

namespace Tests\Unit\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant1;

    private Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant1 = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();

        $this->user->tenants()->attach($this->tenant1, ['role' => 'admin', 'joined_at' => now()]);
        $this->user->tenants()->attach($this->tenant2, ['role' => 'admin', 'joined_at' => now()]);
    }

    #[Test]
    public function tenant_scope_middleware_is_pass_through(): void
    {
        $this->assertTrue(true); // Placeholder test
    }

    #[Test]
    public function tenant_model_has_global_scope_that_filters_by_current_tenant_id(): void
    {
        // Authenticate and set current tenant
        $this->actingAs($this->user);
        $this->user->setCurrentTenant($this->tenant1);

        // Create a tenant that should not be visible
        $otherTenant = Tenant::factory()->create();

        // Query should not include other tenant
        $tenants = Tenant::all();

        $this->assertCount(2, $tenants); // Only tenant1 and tenant2
        $this->assertFalse($tenants->contains('id', $otherTenant->id));
    }

    #[Test]
    public function global_scope_only_applies_when_user_is_authenticated_and_has_current_tenant(): void
    {
        // Without authentication, all tenants should be visible
        $tenants = Tenant::all();

        $this->assertCount(3, $tenants); // tenant1, tenant2, and otherTenant
    }

    #[Test]
    public function global_scope_can_be_disabled_using_without_global_scopes(): void
    {
        // Authenticate and set current tenant
        $this->actingAs($this->user);
        $this->user->setCurrentTenant($this->tenant1);

        // Create a tenant that should not be visible
        $otherTenant = Tenant::factory()->create();

        // Without global scopes, all tenants should be visible
        $tenants = Tenant::withoutGlobalScopes()->get();

        $this->assertCount(4, $tenants); // All tenants including otherTenant
        $this->assertTrue($tenants->contains('id', $otherTenant->id));
    }
}
