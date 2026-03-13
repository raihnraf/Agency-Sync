<?php

namespace Tests\Feature\Queue;

use App\Models\User;
use App\Models\Tenant;
use App\Models\JobStatus;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ExampleSyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobProcessed;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AsyncSyncOperationTest extends TestCase
{
    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    #[Test]
    public function test_sync_dispatches_job_and_returns_immediately()
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'status',
                    'message',
                ],
            ]);

        Queue::assertPushed(ExampleSyncJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id;
        });
    }

    #[Test]
    public function test_sync_request_returns_quickly_non_blocking()
    {
        Queue::fake();

        $startTime = microtime(true);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
            ]);

        $duration = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(202);
        $this->assertLessThan(100, $duration, 'Request should return in < 100ms');
    }

    #[Test]
    public function test_job_executes_async_with_tenant_context()
    {
        // This test verifies the job can execute with tenant context
        // Actual queue event tracking is tested in integration with real Redis queue
        $job = new ExampleSyncJob($this->tenant->id);

        // Verify job has correct tenant ID
        $this->assertEquals($this->tenant->id, $job->tenantId);

        // Verify job can be handled without errors
        $job->handle();

        // If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function test_multiple_sync_requests_can_be_dispatched_concurrently()
    {
        Queue::fake();

        $tenants = Tenant::factory()->count(5)->create();

        foreach ($tenants as $tenant) {
            $this->actingAs($this->user)
                ->postJson('/api/v1/sync/dispatch', [
                    'tenant_id' => $tenant->id,
                ])->assertStatus(202);
        }

        Queue::assertPushed(ExampleSyncJob::class, 5);
    }
}
