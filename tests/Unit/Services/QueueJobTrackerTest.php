<?php

namespace Tests\Unit\Services;

use App\Models\JobStatus;
use App\Models\Tenant;
use App\Services\QueueJobTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Queue\Job as JobContract;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueJobTrackerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function track_creates_job_status_with_pending_status()
    {
        $tenant = Tenant::factory()->create();
        $tracker = app(QueueJobTracker::class);
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn('test-job-id');
        $job->shouldReceive('payload')->andReturn(['test' => 'data']);
        $job->tenantId = $tenant->id;

        $status = $tracker->track($job, 'TestJob');

        $this->assertInstanceOf(JobStatus::class, $status);
        $this->assertEquals('test-job-id', $status->job_id);
        $this->assertEquals($tenant->id, $status->tenant_id);
        $this->assertEquals('TestJob', $status->job_type);
        $this->assertEquals('pending', $status->status);
    }

    #[Test]
    public function track_stores_job_payload()
    {
        $tenant = Tenant::factory()->create();
        $tracker = app(QueueJobTracker::class);
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn('test-job-id');
        $job->shouldReceive('payload')->andReturn(['key' => 'value', 'test' => 123]);
        $job->tenantId = $tenant->id;

        $status = $tracker->track($job, 'TestJob');

        $this->assertEquals(['key' => 'value', 'test' => 123], $status->payload);
    }

    #[Test]
    public function mark_as_running_updates_status_to_running()
    {
        $status = JobStatus::factory()->create(['status' => 'pending']);
        $tracker = app(QueueJobTracker::class);
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn($status->job_id);

        $tracker->markAsRunning($job);

        $status->refresh();
        $this->assertEquals('running', $status->status);
        $this->assertNotNull($status->started_at);
    }

    #[Test]
    public function mark_as_completed_updates_status_to_completed()
    {
        $status = JobStatus::factory()->create(['status' => 'running']);
        $tracker = app(QueueJobTracker::class);
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn($status->job_id);

        $tracker->markAsCompleted($job);

        $status->refresh();
        $this->assertEquals('completed', $status->status);
        $this->assertNotNull($status->completed_at);
    }

    #[Test]
    public function mark_as_failed_updates_status_with_error_message()
    {
        $status = JobStatus::factory()->create(['status' => 'running']);
        $tracker = app(QueueJobTracker::class);
        $job = Mockery::mock(JobContract::class);
        $job->shouldReceive('getJobId')->andReturn($status->job_id);
        $exception = new \Exception('Test error message');

        $tracker->markAsFailed($job, $exception);

        $status->refresh();
        $this->assertEquals('failed', $status->status);
        $this->assertEquals('Test error message', $status->error_message);
        $this->assertNotNull($status->completed_at);
    }
}
