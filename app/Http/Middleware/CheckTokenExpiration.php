<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (!$token) {
            return $next($request);
        }

        // Update last_used_at timestamp
        $token->last_used_at = now();
        $token->save();

        // Check if token has been inactive for 4+ hours
        // Use created_at as fallback if last_used_at is null
        $lastActivity = $token->last_used_at ?? $token->created_at;

        if ($lastActivity->lt(now()->subHours(4))) {
            // Delete expired token
            $token->delete();

            return response()->json([
                'errors' => [
                    ['message' => 'Token expired due to inactivity']
                ]
            ], 401);
        }

        return $next($request);
    }
}
