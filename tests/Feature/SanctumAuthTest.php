<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_logs_route_requires_sanctum_authentication(): void
    {
        $response = $this->getJson('/api/v1/sync-logs');
        $response->assertUnauthorized();
    }

    public function test_sync_logs_details_route_requires_sanctum_authentication(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $syncLog = SyncLog::factory()->for($tenant)->create();

        $response = $this->getJson("/api/v1/sync-logs/{$syncLog->id}/details");
        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_sync_logs_via_api_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/sync-logs');
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_sync_logs(): void
    {
        $response = $this->getJson('/api/v1/sync-logs');
        $response->assertUnauthorized();
    }

    public function test_web_routes_do_not_have_sync_log_endpoints(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // This route should NOT exist in web.php
        $response = $this->get('/dashboard/api/v1/sync-logs');
        $response->assertNotFound();
    }
}
