<?php

return [
    'base_domain' => env('APP_BASE_DOMAIN', parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: null),
    'central_subdomains' => array_filter(array_map('trim', explode(',', (string) env('CENTRAL_SUBDOMAINS', 'www,app,admin')))),
];
