<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs in the iPhone app's requests from an `Authorization: Bearer` token.
 * The user is set on the default guard, so every existing controller that
 * reads Auth::id() / Auth::user() works unchanged for app requests.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findByPlain($plain) : null;

        if (! $token || ! $token->user || ! $token->user->email_verified_at) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        // Avoid a write on every request — once a minute is plenty.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
