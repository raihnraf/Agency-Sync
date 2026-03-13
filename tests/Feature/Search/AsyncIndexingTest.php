<?php

namespace Tests\Feature\Search;

use App\Jobs\ReindexTenantProductsJob;
use App\Models\JobStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for Async Indexing
 * 
 * Tests QUEUE-07 requirement:
 * - QUEUE-07: Reindex operations run asynchronously via queue
 * 
 * @group async-indexing
 */
class AsyncIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user and tenant
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Store',
            'platform_type' => 'shopify',
            'platform_url' => 'https://test-store.myshopify.com',
            'status' => 'active',
        ]);

        // Associate user with tenant
        $this->user->tenants()->attach($this->tenant->id, ['role' => 'admin']);

        // Create token
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_reindex_dispatch_returns_immediately(): void
    {
        Queue::fake();

        $startTime = microtime(true);
        
        $response = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reindex");

        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

        $response->assertStatus(202);
        $this->assertLessThan(100, $duration, 'Response should be returned in less than 100ms');
    }

    public function test_reindex_creates_job_status(): void
    {
        Queue::fake();

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reindex");

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'status',
                    'message',
                    'tenant_id',
                ],
                'meta',
            ]);

        $jobId = $response->json('data.job_id');

        // Verify job status was created in database
        $this->assertDatabaseHas('job_statuses', [
            'id' => $jobId,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'job_type' => 'reindex_tenant_products',
        ]);
    }

    public function test_reindex_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reindex");

        $response->assertStatus(202);

        Queue::assertPushed(ReindexTenantProductsJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id;
        });
    }

    public function test_reindex_endpoint_requires_authentication(): void
    {
        Queue::fake();

        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/reindex");

        $response->assertStatus(401);
    }

    public function test_reindex_validates_tenant_access(): void
    {
        Queue::fake();

        // Create another tenant not associated with user
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Store',
            'platform_type' => 'shopify',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$otherTenant->id}/reindex");

        $response->assertStatus(404);
    }

    public function test_status_endpoint_returns_job_status(): void
    {
        Queue::fake();

        // Create a job status
        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'payload' => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/jobs/{$jobStatus->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.job_id', $jobStatus->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.job_type', 'reindex_tenant_products')
            ->assertJsonStructure([
                'data' => [
                    'job_id',
                    'status',
                    'job_type',
                    'payload',
                    'error_message',
                    'created_at',
                    'started_at',
                    'completed_at',
                ],
            ]);
    }

    public function test_status_endpoint_requires_authentication(): void
    {
        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'payload' => [],
        ]);

        $response = $this->getJson("/api/v1/jobs/{$jobStatus->id}/status");

        $response->assertStatus(401);
    }

    public function test_status_endpoint_validates_tenant_access(): void
    {
        // Create another tenant not associated with user
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Store',
            'platform_type' => 'shopify',
        ]);

        $jobStatus = JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $otherTenant->id,
            'status' => 'pending',
            'payload' => [],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/jobs/{$jobStatus->id}/status");

        $response->assertStatus(404);
    }

    public function test_list_jobs_endpoint_returns_recent_jobs(): void
    {
        // Create some job statuses
        JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'payload' => [],
        ]);

        JobStatus::create([
            'job_id' => (string) \Illuminate\Support\Str::uuid(),
            'job_type' => 'reindex_tenant_products',
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'payload' => [],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/jobs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'jobs',
                ],
                'meta',
            ])
            ->assertJsonCount(2, 'data.jobs');
    }

    public function test_concurrent_reindex_jobs_supported(): void
    {
        Queue::fake();

        // Create second tenant
        $tenant2 = Tenant::factory()->create([
            'name' => 'Second Store',
            'platform_type' => 'shopify',
        ]);
        $this->user->tenants()->attach($tenant2->id, ['role' => 'admin']);

        // Dispatch jobs for both tenants
        $response1 = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reindex");
        
        $response2 = $this->withToken($this->token)
            ->postJson("/api/v1/tenants/{$tenant2->id}/reindex");

        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Verify both jobs were dispatched
        Queue::assertPushed(ReindexTenantProductsJob::class, 2);
    }
}
