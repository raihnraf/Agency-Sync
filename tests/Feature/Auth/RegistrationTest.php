<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_get_route_does_not_exist(): void
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

    public function test_registration_post_route_does_not_exist(): void
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
}
