<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteAuthorizationCode;
use App\Models\SiteConsent;
use App\Services\Audit;
use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function index(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);

        return response()->json($w->sites()->with('consents')->paginate(30));
    }

    public function store(Request $request, string $workspace, WorkspaceAccess $access, EntitlementService $entitlements)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        if (! $entitlements->canCreateSite($w)) {
            throw ValidationException::withMessages(['limit' => 'Site limit reached.']);
        }
        $data = $request->validate(['name' => 'required|string|max:255', 'domain' => 'required|string|max:253']);
        $domain = $this->normalizeDomain($data['domain']);
        if (Site::where('domain', $domain)->where('workspace_id', $w->id)->exists()) {
            throw ValidationException::withMessages(['domain' => 'Domain is already registered.']);
        }

        return response()->json(Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'name' => $data['name'], 'domain' => $domain]), 201);
    }

    public function update(Request $request, string $workspace, string $site, WorkspaceAccess $access, Audit $audit)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'domain' => 'sometimes|required|string|max:253']);
        if (isset($data['domain'])) {
            $data['domain'] = $this->normalizeDomain($data['domain']);
            if ($data['domain'] !== $s->domain) {
                if (Site::where('domain', $data['domain'])->where('workspace_id', $w->id)->whereKeyNot($s->id)->exists()) {
                    throw ValidationException::withMessages(['domain' => 'Domain is already registered.']);
                }
                $data['verified_at'] = null;
                $data['status'] = 'pending';
                $s->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                SiteAuthorizationCode::where('site_id', $s->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            }
        }
        $before = $s->only('name', 'domain', 'status');
        $s->update($data);
        $audit->record($request->user()->id, $w->id, $s, 'update', $before, $s->only('name', 'domain', 'status'));

        return response()->json($s);
    }

    public function consent(Request $request, string $workspace, string $site, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();
        $data = $request->validate(['terms_version' => 'required|string|max:50', 'accepted' => 'accepted']);
        if ($data['terms_version'] !== config('sharetoku.consent_version')) {
            throw ValidationException::withMessages(['terms_version' => 'Terms version is outdated.']);
        }
        SiteConsent::where('site_id', $s->id)->whereNull('withdrawn_at')->update(['withdrawn_at' => now()]);

        return response()->json(SiteConsent::create(['site_id' => $s->id, 'user_id' => $request->user()->id, 'terms_version' => $data['terms_version'], 'consented_at' => now()]), 201);
    }

    public function withdraw(Request $request, string $workspace, string $site, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['administrator']);
        $s = $w->sites()->where('public_id', $site)->firstOrFail();
        $s->consents()->whereNull('withdrawn_at')->update(['withdrawn_at' => now()]);

        return response()->noContent();
    }

    public function suspend(Request $request, string $site, WorkspaceAccess $access, Audit $audit)
    {
        $access->requireSystemAdmin($request->user());
        $request->validate(['reason' => 'required|string|max:2000']);
        $s = Site::where('public_id', $site)->firstOrFail();
        $before = $s->status;
        $s->update(['status' => 'suspended']);
        $s->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        SiteAuthorizationCode::where('site_id', $s->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $audit->record($request->user()->id, $s->workspace_id, $s, 'suspend', ['status' => $before], ['status' => 'suspended']);

        return response()->json(['status' => 'suspended']);
    }

    private function normalizeDomain(string $input): string
    {
        $value = trim($input);
        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            if (! preg_match('~^https?://~i', $value)) {
                throw ValidationException::withMessages(['domain' => 'Invalid domain.']);
            }
            $value = preg_replace('~^https?://~i', '', $value);
        }
        if (! preg_match('~^([^/?#]+)~u', $value, $matches)) {
            throw ValidationException::withMessages(['domain' => 'Invalid domain.']);
        }

        $host = preg_replace('/:\d+$/', '', $matches[1]);
        if (str_contains($host, ':') || str_contains($host, '@')) {
            throw ValidationException::withMessages(['domain' => 'Invalid domain.']);
        }
        $host = rtrim($host, '.');
        $domain = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($domain === false || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw ValidationException::withMessages(['domain' => 'Invalid domain.']);
        }

        return $domain;
    }
}
