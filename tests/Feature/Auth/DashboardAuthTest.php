<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_home_redirects_guest_users_to_login()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_tenants_list_redirects_guest_users_to_login()
    {
        $response = $this->get('/dashboard/tenants');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_tenant_detail_redirects_guest_users_to_login()
    {
        $response = $this->get('/dashboard/tenants/fake-id');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard_home()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
    }

    public function test_authenticated_user_can_access_tenants_list()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/dashboard/tenants');
        $response->assertOk();
    }
}
