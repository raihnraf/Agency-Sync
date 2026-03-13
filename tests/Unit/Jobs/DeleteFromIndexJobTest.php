<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DeleteFromIndexJob;
use App\Jobs\TenantAwareJob;
use Tests\TestCase;

/**
 * Unit tests for DeleteFromIndexJob
 * 
 * @group delete-from-index-job
 */
class DeleteFromIndexJobTest extends TestCase
{
    public function test_job_extends_tenant_aware_job(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertInstanceOf(TenantAwareJob::class, $job);
    }

    public function test_job_stores_product_id(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertEquals('product-123', $job->productId);
    }

    public function test_job_stores_tenant_id(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertEquals('tenant-456', $job->tenantId);
    }

    public function test_job_uses_indexing_queue(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertEquals('indexing', $job->queue);
    }

    public function test_job_has_correct_tries(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertEquals(3, $job->tries);
    }

    public function test_job_has_correct_timeout(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $this->assertEquals(120, $job->timeout);
    }

    public function test_job_has_exponential_backoff(): void
    {
        $job = new DeleteFromIndexJob('product-123', 'tenant-456');
        
        $backoff = $job->backoff();
        
        $this->assertIsArray($backoff);
        $this->assertEquals([10, 30, 90], $backoff);
    }
}
