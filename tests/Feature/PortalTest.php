<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspacePublicProfile;
use App\Services\PortalQuery;
use App\Services\Publishability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): ReferralOffer
    {
        $user = User::factory()->create(['email' => 'secret@example.com', 'name' => 'Private Name']);
        $w = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Internal Workspace', 'owner_user_id' => $user->id]);
        WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $user->id, 'role' => 'administrator']);
        WorkspacePublicProfile::create(['workspace_id' => $w->id, 'public_slug' => 'author', 'display_name' => 'Public Author', 'is_public' => true]);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Example Service', 'slug' => 'example', 'description' => 'Cloud storage']);
        $category = Category::create(['public_id' => (string) Str::ulid(), 'name' => 'Technology', 'slug' => 'tech']);
        $service->categories()->attach($category);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'Welcome Program', 'public_listing_policy' => 'approved', 'external_distribution_policy' => 'prohibited', 'invitee_benefit_text' => 'Free month']);

        return ReferralOffer::create(['public_id' => 'PUBLIC-A', 'workspace_id' => $w->id, 'referral_program_id' => $program->id, 'status' => 'published', 'referral_code' => 'SECRET-CODE', 'referral_url' => 'https://example.com/invite?x=1', 'created_by_user_id' => $user->id]);
    }

    public function test_search_category_service_detail_and_privacy(): void
    {
        $offer = $this->fixture();
        foreach (['Example', 'Cloud', 'Technology', 'Welcome'] as $search) {
            $this->get('/services?q='.$search)->assertOk()->assertSee('Example Service')->assertSee('公開特典 1件');
        }
        $this->get('/services?q=SECRET-CODE')->assertOk()->assertDontSee('Example Service');
        $this->get('/categories/tech')->assertOk()->assertSee('Example Service');
        $this->get('/services?category=missing')->assertOk()->assertDontSee('Example Service');
        $this->get('/categories/missing')->assertNotFound();
        foreach (['/', '/services/example', '/offers/PUBLIC-A', '/u/author'] as $path) {
            $this->get($path)->assertOk()->assertSee('Public Author')->assertDontSee('secret@example.com')->assertDontSee('Private Name')->assertDontSee('Internal Workspace')->assertDontSee($offer->workspace->public_id);
        }
        $this->get('/offers/PUBLIC-A')->assertSee('SECRET-CODE')->assertSee('https://example.com/invite?x=1', false)->assertSee('copy-code')->assertSee('紹介者にも特典が発生する場合があります');
        $this->assertFalse(app(Publishability::class)->offers(true)->exists());
        $this->assertTrue(app(PortalQuery::class)->offers()->exists());
    }

    public function test_unavailable_offers_and_master_records_are_excluded(): void
    {
        $offer = $this->fixture();
        foreach (['draft', 'rejected', 'suspended', 'expired', 'pending_review'] as $status) {
            $offer->update(['status' => $status]);
            $this->get('/offers/PUBLIC-A')->assertNotFound();
            $this->assertFalse(app(PortalQuery::class)->offers()->exists());
        }
        $offer->update(['status' => 'published']);
        foreach (['starts_at' => now()->addDay(), 'ends_at' => now()->subDay()] as $field => $date) {
            $offer->update([$field => $date]);
            $this->get('/offers/PUBLIC-A')->assertNotFound();
            $offer->update([$field => null]);
        }
        foreach (['prohibited', 'suspended', 'needs_review'] as $policy) {
            $offer->program->update(['public_listing_policy' => $policy]);
            $this->get('/offers/PUBLIC-A')->assertNotFound();
        }
        $offer->program->update(['public_listing_policy' => 'approved']);
        foreach (['starts_at' => now()->addDay(), 'ends_at' => now()->subDay(), 'is_active' => false] as $field => $value) {
            $offer->program->update([$field => $value]);
            $this->get('/offers/PUBLIC-A')->assertNotFound();
            $offer->program->update([$field => $field === 'is_active' ? true : null]);
        }
        $offer->workspace->publicProfile->update(['is_public' => false]);
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $this->get('/u/author')->assertNotFound();
        $offer->workspace->publicProfile->update(['is_public' => true]);
        $offer->program->service->update(['is_active' => false]);
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $offer->program->service->update(['is_active' => true]);
        $offer->program->delete();
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $this->get('/services/example')->assertOk()->assertSee('現在公開中の紹介特典はありません');
    }

    public function test_escape_unsafe_links_and_stable_order(): void
    {
        $offer = $this->fixture();
        $offer->update(['title' => '<script>alert(1)</script>', 'referral_code' => '<img src=x onerror=alert(1)>', 'referral_url' => 'javascript:alert(1)']);
        $offer->workspace->publicProfile->update(['website_url' => 'data:text/html,test', 'bio' => '<script>bad</script>']);
        $offer->program->service->update(['official_url' => 'javascript:bad']);
        $offer->program->update(['official_terms_url' => 'javascript:terms']);
        $this->get('/offers/PUBLIC-A')->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:', false)->assertSee('<img src=x onerror=alert(1)>');
        $this->get('/services/example')->assertOk()->assertDontSee('javascript:', false);
        $this->get('/u/author')->assertOk()->assertDontSee('data:text', false)->assertDontSee('<script>bad</script>', false);
        foreach (['PUBLIC-C', 'PUBLIC-B'] as $id) {
            $clone = $offer->replicate();
            $clone->public_id = $id;
            $clone->last_verified_at = now();
            $clone->save();
        }
        $this->assertSame(['PUBLIC-B', 'PUBLIC-C', 'PUBLIC-A'], app(PortalQuery::class)->offers()->pluck('public_id')->all());
    }

    public function test_profile_management_authorization_validation_and_withdrawal(): void
    {
        $offer = $this->fixture();
        $path = '/workspaces/'.$offer->workspace->public_id.'/public-profile';
        $this->get($path)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put($path, [])->assertForbidden();
        $this->actingAs(User::findOrFail($offer->workspace->owner_user_id))->get($path)->assertOk();
        $this->put($path, ['public_slug' => 'author', 'display_name' => 'New Name', 'is_public' => false])->assertRedirect($path);
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $this->putJson($path, ['public_slug' => 'author', 'display_name' => 'Name', 'is_public' => true, 'website_url' => 'javascript:bad'])->assertUnprocessable();
        $other = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Other', 'owner_user_id' => $offer->workspace->owner_user_id]);
        WorkspacePublicProfile::create(['workspace_id' => $other->id, 'public_slug' => 'taken', 'display_name' => 'Taken']);
        $this->putJson($path, ['public_slug' => 'taken', 'display_name' => 'Name', 'is_public' => true])->assertUnprocessable();
    }

    public function test_unconfigured_profiles_and_deleted_workspaces_are_not_public(): void
    {
        $offer = $this->fixture();
        $offer->workspace->publicProfile->delete();
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $this->get('/services/example')->assertOk()->assertSee('現在公開中の紹介特典はありません');
        WorkspacePublicProfile::create(['workspace_id' => $offer->workspace_id, 'public_slug' => 'author', 'display_name' => 'Author']);
        $this->get('/u/author')->assertNotFound();
        $offer->workspace->publicProfile()->update(['is_public' => true]);
        $offer->workspace->delete();
        $this->get('/offers/PUBLIC-A')->assertNotFound();
        $this->get('/u/author')->assertNotFound();
    }

    public function test_zero_overrides_match_distribution_content_and_credential_urls_are_hidden(): void
    {
        $offer = $this->fixture();
        $offer->update(['invitee_benefit_override' => '0', 'conditions_override' => '0', 'referral_url' => 'https://user:pass@example.com/invite']);
        $this->get('/offers/PUBLIC-A')->assertOk()->assertSee('<dd>0</dd>', false)->assertDontSee('Free month')->assertDontSee('user:pass');
        $this->get('/services/example')->assertOk()->assertSee('<p>0</p>', false);
        $this->assertNull(PortalQuery::safeUrl("https://example.com/\ninvite"));
        $this->assertSame('https://example.com/invite', PortalQuery::safeUrl('https://example.com/invite'));
    }

    public function test_only_workspace_administrators_can_manage_public_profiles(): void
    {
        $offer = $this->fixture();
        $owner = User::findOrFail($offer->workspace->owner_user_id);
        $path = '/workspaces/'.$offer->workspace->public_id.'/public-profile';
        foreach (['member', 'editor', 'reviewer'] as $role) {
            $offer->workspace->members()->where('user_id', $owner->id)->update(['role' => $role]);
            $this->actingAs($owner)->get($path)->assertForbidden();
            $this->putJson($path, ['public_slug' => 'author', 'display_name' => 'Changed', 'is_public' => true])->assertForbidden();
        }
        $this->assertSame('Public Author', $offer->workspace->publicProfile->fresh()->display_name);
    }
}
