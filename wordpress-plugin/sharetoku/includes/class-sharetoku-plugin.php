<?php

if (! defined('ABSPATH')) {
    exit;
}

final class ShareToku_Plugin
{
    private static $file;

    private const OPTION = 'sharetoku_settings';

    public static function boot($file)
    {
        self::$file = $file;
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_sharetoku_save', [__CLASS__, 'save']);
        add_action('admin_post_sharetoku_connect', [__CLASS__, 'connect']);
        add_action('admin_post_sharetoku_callback', [__CLASS__, 'callback']);
        add_action('admin_post_sharetoku_disconnect', [__CLASS__, 'disconnect']);
        add_action('admin_post_sharetoku_clear_cache', [__CLASS__, 'clear_cache']);
        add_shortcode('sharetoku', [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'register_block']);
        add_action('rest_api_init', [__CLASS__, 'rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    private static function settings()
    {
        return get_option(self::OPTION, []);
    }

    private static function token()
    {
        return get_option('sharetoku_site_token', '');
    }

    private static function api_url()
    {
        return rtrim(self::settings()['api_url'] ?? '', '/');
    }

    private static function admin_url()
    {
        return admin_url('options-general.php?page=sharetoku');
    }

    private static function callback_url()
    {
        return admin_url('admin-post.php?action=sharetoku_callback');
    }

    public static function menu()
    {
        add_options_page('ShareToku', 'ShareToku', 'manage_options', 'sharetoku', [__CLASS__, 'settings_page']);
    }

    public static function settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $remote = self::token() ? self::request('GET', '/api/v1/site/me') : null;
        $settings = self::settings();
        if ($remote) {
            $settings['plan'] = sanitize_text_field($remote['plan'] ?? '');
            $settings['site_domain'] = sanitize_text_field($remote['domain'] ?? '');
            update_option(self::OPTION, $settings, false);
        }
        echo '<div class="wrap"><h1>ShareToku</h1>';
        echo '<p>接続状態: '.(self::token() ? '接続済み' : '未接続').'</p>';
        if (self::token()) {
            echo '<p>Site: '.esc_html($settings['site_domain'] ?? '').' / Plan: '.esc_html($settings['plan'] ?? '').'</p>';
            echo '<p>最終 API 成功: '.esc_html($settings['last_success'] ?? '未記録').'</p>';
            if (($settings['plan'] ?? '') === 'free') {
                echo '<p>FREE プランでは、同意済みの場合に関連する ShareToku 運営者の紹介特典が PR として追加表示される場合があります。</p>';
            }
            self::form_button('sharetoku_disconnect', '接続解除');
            self::form_button('sharetoku_clear_cache', 'キャッシュ削除');
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('sharetoku_save');
        echo '<input type="hidden" name="action" value="sharetoku_save">';
        echo '<p><label>ShareToku URL <input type="url" name="api_url" size="60" required value="'.esc_attr($settings['api_url'] ?? '').'"></label></p>';
        echo '<p><label>Site public ID <input type="text" name="site_public_id" size="30" required value="'.esc_attr($settings['site_public_id'] ?? '').'"></label></p>';
        submit_button('設定を保存');
        echo '</form>';
        if (! empty($settings['api_url']) && ! empty($settings['site_public_id'])) {
            self::form_button('sharetoku_connect', 'ShareToku と接続');
        }
        echo '</div>';
    }

    private static function form_button($action, $label)
    {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field($action);
        echo '<input type="hidden" name="action" value="'.esc_attr($action).'">';
        submit_button($label, 'secondary');
        echo '</form>';
    }

    private static function require_admin($action)
    {
        if (! current_user_can('manage_options')) {
            wp_die('Forbidden', '', ['response' => 403]);
        }
        check_admin_referer($action);
    }

    public static function save()
    {
        self::require_admin('sharetoku_save');
        $api = esc_url_raw(wp_unslash($_POST['api_url'] ?? ''));
        $host = wp_parse_url($api, PHP_URL_HOST);
        $scheme = wp_parse_url($api, PHP_URL_SCHEME);
        if (! $host || ($scheme !== 'https' && ! (in_array($host, ['localhost', '127.0.0.1'], true) && $scheme === 'http'))) {
            wp_die('Invalid ShareToku URL');
        }
        $id = sanitize_text_field(wp_unslash($_POST['site_public_id'] ?? ''));
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $id)) {
            wp_die('Invalid Site ID');
        }
        $old = self::settings();
        if (($old['api_url'] ?? '') !== $api || ($old['site_public_id'] ?? '') !== $id) {
            delete_option('sharetoku_site_token');
        }
        update_option(self::OPTION, ['api_url' => $api, 'site_public_id' => $id], false);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function connect()
    {
        self::require_admin('sharetoku_connect');
        $settings = self::settings();
        if (empty($settings['api_url']) || empty($settings['site_public_id'])) {
            wp_die('Configure ShareToku first');
        }
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        set_transient('sharetoku_connect_'.get_current_user_id(), ['state' => $state, 'verifier' => $verifier], 300);
        $url = trailingslashit($settings['api_url']).'site-connections/authorize';
        $url = add_query_arg(['site_public_id' => $settings['site_public_id'], 'redirect_uri' => self::callback_url(), 'site_home_url' => home_url('/'), 'state' => $state,
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256'], $url);
        wp_redirect($url);
        exit;
    }

    public static function callback()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Forbidden', '', ['response' => 403]);
        }
        $pending = get_transient('sharetoku_connect_'.get_current_user_id());
        delete_transient('sharetoku_connect_'.get_current_user_id());
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
        if (! $pending || ! hash_equals($pending['state'], $state) || ! preg_match('/^[a-f0-9]{64}$/', $code)) {
            wp_die('Invalid connection callback');
        }
        $response = wp_remote_post(self::api_url().'/api/v1/site-connections/exchange', ['timeout' => 10, 'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['code' => $code, 'code_verifier' => $pending['verifier'], 'redirect_uri' => self::callback_url()])]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_die('ShareToku connection failed');
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token']) || ! preg_match('/^[a-f0-9]{64}$/', $body['access_token'])) {
            wp_die('Invalid token response');
        }
        delete_option('sharetoku_site_token');
        add_option('sharetoku_site_token', $body['access_token'], '', false);
        $settings = self::settings();
        $settings['site_domain'] = sanitize_text_field($body['site']['domain'] ?? '');
        $settings['plan'] = sanitize_text_field($body['site']['plan'] ?? '');
        $settings['last_success'] = current_time('mysql', true);
        update_option(self::OPTION, $settings, false);
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function disconnect()
    {
        self::require_admin('sharetoku_disconnect');
        if (self::token()) {
            $response = wp_remote_post(self::api_url().'/api/v1/site/disconnect', ['timeout' => 10,
                'headers' => ['Authorization' => 'Bearer '.self::token()]]);
            if (is_wp_error($response) || ! in_array(wp_remote_retrieve_response_code($response), [204, 401, 403], true)) {
                wp_die('ShareToku に接続できないため、接続解除を確認できませんでした。通信状態を確認して再試行してください。');
            }
        }
        delete_option('sharetoku_site_token');
        delete_transient('sharetoku_connect_'.get_current_user_id());
        $settings = self::settings();
        unset($settings['site_domain'], $settings['plan'], $settings['last_success']);
        update_option(self::OPTION, $settings, false);
        self::clear_cache_internal();
        wp_safe_redirect(self::admin_url());
        exit;
    }

    public static function clear_cache()
    {
        self::require_admin('sharetoku_clear_cache');
        self::clear_cache_internal();
        wp_safe_redirect(self::admin_url());
        exit;
    }

    private static function clear_cache_internal()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sharetoku_resolve_%' OR option_name LIKE '_transient_timeout_sharetoku_resolve_%'");
    }

    private static function request($method, $path, $body = null)
    {
        if (! self::token()) {
            return null;
        }
        $args = ['method' => $method, 'timeout' => 6, 'headers' => ['Authorization' => 'Bearer '.self::token(), 'Accept' => 'application/json']];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request(self::api_url().$path, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data)) {
            return null;
        }
        $settings = self::settings();
        $settings['last_success'] = current_time('mysql', true);
        update_option(self::OPTION, $settings, false);

        return $data;
    }

    private static function resolve($offer, $placement)
    {
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $offer) || ! preg_match('/^[A-Za-z0-9._:-]{1,150}$/', $placement)) {
            return null;
        }
        $settings = self::settings();
        $key = 'sharetoku_resolve_'.md5(($settings['site_public_id'] ?? '').'|'.$offer.'|'.$placement);
        $cached = get_transient($key);
        if (is_array($cached) && ! empty($cached['cache']['expires_at']) && strtotime($cached['cache']['expires_at']) > time()) {
            return $cached;
        }
        $result = self::request('POST', '/api/v1/placements/resolve', ['owner_offer_id' => $offer, 'placement_key' => $placement]);
        if (! is_array($result) || ($result['schema_version'] ?? null) !== 1 || empty($result['owner_offer']['public_id']) || empty($result['cache']['ttl_seconds'])) {
            return null;
        }
        $ttl = min(300, max(0, (int) $result['cache']['ttl_seconds']), max(0, strtotime($result['cache']['expires_at'] ?? '') - time()));
        if ($ttl > 0) {
            set_transient($key, $result, $ttl);
        }

        return $result;
    }

    public static function shortcode($attrs)
    {
        $attrs = shortcode_atts(['offer' => '', 'placement' => 'article-body'], $attrs, 'sharetoku');

        return self::render(sanitize_text_field($attrs['offer']), sanitize_key($attrs['placement']), false, []);
    }

    private static function render($offer, $placement, $preview, $attrs)
    {
        $data = self::resolve($offer, $placement);
        if (! $data) {
            return $preview ? '<p>ShareToku のプレビューを取得できません。</p>' : '';
        }
        $analytics = $preview ? '' : ($data['analytics']['event_token'] ?? '');
        $html = '<div class="sharetoku-placement" data-sharetoku-endpoint="'.esc_url(self::api_url().'/api/v1/events/batch').'" data-sharetoku-event-token="'.esc_attr($analytics).'">';
        $html .= self::card($data['owner_offer'], 'owner', $data['placement_id'] ?? '', $attrs);
        if (! empty($data['operator_offer'])) {
            $html .= self::card($data['operator_offer'], 'operator', $data['placement_id'] ?? '', $attrs);
        }

        return $html.'</div>';
    }

    private static function card($offer, $slot, $placement, $attrs)
    {
        if (empty($offer['public_id']) || empty($offer['service']['name'])) {
            return '';
        }
        $label = $slot === 'operator' ? 'PR・ShareToku運営者の紹介特典' : '紹介特典';
        $html = '<article class="sharetoku-card sharetoku-'.$slot.'" data-sharetoku-slot="'.esc_attr($slot).'" data-sharetoku-placement="'.esc_attr($placement).'">';
        $html .= '<strong class="sharetoku-label">'.esc_html($label).'</strong><h3>'.esc_html($offer['service']['name']).'</h3>';
        if (! empty($offer['invitee_benefit'])) {
            $html .= '<p>'.esc_html($offer['invitee_benefit']).'</p>';
        }
        if (! empty($offer['conditions']) && ($attrs['showConditions'] ?? true)) {
            $html .= '<p>条件: '.esc_html($offer['conditions']).'</p>';
        }
        if (! empty($offer['referral_code'])) {
            $html .= '<p>紹介コード: <code>'.esc_html($offer['referral_code']).'</code> <button type="button" class="sharetoku-copy" data-code="'.esc_attr($offer['referral_code']).'">コピー</button></p>';
        }
        if (! empty($offer['referral_url'])) {
            $safeUrl = esc_url($offer['referral_url'], ['http', 'https']);
            if ($safeUrl) {
                $html .= '<p><a class="sharetoku-link" href="'.$safeUrl.'" rel="nofollow sponsored noopener noreferrer" target="_blank">紹介リンクを開く</a></p>';
            }
        }
        if (! empty($offer['ends_at'])) {
            $html .= '<p>期限: '.esc_html(substr($offer['ends_at'], 0, 10)).'</p>';
        }
        if (! empty($offer['last_verified_at']) && ($attrs['showLastVerifiedAt'] ?? true)) {
            $html .= '<p>最終確認: '.esc_html(substr($offer['last_verified_at'], 0, 10)).'</p>';
        }
        $html .= '<small>紹介者にも特典が発生する場合があります。</small><span class="sharetoku-live" aria-live="polite"></span></article>';

        return $html;
    }

    public static function assets()
    {
        wp_enqueue_style('sharetoku', plugins_url('assets/sharetoku.css', self::$file), [], '0.1.0');
        wp_enqueue_script('sharetoku', plugins_url('assets/sharetoku.js', self::$file), [], '0.1.0', true);
    }

    public static function register_block()
    {
        register_block_type(dirname(__DIR__).'/build', ['render_callback' => function ($attrs) {
            $offer = sanitize_text_field($attrs['offerId'] ?? '');
            $placement = sanitize_text_field($attrs['placementKey'] ?? '');
            if (! $placement) {
                return '';
            }

            return self::render($offer, $placement, is_admin() || (defined('REST_REQUEST') && REST_REQUEST), $attrs);
        }]);
    }

    public static function rest_routes()
    {
        register_rest_route('sharetoku/v1', '/site', ['methods' => 'GET', 'permission_callback' => [__CLASS__, 'rest_permission'], 'callback' => function () {
            if (! self::token()) {
                return rest_ensure_response(['connected' => false, 'plan' => null]);
            }
            $site = self::request('GET', '/api/v1/site/me');
            if (! $site) {
                return new WP_Error('sharetoku_unavailable', 'ShareToku に接続できません。', ['status' => 503]);
            }

            return rest_ensure_response(['connected' => true, 'plan' => $site['plan'] ?? null]);
        }]);
        register_rest_route('sharetoku/v1', '/offers', ['methods' => 'GET', 'permission_callback' => [__CLASS__, 'rest_permission'], 'callback' => function ($request) {
            if (! self::token()) {
                return new WP_Error('sharetoku_disconnected', 'ShareToku が未接続です。', ['status' => 503]);
            }
            $q = sanitize_text_field($request->get_param('q') ?? '');
            $page = max(1, (int) $request->get_param('page'));
            $data = self::request('GET', '/api/v1/site/offers?'.http_build_query(['q' => $q, 'page' => $page]));

            return rest_ensure_response($data ?: ['data' => []]);
        }]);
        register_rest_route('sharetoku/v1', '/preview', ['methods' => 'POST', 'permission_callback' => [__CLASS__, 'rest_permission'], 'callback' => function ($request) {
            $offer = sanitize_text_field($request->get_param('offerId') ?? '');
            $placement = sanitize_text_field($request->get_param('placementKey') ?? '');

            return rest_ensure_response(['html' => self::render($offer, $placement, true, [])]);
        }]);
    }

    public static function rest_permission($request)
    {
        return current_user_can('edit_posts') && wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
    }
}
