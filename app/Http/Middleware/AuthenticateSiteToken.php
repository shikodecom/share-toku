<?php

namespace App\Http\Middleware;

use App\Models\SiteAccessToken;
use Closure;
use Illuminate\Http\Request;

class AuthenticateSiteToken
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        $raw = $request->bearerToken();
        if (! $raw || ! preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return $this->error('invalid_token', 401);
        }
        $token = SiteAccessToken::with('site.workspace')->where('token_hash', hash('sha256', $raw))->first();
        if (! $token || $token->revoked_at || ($token->expires_at && $token->expires_at->isPast())) {
            return $this->error('invalid_token', 401);
        }
        if (! in_array($scope, $token->scopes_json, true)) {
            return $this->error('insufficient_scope', 403);
        }
        if (! $token->site || $token->site->status !== 'active' || ! $token->site->workspace) {
            return $this->error('site_unavailable', 403);
        }
        $request->attributes->set('site', $token->site);
        $request->attributes->set('site_token', $token);
        $token->update(['last_used_at' => now()]);

        return $next($request);
    }

    private function error(string $code, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => 'Site authorization failed.']], $status);
    }
}
