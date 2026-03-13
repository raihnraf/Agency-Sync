<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_expires_after_4_hours_inactivity()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        // Manually set token's last_used_at to 5 hours ago
        // Use raw update to bypass any model events
        PersonalAccessToken::where('id', $tokenId)->update([
            'last_used_at' => now()->subHours(5),
        ]);

        // Make request with expired token
        $response = $this->withToken($plainTextToken)->getJson('/api/v1/me');

        $response->assertStatus(401)
                 ->assertJsonPath('errors.0.message', 'Token expired due to inactivity');

        // Verify token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    public function test_active_usage_prevents_expiration()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        // Set last_used_at to 3 hours ago (within 4-hour window)
        $tokenModel = PersonalAccessToken::find($token->accessToken->id);
        $tokenModel->last_used_at = now()->subHours(3);
        $tokenModel->save();

        // Make request (should succeed)
        $response = $this->withToken($plainTextToken)->getJson('/api/v1/me');

        $response->assertStatus(200);

        // Verify last_used_at was updated to recent timestamp
        $tokenModel->refresh();
        $this->assertGreaterThan(
            now()->subMinute(),
            $tokenModel->last_used_at,
            'last_used_at should be updated to recent timestamp'
        );
    }

    public function test_expired_token_returns_401()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        // Manually expire token by setting created_at to 5 hours ago and last_used_at to null
        // Use raw update to bypass any model events
        PersonalAccessToken::where('id', $tokenId)->update([
            'created_at' => now()->subHours(5),
            'last_used_at' => null,
        ]);

        // Make authenticated request
        $response = $this->withToken($plainTextToken)->getJson('/api/v1/me');

        $response->assertStatus(401)
                 ->assertJsonStructure([
                     'errors' => [
                         '*' => ['message']
                     ]
                 ]);
    }

    public function test_multiple_tokens_independent_expiration()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create 2 tokens (simulating 2 devices)
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');

        $plainTextToken1 = $token1->plainTextToken;
        $plainTextToken2 = $token2->plainTextToken;
        $token1Id = $token1->accessToken->id;
        $token2Id = $token2->accessToken->id;

        // Expire token1 by setting created_at to 5 hours ago
        // Use raw update to bypass any model events
        PersonalAccessToken::where('id', $token1Id)->update([
            'created_at' => now()->subHours(5),
            'last_used_at' => null,
        ]);

        // Verify token1 is deleted when used
        $this->withToken($plainTextToken1)->getJson('/api/v1/me')->assertStatus(401);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1Id,
        ]);

        // Verify token2 still exists and works
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2Id,
        ]);

        $this->withToken($plainTextToken2)->getJson('/api/v1/me')->assertStatus(200);
    }

    public function test_logout_revokes_only_current_device_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create user with 2 tokens
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');

        $plainTextToken1 = $token1->plainTextToken;
        $plainTextToken2 = $token2->plainTextToken;

        // Logout using token1
        $this->withToken($plainTextToken1)->postJson('/api/v1/logout')->assertStatus(204);

        // Verify token1 deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id,
        ]);

        // Verify token2 still works
        $this->withToken($plainTextToken2)->getJson('/api/v1/me')->assertStatus(200);
    }
}
