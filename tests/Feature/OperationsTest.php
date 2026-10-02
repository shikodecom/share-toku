<?php

namespace Tests\Feature;

use App\Models\Placement;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EventToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Operations test', 'owner_user_id' => $user->id]);
        $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'Service', 'slug' => 'operations-test']);
        $program = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'Program']);
        $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'referral_program_id' => $program->id,
            'referral_code' => 'TEST', 'status' => 'published', 'created_by_user_id' => $user->id]);
        $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'name' => 'Test site', 'domain' => 'example.invalid']);
        $placement = Placement::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'owner_referral_offer_id' => $offer->id, 'placement_key' => 'operations']);

        return [$offer, $site, $placement];
    }

    public function test_expiration_preserves_other_statuses_and_records_audit_once(): void
    {
        $this->freezeTime();
        [$expired] = $this->fixture();
        $expired->update(['ends_at' => now()->subSecond()]);
        $future = $expired->replicate(['public_id']);
        $future->public_id = (string) Str::ulid();
        $future->ends_at = now()->addDay();
        $future->save();
        $draft = $expired->replicate(['public_id']);
        $draft->public_id = (string) Str::ulid();
        $draft->status = 'draft';
        $draft->save();

        $this->artisan('offers:expire')->assertSuccessful();
        $this->artisan('offers:expire')->assertSuccessful();
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('published', $future->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 1);
        $audit = DB::table('audit_logs')->first();
        $this->assertSame('expire', $audit->action);
        $this->assertEquals($expired->id, $audit->auditable_id);
        $this->assertSame(['status' => 'published'], json_decode($audit->before_json, true));
        $this->assertSame(['status' => 'expired'], json_decode($audit->after_json, true));
    }

    public function test_retention_keeps_boundary_events_and_daily_metrics(): void
    {
        $this->freezeTime();
        config(['sharetoku.analytics_retention_days' => 90]);
        [$offer, $site, $placement] = $this->fixture();
        $cutoff = now()->subDays(90);
        foreach ([$cutoff->copy()->subSecond(), $cutoff, now()->subDay()] as $occurredAt) {
            DB::table('analytics_events')->insert(['event_id' => (string) Str::ulid(), 'occurred_at' => $occurredAt, 'received_at' => now(),
                'event_type' => 'impression', 'site_id' => $site->id, 'placement_id' => $placement->id, 'referral_offer_id' => $offer->id,
                'slot' => 'owner', 'created_at' => now()]);
        }
        $this->artisan('analytics:aggregate')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', ['occurred_at' => $cutoff->format('Y-m-d H:i:s')]);
        $this->assertDatabaseCount('daily_metrics', 1);
        $this->assertDatabaseHas('daily_metrics', ['metric_date' => now()->subDay()->toDateString(), 'impressions' => 1]);
    }

    public function test_event_tokens_are_invalidated_by_app_key_rotation(): void
    {
        $oldKey = 'base64:'.base64_encode(str_repeat('a', 32));
        config(['app.key' => $oldKey]);
        $tokens = app(EventToken::class);
        $oldToken = $tokens->issue(['site' => 'test'])['token'];
        $this->assertNotNull($tokens->verify($oldToken));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
        $this->assertNull($tokens->verify($oldToken));
        $newToken = $tokens->issue(['site' => 'test'])['token'];
        $this->assertNotNull($tokens->verify($newToken));
        config(['app.key' => $oldKey]);
        $this->assertNull($tokens->verify($newToken));
        $this->assertNotNull($tokens->verify($oldToken));
    }
}
