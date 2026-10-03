<?php

use App\Models\Category;
use App\Models\ReferralOffer;
use App\Models\Site;
use App\Models\WorkspaceSubscription;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sharetoku_laravel_e2e' || ! app()->environment('local')) {
    exit(2);
}
$fixture = json_decode(file_get_contents(getenv('SHARETOKU_E2E_FIXTURE') ?: '/tmp/sharetoku-e2e-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$owner = ReferralOffer::where('public_id', $fixture['offers']['owner'])->firstOrFail();
$operator = ReferralOffer::where('public_id', $fixture['offers']['operator'])->firstOrFail();
$site = Site::where('public_id', $fixture['site'])->firstOrFail();
$state = $argv[1] ?? 'baseline';
if (! in_array($state, ['baseline', 'no-match', 'withdrawn', 'pro', 'offer-off', 'program-off', 'site-off', 'kill-off'], true)) {
    exit(2);
}
$owner->update(['status' => $state === 'offer-off' ? 'suspended' : 'published']);
$owner->program->update(['is_active' => $state !== 'program-off']);
$operator->program->service->categories()->sync($state === 'no-match' ? [] : Category::pluck('id')->all());
$site->update(['status' => $state === 'site-off' ? 'suspended' : 'active']);
$site->consents()->update(['withdrawn_at' => $state === 'withdrawn' ? now() : null]);
WorkspaceSubscription::updateOrCreate(['workspace_id' => $owner->workspace_id], ['plan_code' => $state === 'pro' ? 'pro' : 'free', 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => null]);
$env = file_get_contents($root.'/.env');
$env = preg_replace('/^SHARETOKU_OPERATOR_OFFERS_ENABLED=.*$/m', 'SHARETOKU_OPERATOR_OFFERS_ENABLED='.($state === 'kill-off' ? 'false' : 'true'), $env);
file_put_contents($root.'/.env', $env);
echo $state.PHP_EOL;
