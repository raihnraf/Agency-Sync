<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class HttpStatusCodesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful registration returns 201.
     */
    public function test_successful_registration_returns_201()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test201@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                ],
                'meta' => [
                    'expires_at',
                ],
            ]);
    }

    /**
     * Test successful login returns 200.
     */
    public function test_successful_login_returns_200()
    {
        // Create a user first
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                ],
                'meta' => [
                    'expires_at',
                ],
            ]);
    }

    /**
     * Test successful logout returns 204.
     */
    public function test_successful_logout_returns_204()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/logout');

        $response->assertStatus(204)
            ->assertContent('');
    }

    /**
     * Test validation error returns 422.
     */
    public function test_validation_error_returns_422()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    ['field', 'message'],
                    ['field', 'message'],
                    ['field', 'message'],
                ],
            ])
            ->assertJsonPath('errors.0.field', 'name')
            ->assertJsonPath('errors.1.field', 'email')
            ->assertJsonPath('errors.2.field', 'password');
    }

    /**
     * Test authentication error returns 401.
     */
    public function test_authentication_error_returns_401()
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    /**
     * Test invalid credentials return 401.
     */
    public function test_invalid_credentials_return_401()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors.0.field', 'email')
            ->assertJsonPath('errors.0.message', 'Invalid credentials');
    }

    /**
     * Test not found endpoint returns 404.
     */
    public function test_not_found_endpoint_returns_404()
    {
        $response = $this->postJson('/api/v1/nonexistent', [
            'data' => 'test',
        ]);

        $response->assertStatus(404);
    }
}
