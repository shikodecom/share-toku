<?php

define('ABSPATH', __DIR__);

class DisconnectFailure extends RuntimeException {}
class DisconnectRedirect extends RuntimeException {}

function current_user_can($capability)
{
    return $GLOBALS['allow_admin'];
}
function check_admin_referer($action)
{
    if (! $GLOBALS['allow_nonce']) {
        throw new DisconnectFailure('Invalid nonce');
    }
}
function get_current_user_id()
{
    return 7;
}
function get_option($key, $default = null)
{
    return $GLOBALS['options'][$key] ?? $default;
}
function update_option($key, $value, $autoload = null)
{
    $GLOBALS['options'][$key] = $value;
}
function delete_option($key)
{
    unset($GLOBALS['options'][$key]);
}
function delete_transient($key)
{
    unset($GLOBALS['transients'][$key]);
}
function wp_remote_post($url, $args)
{
    $GLOBALS['requests']++;

    return $GLOBALS['response'];
}
function is_wp_error($response)
{
    return $response === 'transport';
}
function wp_remote_retrieve_response_code($response)
{
    return $response;
}
function wp_die($message, ...$rest)
{
    throw new DisconnectFailure($message);
}
function admin_url($path)
{
    return 'https://blog.example/wp-admin/'.$path;
}
function wp_safe_redirect($url)
{
    throw new DisconnectRedirect($url);
}

require dirname(__DIR__).'/includes/class-sharetoku-plugin.php';

foreach ([204, 401, 403, 500, 'transport', 'no-token', 'no-admin', 'bad-nonce'] as $case) {
    $GLOBALS['options'] = ['sharetoku_site_token' => 'test-token', 'sharetoku_settings' => [
        'api_url' => 'https://api.example', 'site_public_id' => 'test-site', 'site_domain' => 'blog.example',
        'plan' => 'free', 'last_success' => 'previous-success',
    ]];
    $GLOBALS['transients'] = ['sharetoku_connect_7' => ['state' => 'test-state']];
    $GLOBALS['allow_admin'] = $case !== 'no-admin';
    $GLOBALS['allow_nonce'] = $case !== 'bad-nonce';
    $GLOBALS['response'] = $case;
    $GLOBALS['requests'] = 0;
    $GLOBALS['wpdb'] = new class
    {
        public $options = 'wp_options';

        public $cleared = false;

        public function query($sql)
        {
            $this->cleared = true;
        }
    };
    if ($case === 'no-token') {
        unset($GLOBALS['options']['sharetoku_site_token']);
    }
    $success = false;
    try {
        ShareToku_Plugin::disconnect();
    } catch (DisconnectRedirect) {
        $success = true;
    } catch (DisconnectFailure) {
    }
    $expectedSuccess = in_array($case, [204, 401, 403, 'no-token'], true);
    if ($success !== $expectedSuccess) {
        throw new RuntimeException('Unexpected disconnect outcome: '.$case);
    }
    if ($expectedSuccess) {
        if (isset($GLOBALS['options']['sharetoku_site_token']) || $GLOBALS['transients'] || ! $GLOBALS['wpdb']->cleared) {
            throw new RuntimeException('Disconnect did not clear credentials, pending connection and cache: '.$case);
        }
        if ($GLOBALS['options']['sharetoku_settings'] !== ['api_url' => 'https://api.example', 'site_public_id' => 'test-site']) {
            throw new RuntimeException('Reconnect settings lost or stale connection details retained: '.$case);
        }
    } elseif (! isset($GLOBALS['options']['sharetoku_site_token']) || ! $GLOBALS['transients'] || $GLOBALS['wpdb']->cleared) {
        throw new RuntimeException('Failed disconnect unexpectedly cleared connection: '.$case);
    }
    if (in_array($case, ['no-token', 'no-admin', 'bad-nonce'], true) && $GLOBALS['requests'] !== 0) {
        throw new RuntimeException('Unauthorized or empty connection made a remote request: '.$case);
    }
}

echo "WordPress disconnect checks passed (8 scenarios)\n";
