<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        notification_readall_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    header('Cache-Control: no-store');

    $pdo = notification_readall_db();
    $profileId = notification_readall_require_auth_profile_id();
    $schema = notification_readall_schema_info($pdo);

    $readCount = notification_readall_mark_all_as_read($pdo, $schema, $profileId);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    echo json_encode([
        'meta' => [
            'server_time' => $now,
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $now,
            'etag' => null,
        ],
        'data' => [
            'ok' => true,
            'read_count' => $readCount,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    notification_readall_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function notification_readall_db(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'fantasy_app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    return new PDO(
        "mysql:host={$host};dbname={$db};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function notification_readall_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function notification_readall_schema_info(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $dbName = getenv('DB_NAME') ?: 'fantasy_app';
    $stmt = $pdo->prepare(
        'SELECT table_name, column_name
         FROM information_schema.columns
         WHERE table_schema = :db
           AND table_name IN ("notification")'
    );
    $stmt->execute([':db' => $dbName]);
    $rows = $stmt->fetchAll() ?: [];

    $out = [];
    foreach ($rows as $row) {
        $out[(string) $row['table_name'] . '.' . (string) $row['column_name']] = true;
    }
    $cache = $out;
    return $cache;
}

function notification_readall_mark_all_as_read(PDO $pdo, array $schema, int $profileId): int
{
    $hasReadAt = $schema['notification.read_at'] ?? false;
    $hasUpdatedAt = $schema['notification.updated_at'] ?? false;
    $hasMarkRead = $schema['notification.mark_read'] ?? false;

    if ($hasReadAt) {
        $set = ['read_at = COALESCE(read_at, UTC_TIMESTAMP())'];
        if ($hasMarkRead) {
            $set[] = 'mark_read = 1';
        }
        if ($hasUpdatedAt) {
            $set[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        $sql = 'UPDATE notification
                SET ' . implode(', ', $set) . '
                WHERE profile_id = :profile_id
                  AND read_at IS NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':profile_id' => $profileId]);
        return (int) $stmt->rowCount();
    }

    $set = [];
    if ($hasMarkRead) {
        $set[] = 'mark_read = 1';
    }
    if ($hasUpdatedAt) {
        $set[] = 'updated_at = CURRENT_TIMESTAMP';
    }
    if (empty($set)) {
        return 0;
    }

    $where = $hasMarkRead ? ' AND mark_read = 0' : '';
    $sql = 'UPDATE notification
            SET ' . implode(', ', $set) . '
            WHERE profile_id = :profile_id' . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':profile_id' => $profileId]);
    return (int) $stmt->rowCount();
}

function notification_readall_authorization_header(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }
    return '';
}

function notification_readall_require_auth_profile_id(): int
{
    $header = notification_readall_authorization_header();
    if ($header === '') {
        notification_readall_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = notification_readall_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function notification_readall_verify_jwt(string $token): array
{
    $secret = notification_readall_jwt_secret();
    if ($secret === '') {
        notification_readall_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) notification_readall_b64url_decode($h64), true);
    $payload = json_decode((string) notification_readall_b64url_decode($p64), true);
    $sig = notification_readall_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        notification_readall_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function notification_readall_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function notification_readall_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__, 2) . '/config/app.php';
    if (is_file($configPath)) {
        $config = require $configPath;
        if (is_array($config)) {
            $fallback = trim((string) ($config['jwt_secret'] ?? ''));
            if ($fallback !== '') {
                return $fallback;
            }
        }
    }
    return '';
}
