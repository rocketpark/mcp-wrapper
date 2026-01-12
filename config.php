<?php

return [
    // Map schema handles to GraphQL tokens
    'schemas' => [
        'jensenhughes' => getenv('MCP_GQLSCHEMA_TOKEN'),
        'ai'           => getenv('GQL_AI_TOKEN'),
        'frontend'     => getenv('GQL_FRONTEND_TOKEN'),
        'internal'     => getenv('GQL_INTERNAL_TOKEN'),
    ],
];