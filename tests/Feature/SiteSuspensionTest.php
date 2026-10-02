<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteAccessToken;
use App\Models\SiteAuthorizationCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_domain_change_still_requires_verification_and_revokes_tokens(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Blog', 'owner_user_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'administrator']);
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id,
            'name' => 'Blog', 'domain' => 'old.example.com', 'status' => 'active', 'verified_at' => now()]);
        $token = SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id,
            'token_hash' => hash('sha256', 'test-token'), 'scopes_json' => ['site:read'], 'created_by_user_id' => $owner->id, 'created_at' => now()]);
        $this->actingAs($owner)->patchJson('/workspaces/'.$workspace->public_id.'/sites/'.$site->public_id, ['domain' => 'new.example.com'])
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->assertNull($site->fresh()->verified_at);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_owner_updates_cannot_reactivate_a_suspended_site(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Blog', 'owner_user_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => 'administrator']);
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id,
            'name' => 'Blog', 'domain' => 'old.example.com', 'status' => 'active', 'verified_at' => now()]);
        $raw = bin2hex(random_bytes(32));
        $token = SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id,
            'token_hash' => hash('sha256', $raw), 'scopes_json' => ['site:read'], 'created_by_user_id' => $owner->id, 'created_at' => now()]);
        $verifier = str_repeat('a', 43);
        $code = bin2hex(random_bytes(32));
        $redirect = 'https://new.example.com/wp-admin/admin-post.php?action=sharetoku_callback';
        $authorization = SiteAuthorizationCode::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id,
            'user_id' => $owner->id, 'code_hash' => hash('sha256', $code),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'redirect_uri' => $redirect, 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);

        $this->actingAs($owner)->postJson('/admin/sites/'.$site->public_id.'/suspend', ['reason' => 'Stop'])->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/sites/'.$site->public_id.'/suspend', ['reason' => 'Stop'])->assertOk();
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNotNull($authorization->fresh()->consumed_at);

        $path = '/workspaces/'.$workspace->public_id.'/sites/'.$site->public_id;
        $this->actingAs($owner)->patchJson($path, ['name' => 'Renamed'])->assertOk()->assertJsonPath('status', 'suspended');
        $this->patchJson($path, ['domain' => 'old.example.com'])->assertOk()->assertJsonPath('status', 'suspended');
        $this->patchJson($path, ['domain' => 'new.example.com', 'status' => 'active'])->assertOk()->assertJsonPath('status', 'suspended');
        $this->assertNull($site->fresh()->verified_at);
        $this->assertSame('new.example.com', $site->fresh()->domain);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $site->id, 'action' => 'suspend']);

        $params = ['site_public_id' => $site->public_id, 'redirect_uri' => $redirect, 'site_home_url' => 'https://new.example.com/',
            'state' => str_repeat('b', 32), 'code_challenge' => $authorization->code_challenge, 'code_challenge_method' => 'S256'];
        $this->getJson('/site-connections/authorize?'.http_build_query($params))->assertUnprocessable()->assertJsonValidationErrors('site');
        $this->postJson('/site-connections/authorize', $params)->assertUnprocessable()->assertJsonValidationErrors('site');
        $exchange = ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $redirect];
        $this->postJson('/api/v1/site-connections/exchange', $exchange)->assertStatus(400)->assertJsonPath('error.code', 'invalid_grant');
        $authorization->update(['consumed_at' => null]);
        $this->postJson('/api/v1/site-connections/exchange', $exchange)->assertForbidden()->assertJsonPath('error.code', 'site_unavailable');
        $this->withToken($raw)->getJson('/api/v1/site/me')->assertUnauthorized();
        $token->update(['revoked_at' => null]);
        $this->withToken($raw)->getJson('/api/v1/site/me')->assertForbidden()->assertJsonPath('error.code', 'site_unavailable');
        $this->assertSame('suspended', $site->fresh()->status);
        $this->assertDatabaseCount('site_access_tokens', 1);
    }
}
