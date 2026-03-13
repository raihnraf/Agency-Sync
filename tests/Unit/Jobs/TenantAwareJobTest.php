<?php

namespace Tests\Unit\Jobs;

use App\Jobs\TenantAwareJob;
use App\Queue\Middleware\SetTenantContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantAwareJobTest extends TestCase
{
    #[Test]
    public function job_accepts_tenant_id_in_constructor()
    {
        $job = new class(42) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals(42, $job->tenantId);
    }

    #[Test]
    public function job_sets_queue_to_sync_by_default()
    {
        $job = new class(1) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals('sync', $job->queue);
    }

    #[Test]
    public function job_has_backoff_method_returning_exponential_delays()
    {
        $job = new class(1) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals([10, 30, 90], $job->backoff());
    }

    #[Test]
    public function job_has_tries_property_set_to_3()
    {
        $job = new class(1) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals(3, $job->tries);
    }

    #[Test]
    public function job_has_timeout_property_set_to_120()
    {
        $job = new class(1) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals(120, $job->timeout);
    }

    #[Test]
    public function job_middleware_returns_set_tenant_context_instance()
    {
        $job = new class(1) extends TenantAwareJob {
            public function handle(): void
            {
                // Test implementation
            }
        };

        $middleware = $job->middleware();

        $this->assertIsArray($middleware);
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(SetTenantContext::class, $middleware[0]);
    }
}
