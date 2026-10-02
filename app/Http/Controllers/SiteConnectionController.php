<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteAccessToken;
use App\Models\SiteAuthorizationCode;
use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteConnectionController extends Controller
{
    private function validateAuthorization(Request $request): array
    {
        return $request->validate(['site_public_id' => 'required|string', 'redirect_uri' => 'required|url|max:2048', 'site_home_url' => 'required|url|max:2048',
            'state' => 'required|string|min:32|max:256', 'code_challenge' => 'required|regex:/^[A-Za-z0-9_-]{43}$/',
            'code_challenge_method' => 'required|in:S256']);
    }

    private function site(Request $request, array $data, WorkspaceAccess $access, bool $lock = false): Site
    {
        $site = Site::with('workspace')->where('public_id', $data['site_public_id'])->firstOrFail();
        if ($lock) {
            $site = Site::with('workspace')->lockForUpdate()->findOrFail($site->id);
        }
        $access->requireRole($request->user(), $site->workspace, ['administrator']);
        if ($site->status === 'suspended') {
            throw ValidationException::withMessages(['site' => 'Site is suspended.']);
        }
        $parts = parse_url($data['redirect_uri']);
        if (! is_array($parts)) {
            throw ValidationException::withMessages(['redirect_uri' => 'Invalid redirect URI.']);
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $home = parse_url($data['site_home_url']);
        parse_str($parts['query'] ?? '', $query);
        if ($host !== $site->domain || ($scheme !== 'https' && ! ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)))
            || ! str_ends_with($parts['path'] ?? '', '/wp-admin/admin-post.php') || $query !== ['action' => 'sharetoku_callback']
            || ! is_array($home) || strtolower($home['host'] ?? '') !== $site->domain
            || (strtolower($home['scheme'] ?? '') !== 'https' && ! (strtolower($home['scheme'] ?? '') === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)))
            || isset($home['user']) || isset($home['pass'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages(['redirect_uri' => 'Redirect URI does not match site domain.']);
        }

        return $site;
    }

    public function authorizeForm(Request $request, WorkspaceAccess $access)
    {
        $data = $this->validateAuthorization($request);
        $site = $this->site($request, $data, $access);

        return view('site-authorize', ['site' => $site, 'params' => $data]);
    }

    public function authorizeSite(Request $request, WorkspaceAccess $access)
    {
        $data = $this->validateAuthorization($request);

        return DB::transaction(function () use ($request, $data, $access) {
            $site = $this->site($request, $data, $access, true);
            $rawCode = bin2hex(random_bytes(32));
            SiteAuthorizationCode::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'user_id' => $request->user()->id,
                'code_hash' => hash('sha256', $rawCode), 'code_challenge' => $data['code_challenge'], 'redirect_uri' => $data['redirect_uri'],
                'state_hash' => hash('sha256', $data['state']), 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);
            $separator = str_contains($data['redirect_uri'], '?') ? '&' : '?';

            return redirect()->away($data['redirect_uri'].$separator.http_build_query(['code' => $rawCode, 'state' => $data['state']], '', '&', PHP_QUERY_RFC3986));
        }, 3);
    }

    public function exchange(Request $request, EntitlementService $entitlements)
    {
        $data = $request->validate(['code' => 'required|string|size:64', 'code_verifier' => 'required|regex:/^[A-Za-z0-9._~-]{43,128}$/', 'redirect_uri' => 'required|url|max:2048']);

        return DB::transaction(function () use ($data, $entitlements) {
            // Non-locking discovery only. Re-read the grant after all Site locks.
            $hint = SiteAuthorizationCode::where('code_hash', hash('sha256', $data['code']))->first();
            if (! $hint) {
                return response()->json(['error' => ['code' => 'invalid_grant', 'message' => 'Authorization code is invalid.']], 400);
            }
            $siteHint = Site::find($hint->site_id);
            if (! $siteHint) {
                return response()->json(['error' => ['code' => 'site_unavailable', 'message' => 'Site unavailable.']], 403);
            }
            // Explicit primary-key reads guarantee acquisition order; ORDER BY on
            // a domain query alone does not guarantee InnoDB's scan/lock order.
            $ids = Site::where('domain', $siteHint->domain)->pluck('id')->push($siteHint->id)->unique()->sort();
            $sameDomain = collect();
            foreach ($ids as $id) {
                $locked = Site::lockForUpdate()->find($id);
                if ($locked) {
                    $sameDomain->push($locked);
                }
            }
            // All paths use Site (ascending ID) -> AuthorizationCode -> Token.
            $record = SiteAuthorizationCode::whereKey($hint->id)->lockForUpdate()->first();
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $data['code_verifier'], true)), '+/', '-_'), '=');
            if (! $record || $record->consumed_at || $record->expires_at->isPast() || ! hash_equals($record->code_challenge, $challenge)
                || ! hash_equals($record->redirect_uri, $data['redirect_uri'])) {
                return response()->json(['error' => ['code' => 'invalid_grant', 'message' => 'Authorization code is invalid.']], 400);
            }
            $site = $sameDomain->firstWhere('id', $record->site_id);
            if (! $site || $site->status === 'suspended' || strtolower(parse_url($record->redirect_uri, PHP_URL_HOST) ?? '') !== $site->domain) {
                return response()->json(['error' => ['code' => 'site_unavailable', 'message' => 'Site unavailable.']], 403);
            }
            if ($sameDomain->contains(fn (Site $other) => $other->id !== $site->id && $other->domain === $site->domain && $other->status === 'active')) {
                return response()->json(['error' => ['code' => 'domain_in_use', 'message' => 'Domain is already connected.']], 409);
            }
            $record->update(['consumed_at' => now()]);
            $site->update(['status' => 'active', 'verified_at' => now()]);
            $token = bin2hex(random_bytes(32));
            SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'token_hash' => hash('sha256', $token),
                'scopes_json' => ['site:read', 'placements:read', 'events:write'], 'created_by_user_id' => $record->user_id, 'created_at' => now()]);

            return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'site' => ['public_id' => $site->public_id,
                'domain' => $site->domain, 'plan' => $entitlements->plan($site->workspace)], 'scopes' => ['site:read', 'placements:read', 'events:write']]);
        }, 3);
    }

    public function revoke(Request $request, string $workspace, string $site, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();
        $s->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return response()->noContent();
    }

    public function disconnect(Request $request)
    {
        $request->attributes->get('site_token')->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
