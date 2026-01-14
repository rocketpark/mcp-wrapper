<?php

return [
    // Map schema handles to GraphQL tokens
    'schemas' => [
        'jensenhughes' => getenv('MCP_GQLSCHEMA_TOKEN'),
        'ai'           => getenv('GQL_AI_TOKEN'),
        'frontend'     => getenv('GQL_FRONTEND_TOKEN'),
        'internal'     => getenv('GQL_INTERNAL_TOKEN'),
    ],

    // Security configuration
    'security' => [
        // IP whitelist (empty = allow all)
        // Supports CIDR notation (e.g., "10.0.0.0/8")
        'allowedIps' => [
            '127.0.0.1',      // localhost IPv4
            '::1',            // localhost IPv6
            // Add your production IPs here
            // '10.0.0.0/8',  // Private network
        ],

        // Require authentication for all requests
        'requireAuth' => true,

        // Enable dangerous operations (mutations, deletions)
        // Set to false in production to restrict to read-only queries
        'enableDangerousTools' => getenv('CRAFT_ENVIRONMENT') === 'dev',

        // Disabled tools (won't appear in manifest)
        // Useful for preventing specific operations in production
        'disabledTools' => [
            // Example:
            // 'deleteEntry',
            // 'saveUser',
        ],
    ],
];