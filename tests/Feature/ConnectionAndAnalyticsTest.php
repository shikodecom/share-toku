<?php

namespace Tests\Feature;

use App\Models\Placement;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\Site;
use App\Models\SiteAccessToken;
use App\Models\SiteAuthorizationCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\EventToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectionAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Test', 'owner_user_id' => $user->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'name' => 'Blog', 'domain' => 'example.com']);

        return [$user, $workspace, $site];
    }

    public function test_pkce_exchange_is_one_time_and_token_is_hashed(): void
    {
        [$user, , $site] = $this->fixture();
        $verifier = str_repeat('a', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirect = 'https://example.com/wp-admin/admin-post.php?action=sharetoku_callback';
        $params = ['site_public_id' => $site->public_id, 'redirect_uri' => $redirect, 'site_home_url' => 'https://example.com/', 'state' => str_repeat('b', 32),
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
        $this->actingAs($user)->postJson('/site-connections/authorize', array_replace($params, ['redirect_uri' => 'https://example.com/other']))->assertUnprocessable();
        $this->actingAs($user)->postJson('/site-connections/authorize', array_replace($params, ['site_home_url' => 'https://evil.example/']))->assertUnprocessable();
        $response = $this->actingAs($user)->post('/site-connections/authorize', $params);
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $code = $query['code'];
        $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => str_repeat('z', 43), 'redirect_uri' => $redirect])->assertStatus(400);
        $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => 'https://example.com/other'])->assertStatus(400);
        $exchange = $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $redirect]);
        $exchange->assertOk()->assertJsonPath('site.plan', 'free');
        $token = $exchange->json('access_token');
        $this->assertDatabaseHas('site_access_tokens', ['token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('site_access_tokens', ['token_hash' => $token]);
        $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $redirect])->assertStatus(400);
        $this->withToken($token)->getJson('/api/v1/site/me')->assertOk();
        SiteAccessToken::first()->update(['revoked_at' => now()]);
        $this->withToken($token)->getJson('/api/v1/site/me')->assertUnauthorized();
    }

    public function test_event_token_validation_dedupe_and_path_sanitization(): void
    {
        [$user, $workspace, $site] = $this->fixture();
        $site->update(['status' => 'active']);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'S', 'slug' => 's']);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'P']);
        $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'referral_program_id' => $program->id,
            'referral_code' => 'A', 'created_by_user_id' => $user->id]);
        $placement = Placement::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id,
            'owner_referral_offer_id' => $offer->id, 'placement_key' => 'one']);
        $token = app(EventToken::class)->issue(['site' => $site->public_id, 'placement' => $placement->public_id, 'owner' => $offer->public_id, 'operator' => null])['token'];
        $body = ['event_token' => $token, 'events' => [['event_id' => (string) Str::ulid(), 'type' => 'impression', 'slot' => 'owner',
            'occurred_at' => now()->toIso8601String(), 'page_path' => '/article?secret=1']]];
        $this->postJson('/api/v1/events/batch', $body)->assertOk()->assertJsonPath('accepted', 1);
        $this->postJson('/api/v1/events/batch', $body)->assertOk()->assertJsonPath('accepted', 0);
        $this->assertDatabaseHas('analytics_events', ['page_path' => '/article']);
        $body['events'][0]['slot'] = 'operator';
        $this->postJson('/api/v1/events/batch', $body)->assertUnprocessable();
        $this->postJson('/api/v1/events/batch', ['event_token' => $token.'x', 'events' => $body['events']])->assertUnauthorized();
    }

    public function test_daily_aggregation_is_idempotent(): void
    {
        [$user, $workspace, $site] = $this->fixture();
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'S', 'slug' => 's']);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'P']);
        $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'referral_program_id' => $program->id,
            'referral_code' => 'A', 'created_by_user_id' => $user->id]);
        $placement = Placement::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id,
            'owner_referral_offer_id' => $offer->id, 'placement_key' => 'one']);
        DB::table('analytics_events')->insert(['event_id' => (string) Str::ulid(), 'occurred_at' => now()->subDay(), 'received_at' => now(),
            'event_type' => 'impression', 'site_id' => $site->id, 'placement_id' => $placement->id, 'referral_offer_id' => $offer->id,
            'slot' => 'owner', 'created_at' => now()]);
        $this->artisan('analytics:aggregate')->assertSuccessful();
        $this->artisan('analytics:aggregate')->assertSuccessful();
        $this->assertDatabaseCount('daily_metrics', 1);
        $this->assertDatabaseHas('daily_metrics', ['site_id' => $site->id, 'impressions' => 1, 'clicks' => 0]);
        DB::table('analytics_events')->insert(['event_id' => (string) Str::ulid(), 'occurred_at' => now()->subDay(), 'received_at' => now(),
            'event_type' => 'click', 'site_id' => $site->id, 'placement_id' => $placement->id, 'referral_offer_id' => $offer->id,
            'slot' => 'owner', 'created_at' => now()]);
        $this->artisan('analytics:aggregate')->assertSuccessful();
        $this->assertDatabaseHas('daily_metrics', ['site_id' => $site->id, 'impressions' => 1, 'clicks' => 1]);
    }

    public function test_site_token_rate_limit_returns_error_contract(): void
    {
        [$user, , $site] = $this->fixture();
        $site->update(['status' => 'active']);
        $raw = bin2hex(random_bytes(32));
        SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'token_hash' => hash('sha256', $raw),
            'scopes_json' => ['site:read'], 'created_by_user_id' => $user->id, 'created_at' => now()]);
        config(['sharetoku.rate_limit_site_per_minute' => 1]);
        $this->withToken($raw)->getJson('/api/v1/site/me')->assertOk();
        $this->withToken($raw)->getJson('/api/v1/site/me')->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_domain_change_invalidates_pending_code(): void
    {
        [$user, $workspace, $site] = $this->fixture();
        $code = bin2hex(random_bytes(32));
        $verifier = str_repeat('a', 43);
        $redirect = 'https://example.com/wp-admin/admin-post.php?action=sharetoku_callback';
        SiteAuthorizationCode::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'user_id' => $user->id,
            'code_hash' => hash('sha256', $code), 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'redirect_uri' => $redirect, 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);
        $this->actingAs($user)->patchJson('/workspaces/'.$workspace->public_id.'/sites/'.$site->public_id, ['domain' => 'new.example.com'])
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->assertNotNull(SiteAuthorizationCode::first()->consumed_at);
        $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $redirect])->assertStatus(400);
    }

    public function test_pending_site_can_share_domain_but_cannot_connect_over_active_site(): void
    {
        [, , $site] = $this->fixture();
        $site->update(['status' => 'active']);
        $second = User::factory()->create();
        $otherWorkspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Other', 'owner_user_id' => $second->id]);
        WorkspaceMember::create(['workspace_id' => $otherWorkspace->id, 'user_id' => $second->id, 'role' => 'administrator']);
        $otherSite = $this->actingAs($second)->postJson('/workspaces/'.$otherWorkspace->public_id.'/sites',
            ['name' => 'Other blog', 'domain' => 'https://example.com/article'])->assertCreated()->json();
        $verifier = str_repeat('a', 43);
        $redirect = 'https://example.com/wp-admin/admin-post.php?action=sharetoku_callback';
        $code = bin2hex(random_bytes(32));
        SiteAuthorizationCode::create(['public_id' => (string) Str::ulid(), 'site_id' => Site::where('public_id', $otherSite['public_id'])->firstOrFail()->id,
            'user_id' => $second->id, 'code_hash' => hash('sha256', $code),
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'redirect_uri' => $redirect, 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);
        $this->postJson('/api/v1/site-connections/exchange', ['code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $redirect])
            ->assertStatus(409)->assertJsonPath('error.code', 'domain_in_use');
        $this->assertSame('pending', Site::where('public_id', $otherSite['public_id'])->firstOrFail()->status);
    }
}
