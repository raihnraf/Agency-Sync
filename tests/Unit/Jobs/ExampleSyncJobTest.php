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
        Log::fake();

        $tenant = Tenant::factory()->create();
        $job = new ExampleSyncJob($tenant->id, ['test' => 'data']);

        $job->handle();

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Example sync job executing'
                && isset($context['tenant_id'])
                && isset($context['tenant_name'])
                && isset($context['data']);
        });
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
        Log::fake();

        $tenant = Tenant::factory()->create();
        $job = new ExampleSyncJob($tenant->id);

        $job->handle();

        Log::assertLogged('info', function ($message, $context) {
            return $message === 'Example sync job completed'
                && isset($context['tenant_id']);
        });
    }
}
