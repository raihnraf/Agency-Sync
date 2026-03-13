<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout_and_invalidate_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(204);

        // Verify token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'api-token',
        ]);
    }

    public function test_logout_requires_authentication()
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_logout_invalidates_token_only_for_current_device()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        // Logout with token1
        $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer ' . $token1,
        ])->assertStatus(204);

        // token1 should no longer work
        $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer ' . $token1,
        ])->assertStatus(401);

        // token2 should still work
        $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer ' . $token2,
        ])->assertStatus(204);
    }
}
