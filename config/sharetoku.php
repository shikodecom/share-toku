<?php

return [
    'operator_workspace_public_id' => env('SHARETOKU_OPERATOR_WORKSPACE_PUBLIC_ID'),
    'operator_offers_enabled' => env('SHARETOKU_OPERATOR_OFFERS_ENABLED', true),
    'cache_ttl' => (int) env('SHARETOKU_CACHE_TTL', 300),
    'event_token_ttl' => (int) env('SHARETOKU_EVENT_TOKEN_TTL', 86400),
    'analytics_retention_days' => (int) env('SHARETOKU_ANALYTICS_RETENTION_DAYS', 90),
    'consent_version' => env('SHARETOKU_CONSENT_VERSION', '1'),
    'rate_limit_site_per_minute' => (int) env('SHARETOKU_RATE_LIMIT_SITE_PER_MINUTE', 120),
    'rate_limit_ip_per_minute' => (int) env('SHARETOKU_RATE_LIMIT_IP_PER_MINUTE', 300),
    'plans' => [
        'free' => ['max_sites' => 1, 'max_offers' => 30, 'operator_offer_enabled' => true, 'analytics_level' => 'basic', 'design_customization' => false, 'api_access_level' => 'site'],
        'pro' => ['max_sites' => 3, 'max_offers' => 300, 'operator_offer_enabled' => false, 'analytics_level' => 'detailed', 'design_customization' => true, 'api_access_level' => 'site'],
    ],
];
