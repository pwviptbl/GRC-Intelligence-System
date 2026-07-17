<?php

return [
    'token' => env('MCP_SERVER_TOKEN', ''),
    'tokens' => array_values(array_filter(array_map(
        static fn (string $token): string => trim($token),
        explode(',', (string) env('MCP_SERVER_TOKENS', env('MCP_SERVER_TOKEN', '')))
    ))),
    'allow_unauthenticated' => (bool) env('MCP_ALLOW_UNAUTHENTICATED', false),
    'rate_limit_per_minute' => max(1, (int) env('MCP_RATE_LIMIT_PER_MINUTE', 120)),
    'write_rate_limit_per_minute' => max(1, (int) env('MCP_WRITE_RATE_LIMIT_PER_MINUTE', 30)),
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('MCP_ALLOWED_ORIGINS', ''))
    ))),
];
