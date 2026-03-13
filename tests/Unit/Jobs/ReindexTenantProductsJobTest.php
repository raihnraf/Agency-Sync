<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ReindexTenantProductsJob;
use App\Jobs\TenantAwareJob;
use Tests\TestCase;

/**
 * Unit tests for ReindexTenantProductsJob
 * 
 * @group reindex-tenant-products-job
 */
class ReindexTenantProductsJobTest extends TestCase
{
    public function test_job_extends_tenant_aware_job(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertInstanceOf(TenantAwareJob::class, $job);
    }

    public function test_job_stores_tenant_id(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertEquals('tenant-456', $job->tenantId);
    }

    public function test_job_stores_job_status_id(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456', 'job-status-789');
        
        $this->assertEquals('job-status-789', $job->jobStatusId);
    }

    public function test_job_uses_indexing_queue(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertEquals('indexing', $job->queue);
    }

    public function test_job_has_single_try_for_bulk_operations(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertEquals(1, $job->tries);
    }

    public function test_job_has_long_timeout_for_large_catalogs(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertEquals(3600, $job->timeout);
    }

    public function test_job_has_exponential_backoff(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $backoff = $job->backoff();
        
        $this->assertIsArray($backoff);
        $this->assertEquals([10, 30, 90], $backoff);
    }

    public function test_job_can_be_created_without_job_status(): void
    {
        $job = new ReindexTenantProductsJob('tenant-456');
        
        $this->assertNull($job->jobStatusId);
    }
}
