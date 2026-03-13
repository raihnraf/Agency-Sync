<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_return_field_level_errors()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    ['field', 'message'],
                ],
            ])
            ->assertJsonCount(3, 'errors');
    }

    public function test_multiple_validation_errors_supported()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonCount(3, 'errors')
            ->assertJsonPath('errors.0.field', fn ($field) => in_array($field, ['name', 'email', 'password']))
            ->assertJsonPath('errors.1.field', fn ($field) => in_array($field, ['name', 'email', 'password']))
            ->assertJsonPath('errors.2.field', fn ($field) => in_array($field, ['name', 'email', 'password']));
    }

    public function test_validation_returns_422_status()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'password')
            ->assertJsonPath('errors.0.message', fn ($msg) => $msg !== null);
    }

    public function test_login_validation_requires_email()
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'email');
    }

    public function test_password_mismatch_returns_error()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'password')
            ->assertJsonPath('errors.0.message', fn ($msg) => str_contains($msg, 'confirmation'));
    }
}
