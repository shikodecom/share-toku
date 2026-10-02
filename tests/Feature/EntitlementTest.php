<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_free_upgrade_downgrade_and_server_side_site_limit(): void
    {
        $user = User::factory()->create();
        $w = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Test', 'owner_user_id' => $user->id]);
        WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $user->id, 'role' => 'administrator']);
        $service = app(EntitlementService::class);
        $this->assertSame('free', $service->plan($w));
        $this->assertTrue($service->get($w, 'operator_offer_enabled'));
        $this->actingAs($user)->postJson('/workspaces/'.$w->public_id.'/sites', ['name' => 'One', 'domain' => 'one.example.com'])->assertCreated();
        $this->actingAs($user)->postJson('/workspaces/'.$w->public_id.'/sites', ['name' => 'Two', 'domain' => 'two.example.com'])->assertUnprocessable();
        $subscription = WorkspaceSubscription::create(['workspace_id' => $w->id, 'plan_code' => 'pro', 'status' => 'active', 'starts_at' => now()]);
        $this->assertSame('pro', $service->plan($w));
        $this->assertFalse($service->get($w, 'operator_offer_enabled'));
        $this->actingAs($user)->postJson('/workspaces/'.$w->public_id.'/sites', ['name' => 'Two', 'domain' => 'two.example.com'])->assertCreated();
        $subscription->update(['status' => 'cancelled']);
        $this->assertSame('free', $service->plan($w));
        $this->assertSame(2, $w->sites()->count());
        $this->actingAs($user)->postJson('/workspaces/'.$w->public_id.'/sites', ['name' => 'Three', 'domain' => 'three.example.com'])->assertUnprocessable();
        $data = ['plan_code' => 'pro', 'status' => 'active', 'starts_at' => now()->toIso8601String()];
        $this->actingAs($user)->postJson('/admin/workspaces/'.$w->public_id.'/subscription', $data)->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin)->postJson('/admin/workspaces/'.$w->public_id.'/subscription', $data)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['workspace_id' => $w->id, 'action' => 'subscription_change']);
    }
}
