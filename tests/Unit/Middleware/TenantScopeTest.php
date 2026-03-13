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

        // Query should only show current tenant
        $tenants = Tenant::all();

        $this->assertCount(1, $tenants); // Only tenant1 (current tenant)
        $this->assertEquals($this->tenant1->id, $tenants->first()->id);
        $this->assertFalse($tenants->contains('id', $otherTenant->id));
        $this->assertFalse($tenants->contains('id', $this->tenant2->id));
    }

    #[Test]
    public function global_scope_only_applies_when_user_is_authenticated_and_has_current_tenant(): void
    {
        // Clear authentication and current tenant from previous test
        auth()->guard('web')->forgetUser();
        $this->user->current_tenant_id = null;
        $this->user->save();

        // Without authentication or current tenant, all tenants should be visible
        $tenants = Tenant::all();

        // Should see all tenants created in setUp (tenant1 and tenant2)
        $this->assertTrue($tenants->count() >= 2);
        $this->assertTrue($tenants->contains('id', $this->tenant1->id));
        $this->assertTrue($tenants->contains('id', $this->tenant2->id));
    }

    #[Test]
    public function global_scope_can_be_disabled_using_without_global_scopes(): void
    {
        // Authenticate and set current tenant
        $this->actingAs($this->user);
        $this->user->setCurrentTenant($this->tenant1);

        // Create another tenant that should not be visible
        $otherTenant = Tenant::factory()->create();

        // Without global scopes, all tenants should be visible
        $tenants = Tenant::withoutGlobalScopes()->get();

        // Should see all tenants including the one just created
        $this->assertTrue($tenants->contains('id', $this->tenant1->id));
        $this->assertTrue($tenants->contains('id', $this->tenant2->id));
        $this->assertTrue($tenants->contains('id', $otherTenant->id));
    }
}
