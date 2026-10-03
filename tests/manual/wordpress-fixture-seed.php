<?php

use App\Models\Category;
use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Models\Service;
use App\Models\Site;
use App\Models\SiteConsent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspacePublicProfile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sharetoku_laravel_e2e' || User::exists()) {
    fwrite(STDERR, "Requires an empty, isolated local fixture DB\n");
    exit(2);
}

$owner = User::create(['name' => 'E2E Owner', 'email' => 'owner@example.invalid', 'google_id' => 'e2e-owner']);
$reviewer = User::create(['name' => 'E2E Reviewer', 'email' => 'reviewer@example.invalid', 'google_id' => 'e2e-reviewer']);
$w = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'E2E Owner Workspace', 'owner_user_id' => $owner->id]);
WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $owner->id, 'role' => 'administrator']);
WorkspaceMember::create(['workspace_id' => $w->id, 'user_id' => $reviewer->id, 'role' => 'reviewer']);
$operator = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'E2E Operator', 'owner_user_id' => $reviewer->id]);
WorkspacePublicProfile::create(['workspace_id' => $w->id, 'public_slug' => 'e2e-owner', 'display_name' => 'E2E紹介者', 'is_public' => true]);
$category = Category::create(['public_id' => (string) Str::ulid(), 'name' => 'E2E Cloud', 'slug' => 'e2e-cloud']);
$offers = [];
foreach ([['Owner', 'owner', $w], ['Operator', 'operator', $operator]] as [$label,$slug,$workspace]) {
    $service = Service::create(['public_id' => (string) Str::ulid(), 'name' => 'E2E '.$label.' Service', 'slug' => 'e2e-'.$slug]);
    $service->categories()->attach($category);
    $p = ReferralProgram::create(['public_id' => (string) Str::ulid(), 'service_id' => $service->id, 'name' => 'E2E '.$label.' Program', 'public_listing_policy' => 'approved', 'external_distribution_policy' => 'approved', 'invitee_benefit_text' => 'E2E '.$label.' benefit', 'conditions_text' => 'Local test only']);
    $offer = ReferralOffer::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'referral_program_id' => $p->id, 'status' => 'published', 'referral_code' => 'E2E-'.$label.'-CODE', 'referral_url' => 'https://example.com/'.$slug, 'created_by_user_id' => $owner->id, 'last_verified_at' => now()]);
    $offers[$slug] = $offer->public_id;
}
$site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'name' => 'E2E WordPress', 'domain' => '127.0.0.1', 'status' => 'pending']);
SiteConsent::create(['site_id' => $site->id, 'user_id' => $owner->id, 'terms_version' => '1', 'consented_at' => now()]);
echo json_encode(['owner_user_id' => $owner->id, 'reviewer_user_id' => $reviewer->id, 'workspace' => $w->public_id, 'operator_workspace' => $operator->public_id, 'site' => $site->public_id, 'offers' => $offers], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
