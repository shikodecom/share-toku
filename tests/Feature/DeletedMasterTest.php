<?php

namespace Tests\Feature;

use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Publishability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeletedMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_master_preserves_dashboard_and_blocks_creation_submission_and_approval(): void
    {
        foreach (['service', 'program'] as $kind) {
            $author = User::factory()->create();
            $admin = User::factory()->create();
            $admin->forceFill(['is_system_admin' => true])->save();
            $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Test', 'owner_user_id' => $author->id]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $author->id, 'role' => 'administrator']);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $admin->id, 'role' => 'administrator']);
            $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'service-'.$kind]);
            $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'Program',
                'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved']);
            $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id,
                'referral_program_id' => $program->id, 'referral_code' => 'CODE', 'created_by_user_id' => $author->id]);
            $path = '/workspaces/'.$workspace->public_id.'/offers';
            $review = $this->actingAs($author)->postJson($path.'/'.$offer->public_id.'/submit')->assertCreated()->json('public_id');
            $this->actingAs($admin)->deleteJson('/master/'.($kind === 'service' ? 'services/'.$service->public_id : 'programs/'.$program->public_id))->assertNoContent();

            $this->actingAs($author)->get('/dashboard')->assertOk()->assertDontSee('<option value="'.$program->id.'">', false);
            $this->actingAs($admin)->get('/dashboard')->assertOk();
            $this->postJson('/reviews/'.$review.'/decision', ['decision' => 'approve'])->assertUnprocessable();
            $this->postJson('/reviews/'.$review.'/decision', ['decision' => 'reject', 'reason' => 'Master deleted'])->assertOk();
            $this->actingAs($author)->postJson($path.'/'.$offer->public_id.'/submit')->assertUnprocessable();
            $this->postJson($path, ['referral_program_id' => $program->id, 'referral_code' => 'NEW'])->assertUnprocessable();

            $offer->update(['status' => 'published']);
            $this->assertFalse(app(Publishability::class)->offerAllowed($offer->fresh(), true));
            $this->assertFalse(app(Publishability::class)->offers(true)->whereKey($offer->id)->exists());
            $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('削除済み');
        }
    }
}
