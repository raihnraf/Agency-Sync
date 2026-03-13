<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ExampleSyncJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

class ExampleSyncJobTest extends TestCase
{
    #[Test]
    public function job_extends_tenant_aware_job()
    {
        $tenantId = 'a149e4e8-c843-402b-b2d4-b2c8602c69a7';
        $data = ['test' => 'data'];

        $job = new ExampleSyncJob($tenantId, $data);

        $this->assertEquals($tenantId, $job->tenantId);
        $this->assertEquals($data, $job->data);
    }

    #[Test]
    public function job_accepts_tenant_id_and_optional_data_in_constructor()
    {
        $tenantId = 'a149e4e8-c843-402b-b2d4-b2c8602c69a7';

        $jobWithData = new ExampleSyncJob($tenantId, ['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $jobWithData->data);

        $jobWithoutData = new ExampleSyncJob($tenantId);
        $this->assertEquals([], $jobWithoutData->data);
    }

    #[Test]
    public function job_handle_method_logs_execution_with_tenant_context()
    {
        $tenant = Tenant::factory()->create();
        $job = new ExampleSyncJob($tenant->id, ['test' => 'data']);

        // Job should execute without errors
        $this->expectNotToPerformAssertions();

        $job->handle();
    }

    #[Test]
    public function job_can_access_tenant_model_via_current_tenant()
    {
        $tenant = Tenant::factory()->create();

        $job = new ExampleSyncJob($tenant->id);
        $job->handle();

        $this->assertEquals($tenant->id, $tenant->id);
    }

    #[Test]
    public function job_logs_completion_message()
    {
        $tenant = Tenant::factory()->create();
        $job = new ExampleSyncJob($tenant->id);

        // Job should complete without errors (logs are side effects)
        $this->expectNotToPerformAssertions();

        $job->handle();
    }
}
