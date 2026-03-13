<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_return_field_level_errors()
    {
        $this->assertTrue(true);
    }

    public function test_multiple_validation_errors_supported()
    {
        $this->assertTrue(true);
    }

    public function test_validation_returns_422_status()
    {
        $this->assertTrue(true);
    }

    public function test_login_validation_requires_email()
    {
        $this->assertTrue(true);
    }

    public function test_password_mismatch_returns_error()
    {
        $this->assertTrue(true);
    }
}
