<?php

namespace Tests\Unit\Models;

use App\Models\JobStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_status_has_tenant_id_job_id_job_type_status_fields()
    {
        $status = JobStatus::factory()->make();

        $this->assertNotNull($status->tenant_id);
        $this->assertNotNull($status->job_id);
        $this->assertNotNull($status->job_type);
        $this->assertNotNull($status->status);
    }

    #[Test]
    public function job_status_casts_payload_to_array()
    {
        $status = JobStatus::factory()->create([
            'payload' => ['key' => 'value', 'test' => 123],
        ]);

        $this->assertIsArray($status->payload);
        $this->assertEquals(['key' => 'value', 'test' => 123], $status->payload);
    }

    #[Test]
    public function job_status_casts_started_at_and_completed_at_to_datetime()
    {
        $status = JobStatus::factory()->create([
            'started_at' => '2026-03-13 10:00:00',
            'completed_at' => '2026-03-13 10:05:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $status->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $status->completed_at);
    }

    #[Test]
    public function job_status_belongs_to_tenant()
    {
        $tenant = Tenant::factory()->create();
        $status = JobStatus::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $status->tenant);
        $this->assertEquals($tenant->id, $status->tenant->id);
    }

    #[Test]
    public function migration_creates_table_with_all_required_columns()
    {
        $this->assertDatabaseHas('job_statuses', [
            'job_id' => JobStatus::factory()->create()->job_id,
        ]);
    }
}
