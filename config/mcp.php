<?php

return [
    'token' => env('MCP_SERVER_TOKEN', ''),
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('MCP_ALLOWED_ORIGINS', ''))
    ))),
];
