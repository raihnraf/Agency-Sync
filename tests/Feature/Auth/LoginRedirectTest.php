<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_dashboard_redirects_to_login()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_tenant_routes_redirect_to_login()
    {
        $response = $this->get('/dashboard/tenants');

        $response->assertRedirect('/login');
    }

    public function test_login_redirects_to_dashboard_after_success()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_logout_redirects_to_home()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
    }

    public function test_logout_destroys_session()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        // Logout
        $this->post('/logout');

        // Verify user is no longer authenticated
        $this->assertGuest('web');
    }

    public function test_after_logout_dashboard_requires_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        // Logout
        $this->post('/logout');

        // Try to access dashboard
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_logout_complete_flow()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Login
        $loginResponse = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect('/dashboard');

        // Verify authenticated
        $this->assertAuthenticated();

        // Logout
        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/');

        // Verify session destroyed
        $this->assertGuest('web');

        // Verify dashboard requires login
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertRedirect('/login');
    }
}
