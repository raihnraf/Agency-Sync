<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegistrationRoutesRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_get_route_does_not_exist()
    {
        $hasRegisterGet = false;
        foreach (Route::getRoutes() as $route) {
            if ($route->uri === 'register' && in_array('GET', $route->methods)) {
                $hasRegisterGet = true;
                break;
            }
        }

        $this->assertFalse($hasRegisterGet, 'GET /register route should not exist');
    }

    public function test_registration_post_route_does_not_exist()
    {
        $hasRegisterPost = false;
        foreach (Route::getRoutes() as $route) {
            if ($route->uri === 'register' && in_array('POST', $route->methods)) {
                $hasRegisterPost = true;
                break;
            }
        }

        $this->assertFalse($hasRegisterPost, 'POST /register route should not exist');
    }

    public function test_api_registration_route_still_exists()
    {
        $hasApiRegister = false;
        foreach (Route::getRoutes() as $route) {
            if (str_contains($route->uri, 'register') && in_array('POST', $route->methods)) {
                $hasApiRegister = true;
                break;
            }
        }

        $this->assertTrue($hasApiRegister, 'API registration route should still exist');
    }
}
