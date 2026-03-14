<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group Authentication
 *
 * API endpoints for user authentication and token management
 */
class AuthController extends BaseController
{
    /**
     * Register a new user.
     *
     * Creates a new user account and returns an authentication token.
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: secret123
     *
     * @response {
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     },
     *     "token": "plainTextTokenHere"
     *   },
     *   "meta": {
     *     "expires_at": "2026-03-15T12:00:00Z"
     *   }
     * }
     * @response 422 {
     *   "errors": [
     *     {
     *       "field": "email",
     *       "message": "The email has already been taken."
     *     }
     *   ]
     * }
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
     *
     * Authenticates user credentials and returns a Sanctum token.
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: secret123
     *
     * @response {
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     },
     *     "token": "plainTextTokenHere"
     *   },
     *   "meta": {
     *     "expires_at": "2026-03-15T12:00:00Z"
     *   }
     * }
     * @response 401 {
     *   "errors": [
     *     {
     *       "field": "email",
     *       "message": "Invalid credentials"
     *     }
     *   ]
     * }
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
     *
     * Invalidates the authentication token used for this request.
     *
     * @authenticated
     *
     * @response 204
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /**
     * Get authenticated user details.
     *
     * Returns the currently authenticated user's information.
     *
     * @authenticated
     *
     * @responseField data.user.id string User UUID
     * @responseField data.user.name string User's full name
     * @responseField data.user.email string User's email address
     *
     * @response {
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "name": "John Doe",
     *       "email": "john@example.com"
     *     }
     *   }
     * }
     */
    public function me(Request $request)
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ], 200);
    }
}
