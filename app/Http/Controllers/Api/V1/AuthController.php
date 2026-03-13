<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token', ['*'], now()->addHours(4));

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
            'meta' => [
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Login user and return token.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => [
                    ['field' => 'email', 'message' => 'Invalid credentials'],
                ]
            ], 401);
        }

        $token = $user->createToken('api-token', ['*'], now()->addHours(4));

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
            'meta' => [
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Logout user and invalidate current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
