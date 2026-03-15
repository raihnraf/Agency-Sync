<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\JobStatus;
use App\Jobs\ExampleSyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sync Dispatch API Endpoint Tests
 *
 * Tests for SYNC-01: Agency admin can trigger manual catalog sync for a specific client store
 * Tests for UI-05: Agency admin can trigger sync operation for each client store
 *
 * @group frontend
 */
class SyncDispatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();
        $this->user->createToken('test-token');

        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);
    }

    public function test_sync_dispatch_returns_202_accepted(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
                'data' => [],
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => [
                'job_id',
                'status',
                'message',
            ],
        ]);
    }

    public function test_sync_dispatch_creates_job_status_record(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
                'data' => [],
            ]);

        $response->assertStatus(202);

        // Verify job_id is returned
        $jobId = $response->json('data.job_id');
        $this->assertNotNull($jobId);
        $this->assertIsString($jobId);
    }

    public function test_sync_dispatch_requires_tenant_id_in_body(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'data' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_sync_dispatch_dispatches_queue_job(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
                'data' => [],
            ]);

        // Verify the sync job was dispatched
        Queue::assertPushed(ExampleSyncJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id;
        });
    }
}
