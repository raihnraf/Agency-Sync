<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JsonResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_consistent_json_structure()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
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

    public function test_success_response_includes_data_field()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.token', fn ($token) => $token !== null)
            ->assertJsonPath('meta.expires_at', fn ($date) => $date !== null);
    }

    public function test_error_response_includes_errors_array()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errors', fn ($errors) => is_array($errors))
            ->assertJsonPath('errors.0.field', fn ($field) => $field !== null)
            ->assertJsonPath('errors.0.message', fn ($message) => $message !== null);
    }

    public function test_no_content_response_has_no_body()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/logout');

        $response->assertStatus(204)
            ->assertContent('');
    }
}
