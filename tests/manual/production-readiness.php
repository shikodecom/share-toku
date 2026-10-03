<?php

use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = getenv('SHARETOKU_RELEASE_ROOT');
if (! $root) {
    fwrite(STDERR, "SHARETOKU_RELEASE_ROOT is required\n");
    exit(2);
}
require $root.'/current/vendor/autoload.php';
$app = require $root.'/current/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config(['cache.default' => 'array']);
$kernel->call('schedule:list');
$schedule = $app->make(Schedule::class);
$cron = shell_exec('crontab -l 2>/dev/null') ?? '';
$entries = array_values(array_filter(explode("\n", $cron), fn ($line) => str_contains($line, 'share-toku') && ! str_starts_with(trim($line), '#')));
$backups = [];
foreach ([$root.'/backups', $root.'/shared/backups', $root.'/shared/backup'] as $path) {
    $files = is_dir($path) ? array_values(array_filter(glob($path.'/*') ?: [], 'is_file')) : [];
    $backups[] = ['directory_present' => is_dir($path), 'file_count' => count($files), 'newest_timestamp' => $files ? max(array_map('filemtime', $files)) : null, 'files' => array_map(fn ($file) => ['extension' => pathinfo($file, PATHINFO_EXTENSION), 'bytes' => filesize($file), 'mode' => decoct(fileperms($file) & 0777)], $files)];
}
$publicId = config('sharetoku.operator_workspace_public_id');
$result = [
    'php_version' => PHP_VERSION, 'app_env' => config('app.env'), 'app_debug' => config('app.debug'),
    'app_url' => config('app.url'), 'secure_cookie' => config('session.secure'), 'session_driver' => config('session.driver'),
    'operator_offers_enabled' => config('sharetoku.operator_offers_enabled'), 'operator_workspace_configured' => filled($publicId),
    'operator_workspace_exists' => $publicId ? Workspace::where('public_id', $publicId)->exists() : false,
    'analytics_retention_days' => config('sharetoku.analytics_retention_days'), 'mysql_version' => DB::selectOne('SELECT VERSION() AS version')->version,
    'cron_matching_entry_count' => count($entries), 'cron_schedule_run_count' => count(array_filter($entries, fn ($line) => str_contains($line, 'schedule:run'))),
    'cron_minutely_schedule_run_count' => count(array_filter($entries, fn ($line) => preg_match('/^\s*\*\s+\*\s+\*\s+\*\s+\*\s/', $line) && str_contains($line, 'schedule:run'))),
    'registered_commands' => array_map(fn ($event) => ['command' => preg_match('/(offers:expire|analytics:aggregate|analytics:prune)/', $event->command ?? '', $m) ? $m[1] : 'other', 'expression' => $event->expression, 'timezone' => $event->timezone], $schedule->events()),
    'application_backup_directories' => $backups, 'logging_default' => config('logging.default'), 'logging_stack' => config('logging.channels.stack.channels'),
    'logging_daily_days' => config('logging.channels.daily.days'), 'env_owner_only' => (fileperms($root.'/shared/.env') & 0777) === 0600,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
