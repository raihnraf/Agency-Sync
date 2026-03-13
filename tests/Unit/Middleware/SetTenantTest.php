<?php

namespace Tests\Unit\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SetTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
        $this->user->tenants()->attach($this->tenant, ['role' => 'admin', 'joined_at' => now()]);
    }

    #[Test]
    public function middleware_extracts_x_tenant_id_from_request_header(): void
    {
        $request = Request::create('/api/v1/test');
        $request->headers->set('X-Tenant-ID', $this->tenant->id);
        $request->setUserResolver(fn () => $this->user);

        $middleware = new SetTenant();
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function missing_x_tenant_id_header_returns_422_with_explicit_error(): void
    {
        $request = Request::create('/api/v1/test');
        $request->setUserResolver(fn () => $this->user);

        $middleware = new SetTenant();
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals([
            'errors' => [
                ['field' => 'X-Tenant-ID', 'message' => 'X-Tenant-ID header is required'],
            ],
        ], json_decode($response->getContent(), true));
    }

    #[Test]
    public function valid_tenant_id_that_belongs_to_user_sets_tenant_context(): void
    {
        $request = Request::create('/api/v1/test');
        $request->headers->set('X-Tenant-ID', $this->tenant->id);
        $request->setUserResolver(fn () => $this->user);

        $middleware = new SetTenant();
        $response = $middleware->handle($request, function ($req) {
            $this->assertEquals($this->tenant->id, $req->attributes->get('current_tenant')->id);
            $this->assertEquals($this->tenant->id, $this->user->fresh()->current_tenant_id);

            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function invalid_tenant_id_returns_404_with_generic_error(): void
    {
        $request = Request::create('/api/v1/test');
        $request->headers->set('X-Tenant-ID', 'non-existent-id');
        $request->setUserResolver(fn () => $this->user);

        $middleware = new SetTenant();
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals([
            'errors' => [
                ['message' => 'Tenant not found or access denied'],
            ],
        ], json_decode($response->getContent(), true));
    }

    #[Test]
    public function tenant_not_associated_with_user_returns_404_with_generic_error(): void
    {
        $otherTenant = Tenant::factory()->create();

        $request = Request::create('/api/v1/test');
        $request->headers->set('X-Tenant-ID', $otherTenant->id);
        $request->setUserResolver(fn () => $this->user);

        $middleware = new SetTenant();
        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals([
            'errors' => [
                ['message' => 'Tenant not found or access denied'],
            ],
        ], json_decode($response->getContent(), true));
    }
}
