<?php

// Exercise registered WordPress REST callbacks, not a stub renderer.
if (! defined('ABSPATH') || DB_NAME !== 'sharetoku_wp_e2e') {
    exit(2);
}
$fixture = json_decode(file_get_contents(getenv('SHARETOKU_E2E_FIXTURE') ?: '/tmp/sharetoku-e2e-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
wp_set_current_user(1);
if (getenv('SHARETOKU_E2E_CLEAR_CACHE') === '1') {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sharetoku_resolve_%' OR option_name LIKE '_transient_timeout_sharetoku_resolve_%'");
}
$request = new WP_REST_Request('GET', '/sharetoku/v1/offers');
$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$request->set_param('q', 'E2E Owner');
$response = rest_get_server()->dispatch($request);
$data = $response->get_data();
$found = array_filter($data['data'] ?? [], fn ($offer) => ($offer['public_id'] ?? '') === $fixture['offers']['owner']);
if ($response->get_status() !== 200 || ! $found) {
    fwrite(STDERR, "Offer search failed\n");
    exit(1);
}
$preview = new WP_REST_Request('POST', '/sharetoku/v1/preview');
$preview->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$preview->set_param('offerId', $fixture['offers']['owner']);
$preview->set_param('placementKey', 'e2e-preview');
$response = rest_get_server()->dispatch($preview);
$html = $response->get_data()['html'] ?? '';
if ($response->get_status() !== 200 || ! str_contains($html, 'data-sharetoku-event-token=""')) {
    fwrite(STDERR, "Preview analytics not excluded\n");
    exit(1);
}
$counts = [substr_count($html, 'data-sharetoku-slot="owner"'), substr_count($html, 'data-sharetoku-slot="operator"')];
$expected = array_map('intval', explode(',', getenv('SHARETOKU_E2E_EXPECT') ?: '1,1'));
if ($counts !== $expected) {
    fwrite(STDERR, "Unexpected preview card counts\n");
    exit(1);
}
echo json_encode(['search_status' => 200, 'owner_found' => true, 'preview_counts' => $counts, 'preview_event_token_empty' => true]).PHP_EOL;
