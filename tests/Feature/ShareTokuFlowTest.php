<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\Site;
use App\Models\SiteAccessToken;
use App\Models\SiteConsent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShareTokuFlowTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(User $user): Workspace
    {
        $w = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Test', 'owner_user_id' => $user->id]);
        WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $user->id, 'role' => 'administrator']);

        return $w;
    }

    private function offer(Workspace $w, User $user, string $status = 'published'): ReferralOffer
    {
        $category = Category::create(['public_id' => (string) Str::ulid(), 'name' => 'Finance', 'slug' => 'finance-'.Str::random(8)]);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'service-'.Str::random(8)]);
        $service->categories()->attach($category);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'Program',
            'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved']);

        return ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'referral_program_id' => $program->id,
            'referral_code' => 'CODE', 'status' => $status, 'created_by_user_id' => $user->id]);
    }

    public function test_health_and_registration_workspace(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        config(['app.debug' => false]);
        $this->getJson('/api/v1/missing')->assertNotFound()->assertDontSee('trace');
        $this->postJson('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'])
            ->assertCreated()->assertJsonPath('workspace.name', 'A のWorkspace');
        $this->assertDatabaseHas('workspace_members', ['role' => 'administrator']);
        $this->get('/dashboard')->assertOk()->assertSee('Workspace');
    }

    public function test_workspace_boundary_and_offer_validation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $aw = $this->workspace($a);
        $bw = $this->workspace($b);
        $offer = $this->offer($bw, $b);
        $this->actingAs($a)->getJson('/workspaces/'.$bw->public_id.'/offers/'.$offer->public_id)->assertForbidden();
        $this->actingAs($a)->getJson('/workspaces/'.$aw->public_id.'/offers')->assertOk();
        $this->actingAs($a)->postJson('/workspaces/'.$aw->public_id.'/offers', [
            'referral_program_id' => $offer->referral_program_id, 'referral_url' => 'javascript:alert(1)'])->assertUnprocessable();
    }

    public function test_distribution_uses_site_workspace_and_consent(): void
    {
        $owner = User::factory()->create();
        $operatorUser = User::factory()->create();
        $w = $this->workspace($owner);
        $operator = $this->workspace($operatorUser);
        $offer = $this->offer($w, $owner);
        $candidate = $this->offer($operator, $operatorUser);
        config(['sharetoku.operator_workspace_public_id' => $operator->public_id]);
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'name' => 'Blog', 'domain' => 'example.com', 'status' => 'active']);
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('除外 Category');
        $raw = bin2hex(random_bytes(32));
        SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'token_hash' => hash('sha256', $raw),
            'scopes_json' => ['site:read', 'placements:read'], 'created_by_user_id' => $owner->id, 'created_at' => now()]);
        $payload = ['owner_offer_id' => $offer->public_id, 'placement_key' => 'post-1'];
        $headers = ['Authorization' => 'Bearer '.$raw];
        $this->withHeaders($headers)->getJson('/api/v1/site/offers')->assertOk()->assertJsonPath('data.0.public_id', $offer->public_id);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->assertJsonPath('operator_offer', null);
        SiteConsent::create(['site_id' => $site->id, 'user_id' => $owner->id, 'terms_version' => config('sharetoku.consent_version'), 'consented_at' => now()]);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->assertJsonPath('operator_offer', null);
        $candidate->program->service->categories()->attach($offer->program->service->categories->first()->id);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()
            ->assertJsonPath('operator_offer.public_id', $candidate->public_id)->assertJsonPath('owner_offer.public_id', $offer->public_id);
        $categoryId = $offer->program->service->categories->first()->id;
        DB::table('site_category_exclusions')->insert(['site_id' => $site->id, 'category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->assertJsonPath('operator_offer', null);
        DB::table('site_category_exclusions')->where('site_id', $site->id)->delete();
        config(['sharetoku.operator_offers_enabled' => false]);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->assertJsonPath('operator_offer', null);
        config(['sharetoku.operator_offers_enabled' => true]);
        $offer->update(['ends_at' => now()->addSeconds(120)]);
        $ttl = $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->json('cache.ttl_seconds');
        $this->assertLessThanOrEqual(120, $ttl);
        $offer->update(['ends_at' => null]);
        $subscription = WorkspaceSubscription::create(['workspace_id' => $w->id, 'plan_code' => 'pro', 'status' => 'active', 'starts_at' => now()]);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()
            ->assertJsonPath('plan', 'pro')->assertJsonPath('operator_offer', null);
        $subscription->update(['status' => 'cancelled']);
        $site->consents()->whereNull('withdrawn_at')->update(['withdrawn_at' => now()]);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', $payload)->assertOk()->assertJsonPath('operator_offer', null);
        $this->withHeaders($headers)->postJson('/api/v1/placements/resolve', ['owner_offer_id' => $candidate->public_id, 'placement_key' => 'post-1'])->assertNotFound();
        $this->assertDatabaseCount('placements', 1);
    }

    public function test_revoked_token_and_suspended_offer_are_rejected(): void
    {
        $user = User::factory()->create();
        $w = $this->workspace($user);
        $offer = $this->offer($w, $user, 'suspended');
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'name' => 'Blog', 'domain' => 'example.com', 'status' => 'active']);
        $raw = bin2hex(random_bytes(32));
        $token = SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'token_hash' => hash('sha256', $raw),
            'scopes_json' => ['placements:read'], 'created_by_user_id' => $user->id, 'created_at' => now()]);
        $this->withToken($raw)->postJson('/api/v1/placements/resolve', ['owner_offer_id' => $offer->public_id, 'placement_key' => 'one'])->assertNotFound();
        $token->update(['revoked_at' => now()]);
        $this->withToken($raw)->postJson('/api/v1/placements/resolve', ['owner_offer_id' => $offer->public_id, 'placement_key' => 'one'])->assertUnauthorized();
    }

    public function test_site_domain_is_normalized_for_url_and_idn(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspace($user);
        $this->actingAs($user)->postJson('/workspaces/'.$workspace->public_id.'/sites', [
            'name' => 'Blog', 'domain' => 'HTTPS://BÜCHER.EXAMPLE./article',
        ])->assertCreated()->assertJsonPath('domain', 'xn--bcher-kva.example');
        $this->actingAs($user)->postJson('/workspaces/'.$workspace->public_id.'/sites', [
            'name' => 'Duplicate', 'domain' => 'xn--bcher-kva.example',
        ])->assertUnprocessable();
    }
}
