<?php

define('ABSPATH', __DIR__);
function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function esc_attr($value)
{
    return esc_html($value);
}
function esc_url($value, $protocols = ['http', 'https'])
{
    $scheme = parse_url((string) $value, PHP_URL_SCHEME);

    return in_array($scheme, $protocols, true) ? esc_attr($value) : '';
}

require dirname(__DIR__).'/includes/class-sharetoku-plugin.php';

$method = new ReflectionMethod(ShareToku_Plugin::class, 'card');
$offer = ['public_id' => '01M3TWBF4969R7EYPHN9M9CYKF', 'service' => ['name' => '<img src=x onerror=alert(1)>'],
    'invitee_benefit' => '<script>alert(1)</script>', 'conditions' => 'terms', 'referral_code' => '<b>CODE</b>',
    'referral_url' => 'javascript:alert(1)'];
$owner = $method->invoke(null, $offer, 'owner', 'placement', []);
$operator = $method->invoke(null, $offer, 'operator', 'placement', []);

foreach (['<img', '<script', '<b>CODE</b>', 'href="javascript:'] as $unsafe) {
    if (str_contains($owner, $unsafe) || str_contains($operator, $unsafe)) {
        throw new RuntimeException('Unsafe output: '.$unsafe);
    }
}
if (! str_contains($owner, '紹介特典') || str_contains($owner, 'PR・ShareToku運営者')) {
    throw new RuntimeException('Owner disclosure is incorrect');
}
if (! str_contains($operator, 'PR・ShareToku運営者の紹介特典') || ! str_contains($operator, 'sharetoku-operator')) {
    throw new RuntimeException('Operator disclosure is missing');
}
echo "WordPress renderer checks passed\n";
