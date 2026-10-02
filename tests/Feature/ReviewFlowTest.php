<?php

namespace Tests\Feature;

use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\OfferContent;
use App\Services\Publishability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_command_marks_published_offer_and_records_audit(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'A', 'owner_user_id' => $user->id]);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'expiry-service']);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id,
            'name' => 'Program', 'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved']);
        $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id,
            'referral_program_id' => $program->id, 'referral_code' => 'CODE', 'status' => 'published',
            'ends_at' => now()->subMinute(), 'created_by_user_id' => $user->id]);

        $this->artisan('offers:expire')->assertSuccessful();
        $this->assertSame('expired', $offer->fresh()->status);
        $this->assertFalse(app(Publishability::class)->offerAllowed($offer->fresh(), true));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $offer->id, 'action' => 'expire']);
    }

    public function test_submit_approve_suspend_and_audit(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_system_admin' => true])->save();
        $adminWorkspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Admin', 'owner_user_id' => $admin->id]);
        WorkspaceMember::create(['workspace_id' => $adminWorkspace->id, 'user_id' => $admin->id, 'role' => 'administrator']);
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'A', 'owner_user_id' => $author->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $author->id, 'role' => 'administrator']);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'service']);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id,
            'name' => 'Program', 'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved']);
        $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id,
            'referral_program_id' => $program->id, 'referral_code' => 'CODE', 'created_by_user_id' => $author->id]);
        $program->update(['invitee_benefit_text' => 'default benefit']);
        $this->assertSame('default benefit', app(OfferContent::class)->forDistribution($offer)['invitee_benefit']);
        $offer->update(['invitee_benefit_override' => 'override benefit']);
        $this->assertSame('override benefit', app(OfferContent::class)->forDistribution($offer->fresh())['invitee_benefit']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('マスタ管理');
        $review = $this->actingAs($author)->postJson('/workspaces/'.$workspace->public_id.'/offers/'.$offer->public_id.'/submit')
            ->assertCreated()->json('public_id');
        $this->assertDatabaseHas('review_requests', ['public_id' => $review, 'status' => 'pending']);
        $this->actingAs($admin)->postJson('/reviews/'.$review.'/decision', ['decision' => 'approve'])->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('/admin/offers/'.$offer->public_id.'/suspend');
        $this->assertTrue(app(Publishability::class)->offerAllowed($offer->fresh(), true));
        $this->actingAs($admin)->postJson('/admin/offers/'.$offer->public_id.'/suspend', ['reason' => 'policy violation'])->assertOk();
        $this->assertFalse(app(Publishability::class)->offerAllowed($offer->fresh(), true));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $offer->id, 'action' => 'suspend']);
        $this->actingAs($admin)->postJson('/admin/offers/'.$offer->public_id.'/reopen')->assertOk()->assertJsonPath('status', 'draft');
        $this->assertFalse(app(Publishability::class)->offerAllowed($offer->fresh(), true));
    }
}
