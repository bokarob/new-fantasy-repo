<?php

declare(strict_types=1);

return [
    // Local fallback so auth/home can run without process-level env wiring.
    'jwt_secret' => getenv('JWT_SECRET') ?: 'local-dev-jwt-secret-change-me',
];
