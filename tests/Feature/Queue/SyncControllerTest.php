<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ExampleSyncJob;
use PHPUnit\Framework\Attributes\Test;

class SyncControllerTest extends TestCase
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
    public function post_sync_dispatches_example_sync_job()
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(202);

        Queue::assertPushed(ExampleSyncJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id;
        });
    }

    #[Test]
    public function endpoint_returns_202_accepted_immediately()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(202);
    }

    #[Test]
    public function response_includes_job_id_and_status_pending()
    {
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
    }

    #[Test]
    public function endpoint_requires_authentication()
    {
        $response = $this->postJson('/api/v1/sync/dispatch', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function endpoint_validates_tenant_id_exists_in_request()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => 'non-existent-uuid',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function endpoint_accepts_optional_data_array()
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/dispatch', [
                'tenant_id' => $this->tenant->id,
                'data' => ['key' => 'value'],
            ]);

        $response->assertStatus(202);

        Queue::assertPushed(ExampleSyncJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id
                && $job->data === ['key' => 'value'];
        });
    }
}
