<?php

// Run using official WP-CLI eval-file against the isolated fixture database only.
if (! defined('ABSPATH') || DB_NAME !== 'sharetoku_wp_e2e') {
    exit(2);
}
$fixture = json_decode(file_get_contents(getenv('SHARETOKU_E2E_FIXTURE') ?: '/tmp/sharetoku-e2e-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
if (getenv('SHARETOKU_E2E_CLEAR_CACHE') === '1') {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sharetoku_resolve_%' OR option_name LIKE '_transient_timeout_sharetoku_resolve_%'");
}
$html = do_shortcode('[sharetoku offer="'.$fixture['offers']['owner'].'" placement="e2e-shortcode"]');
$result = ['owner' => substr_count($html, 'data-sharetoku-slot="owner"'), 'operator' => substr_count($html, 'data-sharetoku-slot="operator"'), 'pr_label' => str_contains($html, 'PR・ShareToku運営者の紹介特典')];
if (getenv('SHARETOKU_E2E_EXPECT')) {
    $expected = array_map('intval', explode(',', getenv('SHARETOKU_E2E_EXPECT')));
    if ([$result['owner'], $result['operator']] !== $expected) {
        fwrite(STDERR, "Unexpected card counts\n");
        exit(1);
    }
}
echo json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL;
