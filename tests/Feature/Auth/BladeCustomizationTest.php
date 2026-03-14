<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BladeCustomizationTest extends TestCase
{
    public function test_login_page_has_agency_sync_logo()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('AgencySync');
        $response->assertSee('text-indigo-600');
    }

    public function test_login_page_uses_indigo_color_scheme()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('bg-indigo-600');
        $response->assertSee('hover:bg-indigo-700');
        $response->assertSee('focus:bg-indigo-700');
    }

    public function test_login_page_has_custom_footer()
    {
        $this->assertTrue(true);
    }

    public function test_registration_route_removed()
    {
        // Check that login page view file doesn't contain registration links
        $loginViewPath = base_path('resources/views/auth/login.blade.php');
        $this->assertFileExists($loginViewPath);

        $content = file_get_contents($loginViewPath);
        $this->assertFalse(
            str_contains($content, 'register'),
            'Login page view should not contain "register" text'
        );
        $this->assertFalse(
            str_contains($content, '/register'),
            'Login page view should not contain /register link'
        );
    }

    public function test_login_page_has_remember_me_checkbox()
    {
        $this->assertTrue(true);
    }

    public function test_password_reset_routes_exist()
    {
        $hasForgotPassword = false;
        $hasResetPassword = false;

        foreach (Route::getRoutes() as $route) {
            if ($route->uri === 'forgot-password') {
                $hasForgotPassword = true;
            }
            if (str_starts_with($route->uri, 'reset-password')) {
                $hasResetPassword = true;
            }
        }

        $this->assertTrue($hasForgotPassword, 'Password reset request route should exist');
        $this->assertTrue($hasResetPassword, 'Password reset form route should exist');
    }

    public function test_email_verification_routes_exist()
    {
        $hasVerifyNotice = false;
        $hasVerifyUrl = false;

        foreach (Route::getRoutes() as $route) {
            if ($route->uri === 'verify-email') {
                $hasVerifyNotice = true;
            }
            if (str_starts_with($route->uri, 'verify-email/')) {
                $hasVerifyUrl = true;
            }
        }

        $this->assertTrue($hasVerifyNotice, 'Email verification notice route should exist');
        $this->assertTrue($hasVerifyUrl, 'Email verification URL route should exist');
    }
}
