<?php

use App\Http\Controllers\SiteConnectionController;
use App\Http\Controllers\SiteController;
use App\Models\Site;
use App\Models\SiteAccessToken;
use App\Models\SiteAuthorizationCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Audit;
use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

// Run only against an isolated MySQL test DB with migrations already applied.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
});

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

check(app()->environment('testing') && DB::getDriverName() === 'mysql', 'Requires APP_ENV=testing and a disposable MySQL DB');
DB::statement('SET SESSION innodb_lock_wait_timeout = 10');

if (($argv[1] ?? '') === '--worker') {
    try {
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        echo json_encode(['connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id]).PHP_EOL;
        flush();
        $request = Request::create('/', 'POST', $input['data']);
        $controller = app(SiteConnectionController::class);
        if ($input['operation'] === 'authorize') {
            $request->setUserResolver(fn () => User::findOrFail($input['user_id']));
            $response = $controller->authorizeSite($request, app(WorkspaceAccess::class));
        } else {
            $response = $controller->exchange($request, app(EntitlementService::class));
        }
        // Never print the response body containing the issued bearer token.
        echo json_encode(['status' => $response->getStatusCode()]).PHP_EOL;
        exit(0);
    } catch (ValidationException $e) {
        echo json_encode(['status' => 422]).PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e::class, 'code' => $e->getCode()]).PHP_EOL;
        exit(1);
    }
}

$workers = [];
$fixtures = [];

function fixture(string $domain): array
{
    global $fixtures;
    $user = User::factory()->create(['is_system_admin' => true]);
    $workspace = Workspace::create(['public_id' => (string) Str::ulid(), 'name' => 'Concurrency test', 'owner_user_id' => $user->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'administrator']);
    $site = Site::create(['public_id' => (string) Str::ulid(), 'workspace_id' => $workspace->id, 'name' => 'Test', 'domain' => $domain]);
    $data = ['code' => bin2hex(random_bytes(32)), 'code_verifier' => str_repeat('a', 43),
        'redirect_uri' => 'https://'.$domain.'/wp-admin/admin-post.php?action=sharetoku_callback'];
    $code = SiteAuthorizationCode::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'user_id' => $user->id,
        'code_hash' => hash('sha256', $data['code']), 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $data['code_verifier'], true)), '+/', '-_'), '='),
        'redirect_uri' => $data['redirect_uri'], 'expires_at' => now()->addMinutes(5), 'created_at' => now()]);
    SiteAccessToken::create(['public_id' => (string) Str::ulid(), 'site_id' => $site->id, 'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
        'scopes_json' => ['site:read'], 'created_by_user_id' => $user->id, 'created_at' => now()]);
    $result = compact('user', 'workspace', 'site', 'code', 'data');
    $fixtures[] = $result;

    return $result;
}

function worker(array $fixture, string $operation = 'exchange'): Process
{
    global $workers;
    $db = config('database.connections.mysql');
    $process = new Process([PHP_BINARY, __FILE__, '--worker'], base_path(), [
        'APP_BASE_PATH' => base_path(), 'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
        'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => (string) $db['host'], 'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_SOCKET' => $db['unix_socket'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null',
    ]);
    $data = $fixture['data'];
    if ($operation === 'authorize') {
        $data = ['site_public_id' => $fixture['site']->public_id, 'redirect_uri' => $data['redirect_uri'],
            'site_home_url' => 'https://'.$fixture['site']->domain.'/', 'state' => str_repeat('b', 32),
            'code_challenge' => $fixture['code']->code_challenge, 'code_challenge_method' => 'S256'];
    }
    $process->setInput(json_encode(['operation' => $operation, 'data' => $data, 'user_id' => $fixture['user']->id], JSON_THROW_ON_ERROR));
    $process->setTimeout(20);
    $process->start();
    $workers[] = $process;

    return $process;
}

function awaitSiteWait(Process $process, int $siteId): void
{
    $deadline = microtime(true) + 10;
    do {
        $first = explode("\n", $process->getOutput())[0];
        $ready = json_decode($first, true);
        if (isset($ready['connection_id'])) {
            $waiting = DB::selectOne('SELECT COUNT(*) AS n FROM performance_schema.data_lock_waits w
                JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE = w.ENGINE
                JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID
                WHERE t.PROCESSLIST_ID = ? AND l.OBJECT_SCHEMA = ? AND l.OBJECT_NAME = ? AND l.INDEX_NAME = ? AND l.LOCK_DATA = ?',
                [$ready['connection_id'], DB::getDatabaseName(), 'sites', 'PRIMARY', (string) $siteId]);
            if ($waiting->n > 0) {
                return;
            }
        }
        check($process->isRunning(), 'Worker exited before reaching the expected Site lock');
        usleep(10000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Timed out waiting for the expected InnoDB Site lock');
}

function status(Process $process): int
{
    check($process->wait() === 0, 'Worker failed: '.$process->getOutput());
    $lines = explode("\n", trim($process->getOutput()));

    return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['status'];
}

try {
    foreach (['suspend', 'update'] as $operation) {
        $f = fixture($operation.'-'.strtolower((string) Str::ulid()).'.example.invalid');
        DB::beginTransaction();
        Site::lockForUpdate()->findOrFail($f['site']->id);
        $process = worker($f);
        awaitSiteWait($process, $f['site']->id);
        // The old Code -> Site order fails NOWAIT here, even with deadlock retry.
        DB::select('SELECT id FROM site_authorization_codes WHERE id = ? FOR UPDATE NOWAIT', [$f['code']->id]);
        $request = Request::create('/', 'POST', ['reason' => 'Test', 'domain' => 'changed-'.$f['site']->domain]);
        $request->setUserResolver(fn () => $f['user']);
        $controller = app(SiteController::class);
        if ($operation === 'suspend') {
            $controller->suspend($request, $f['site']->public_id, app(WorkspaceAccess::class), app(Audit::class));
        } else {
            $controller->update($request, $f['workspace']->public_id, $f['site']->public_id, app(WorkspaceAccess::class), app(Audit::class));
        }
        DB::commit();
        check(status($process) === 400, 'Concurrent exchange must reject the consumed grant');
        check($f['site']->fresh()->status === ($operation === 'suspend' ? 'suspended' : 'pending'), 'Exchange reactivated a changed Site');
        check(SiteAccessToken::where('site_id', $f['site']->id)->whereNull('revoked_at')->count() === 0, 'Changed Site retained a live token');
    }

    $domain = 'shared-'.strtolower((string) Str::ulid()).'.example.invalid';
    $low = fixture($domain);
    $high = fixture($domain);
    DB::beginTransaction();
    Site::lockForUpdate()->findOrFail($low['site']->id);
    $highWorker = worker($high);
    awaitSiteWait($highWorker, $low['site']->id);
    // High-ID exchange must not hold its own Site before waiting on low ID.
    DB::select('SELECT id FROM sites WHERE id = ? FOR UPDATE NOWAIT', [$high['site']->id]);
    $lowWorker = worker($low);
    awaitSiteWait($lowWorker, $low['site']->id);
    DB::commit();
    $statuses = [status($lowWorker), status($highWorker)];
    sort($statuses);
    check($statuses === [200, 409], 'Exactly one same-domain exchange must succeed');
    check(Site::where('domain', $domain)->where('status', 'active')->count() === 1, 'Domain has multiple active Sites');
    check(SiteAccessToken::whereIn('site_id', [$low['site']->id, $high['site']->id])->count() === 3, 'Multiple exchanges issued tokens');

    $f = fixture('authorize-'.strtolower((string) Str::ulid()).'.example.invalid');
    DB::beginTransaction();
    Site::lockForUpdate()->findOrFail($f['site']->id);
    $process = worker($f, 'authorize');
    awaitSiteWait($process, $f['site']->id);
    $request = Request::create('/', 'POST', ['reason' => 'Test']);
    $request->setUserResolver(fn () => $f['user']);
    app(SiteController::class)->suspend($request, $f['site']->public_id, app(WorkspaceAccess::class), app(Audit::class));
    DB::commit();
    check(status($process) === 422, 'Authorization must recheck suspension after waiting');
    check(SiteAuthorizationCode::where('site_id', $f['site']->id)->count() === 1, 'Suspended Site received a new authorization code');
    echo json_encode(['result' => 'passed', 'concurrent_scenarios' => 4]).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    foreach ($workers as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    foreach ($fixtures as $f) {
        foreach (['site_access_tokens', 'site_authorization_codes', 'site_consents'] as $table) {
            DB::table($table)->where('site_id', $f['site']->id)->delete();
        }
        DB::table('audit_logs')->where('workspace_id', $f['workspace']->id)->delete();
        DB::table('sites')->where('id', $f['site']->id)->delete();
        DB::table('workspace_members')->where('workspace_id', $f['workspace']->id)->delete();
        DB::table('workspaces')->where('id', $f['workspace']->id)->delete();
        DB::table('users')->where('id', $f['user']->id)->delete();
    }
}
exit($exitCode ?? 0);
