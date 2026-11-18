<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken() ?: $request->header('x-api-key');
        $key = $plain ? ApiKey::findByPlain($plain) : null;
        if (! $key) return response()->json(['error' => ['code' => 'unauthorized', 'message' => 'Missing or invalid API key']], 401);
        if ($key->ip_whitelist && ! in_array($request->ip(), $key->ip_whitelist, true)) {
            return response()->json(['error' => ['code' => 'ip_not_allowed', 'message' => 'This IP (' . $request->ip() . ') is not on the key\'s whitelist']], 403);
        }
        $user = $key->user;
        if ($user->status === 'suspended') return response()->json(['error' => ['code' => 'account_suspended', 'message' => 'Account suspended']], 403);
        if ($key->mode === 'live' && ($why = $user->verifyBlockReason())) return response()->json(['error' => ['code' => 'account_not_approved', 'message' => $why]], 403);
        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_key', $key);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
