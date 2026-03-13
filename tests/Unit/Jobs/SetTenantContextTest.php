<?php

namespace Tests\Unit\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\Tenant;
use App\Queue\Middleware\SetTenantContext;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetTenantContextTest extends TestCase
{
    #[Test]
    public function middleware_restores_tenant_from_tenant_id_property()
    {
        $tenant = Tenant::factory()->create();

        $job = new class($tenant->id) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $middleware = new SetTenantContext();
        $nextCalled = false;

        $middleware->handle($job, function ($job) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals($job->tenantId, Tenant::currentTenant()?->id);
        });

        $this->assertTrue($nextCalled, 'Next closure was not called');
    }

    #[Test]
    public function middleware_calls_tenant_set_current_tenant()
    {
        $tenant = Tenant::factory()->create();

        $job = new class($tenant->id) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $middleware = new SetTenantContext();

        $middleware->handle($job, function ($job) use ($tenant) {
            $this->assertNotNull(Tenant::currentTenant());
            $this->assertEquals($tenant->id, Tenant::currentTenant()->id);
        });
    }

    #[Test]
    public function middleware_clears_tenant_context_after_job_execution()
    {
        $tenant = Tenant::factory()->create();

        $job = new class($tenant->id) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $middleware = new SetTenantContext();

        $middleware->handle($job, function ($job) {
            // Tenant context should be set during job
            $this->assertNotNull(Tenant::currentTenant());
        });

        // Tenant context should be cleared after job
        $this->assertNull(Tenant::currentTenant());
        $this->assertFalse(app()->bound('currentTenant'));
    }

    #[Test]
    public function middleware_handles_jobs_without_tenant_id_gracefully()
    {
        $job = new \stdClass();

        $middleware = new SetTenantContext();
        $nextCalled = false;

        $middleware->handle($job, function ($job) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertNull(Tenant::currentTenant());
        });

        $this->assertTrue($nextCalled, 'Next closure was not called');
    }
}
