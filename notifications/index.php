<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        notifications_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $filter = notifications_query_filter();
    $cursor = notifications_query_cursor();
    $limit = notifications_query_limit();

    $pdo = notifications_db();
    $profileId = notifications_require_auth_profile_id();
    $schema = notifications_schema_info($pdo);
    $langId = notifications_lang_id($pdo, $profileId, $schema);
    $serverTime = gmdate('Y-m-d\TH:i:s\Z');

    $unreadCount = notifications_unread_count($pdo, $profileId, $schema);
    $items = notifications_page_items($pdo, $profileId, $langId, $schema, $filter, $cursor, $limit, $serverTime);

    $nextCursor = null;
    if (count($items) > $limit) {
        $edge = $items[$limit - 1] ?? null;
        if (is_array($edge) && isset($edge['notification_id'])) {
            $nextCursor = (string) ((int) $edge['notification_id']);
        }
        $items = array_slice($items, 0, $limit);
    }

    $etagBuild = notifications_etag_and_last_updated(
        $pdo,
        $profileId,
        $schema,
        $filter,
        $cursor,
        $limit,
        $unreadCount,
        $serverTime
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    $ifNoneMatch = notifications_if_none_match_raw();
    if (notifications_if_none_match_matches($etagBuild['etag'], $ifNoneMatch)) {
        http_response_code(304);
        exit;
    }

    $responseItems = [];
    foreach ($items as $row) {
        $item = [
            'notification_id' => (int) $row['notification_id'],
            'type' => (string) ($row['notification_type'] ?? ''),
            'title' => (string) ($row['title'] ?? 'Notification'),
            'created_at' => notifications_datetime_iso((string) ($row['created_at'] ?? $serverTime), $serverTime),
            'is_read' => ((int) ($row['is_read'] ?? 0)) === 1,
        ];

        $body = $row['body'] ?? null;
        if ($body !== null && (string) $body !== '') {
            $item['body'] = (string) $body;
        }

        $targetKind = trim((string) ($row['target_kind'] ?? ''));
        if ($targetKind !== '') {
            $target = [
                'kind' => $targetKind,
                'league_id' => isset($row['target_league_id']) && $row['target_league_id'] !== null
                    ? (int) $row['target_league_id']
                    : null,
                'params' => notifications_target_params((string) ($row['target_params'] ?? '')),
            ];
            $item['target'] = $target;
        }

        $responseItems[] = $item;
    }

    echo json_encode([
        'meta' => [
            'server_time' => $serverTime,
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'unread_count' => $unreadCount,
            'items' => $responseItems,
            'next_cursor' => $nextCursor,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    notifications_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function notifications_query_filter(): string
{
    if (!array_key_exists('filter', $_GET)) {
        return 'all';
    }
    $filter = trim((string) $_GET['filter']);
    if ($filter !== 'all' && $filter !== 'unread') {
        notifications_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $filter;
}

function notifications_query_cursor(): ?int
{
    if (!array_key_exists('cursor', $_GET)) {
        return null;
    }
    $raw = trim((string) $_GET['cursor']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        notifications_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function notifications_query_limit(): int
{
    if (!array_key_exists('limit', $_GET)) {
        return 20;
    }
    $raw = trim((string) $_GET['limit']);
    if ($raw === '' || !ctype_digit($raw)) {
        notifications_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    $n = (int) $raw;
    if ($n <= 0 || $n > 50) {
        notifications_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $n;
}

function notifications_db(): PDO
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

function notifications_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function notifications_schema_info(PDO $pdo): array
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
           AND table_name IN ("notification","notificationtype","notificationtext","profile")'
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

function notifications_unread_condition(array $schema, string $alias = 'n'): string
{
    if ($schema['notification.read_at'] ?? false) {
        return $alias . '.read_at IS NULL';
    }
    return $alias . '.mark_read = 0';
}

function notifications_lang_id(PDO $pdo, int $profileId, array $schema): int
{
    if (!($schema['profile.lang_id'] ?? false)) {
        return 1;
    }
    $stmt = $pdo->prepare('SELECT lang_id FROM profile WHERE profile_id = :profile_id LIMIT 1');
    $stmt->execute([':profile_id' => $profileId]);
    $langId = $stmt->fetchColumn();
    if ($langId === false || $langId === null) {
        return 1;
    }
    return max(1, (int) $langId);
}

function notifications_unread_count(PDO $pdo, int $profileId, array $schema): int
{
    $unreadCondition = notifications_unread_condition($schema, 'n');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification n WHERE n.profile_id = :profile_id AND {$unreadCondition}");
    $stmt->execute([':profile_id' => $profileId]);
    return (int) $stmt->fetchColumn();
}

function notifications_page_items(
    PDO $pdo,
    int $profileId,
    int $langId,
    array $schema,
    string $filter,
    ?int $cursor,
    int $limit,
    string $serverTime
): array {
    $hasCreatedAt = $schema['notification.created_at'] ?? false;
    $hasReadAt = $schema['notification.read_at'] ?? false;
    $hasTitle = $schema['notification.title'] ?? false;
    $hasBody = $schema['notification.body'] ?? false;
    $hasTargetKind = $schema['notification.target_kind'] ?? false;
    $hasTargetLeagueId = $schema['notification.target_league_id'] ?? false;
    $hasTargetParams = $schema['notification.target_params'] ?? false;
    $hasTypeName = $schema['notificationtype.name'] ?? false;
    $hasText = $schema['notificationtext.text'] ?? false;

    $titleExpr = $hasTitle
        ? "COALESCE(NULLIF(TRIM(n.title), ''), " . ($hasTypeName ? "ntype.name, " : '') . "n.notification_type, 'Notification')"
        : ($hasTypeName ? "COALESCE(ntype.name, n.notification_type, 'Notification')" : "COALESCE(n.notification_type, 'Notification')");
    $bodyExpr = $hasBody
        ? 'n.body'
        : ($hasText ? 'ntext.text' : 'NULL');
    $createdExpr = $hasCreatedAt ? 'n.created_at' : 'UTC_TIMESTAMP()';
    $isReadExpr = $hasReadAt
        ? 'CASE WHEN n.read_at IS NULL THEN 0 ELSE 1 END'
        : 'CASE WHEN n.mark_read = 1 THEN 1 ELSE 0 END';

    $targetKindExpr = $hasTargetKind ? 'n.target_kind' : 'NULL';
    $targetLeagueExpr = $hasTargetLeagueId ? 'n.target_league_id' : 'NULL';
    $targetParamsExpr = $hasTargetParams ? 'n.target_params' : 'NULL';

    $joins = [];
    if ($hasTypeName) {
        $joins[] = 'LEFT JOIN notificationtype ntype ON ntype.notification_type = n.notification_type';
    }
    if ($hasText) {
        $joins[] = 'LEFT JOIN notificationtext ntext ON ntext.notification_type = n.notification_type AND ntext.lang_id = :lang_id';
    }

    $where = ['n.profile_id = :profile_id'];
    $params = [
        ':profile_id' => $profileId,
    ];
    if ($hasText) {
        $params[':lang_id'] = $langId;
    }
    if ($filter === 'unread') {
        $where[] = notifications_unread_condition($schema, 'n');
    }
    if ($cursor !== null) {
        $where[] = 'n.notification_id < :cursor';
        $params[':cursor'] = $cursor;
    }

    $order = $hasCreatedAt
        ? 'n.created_at DESC, n.notification_id DESC'
        : 'n.notification_id DESC';

    $sql = 'SELECT
                n.notification_id,
                n.notification_type,
                ' . $titleExpr . ' AS title,
                ' . $bodyExpr . ' AS body,
                ' . $createdExpr . ' AS created_at,
                ' . $isReadExpr . ' AS is_read,
                ' . $targetKindExpr . ' AS target_kind,
                ' . $targetLeagueExpr . ' AS target_league_id,
                ' . $targetParamsExpr . ' AS target_params
            FROM notification n
            ' . implode("\n", $joins) . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $order . '
            LIMIT ' . ((int) $limit + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    if (!$hasCreatedAt) {
        foreach ($rows as &$row) {
            if (empty($row['created_at'])) {
                $row['created_at'] = $serverTime;
            }
        }
        unset($row);
    }

    return $rows;
}

function notifications_etag_and_last_updated(
    PDO $pdo,
    int $profileId,
    array $schema,
    string $filter,
    ?int $cursor,
    int $limit,
    int $unreadCount,
    string $serverTime
): array {
    $maxId = 0;
    $maxTsMarker = '0';
    $lastUpdated = $serverTime;

    $tsExpr = null;
    if (($schema['notification.updated_at'] ?? false) && ($schema['notification.created_at'] ?? false)) {
        $tsExpr = 'COALESCE(updated_at, created_at)';
    } elseif ($schema['notification.updated_at'] ?? false) {
        $tsExpr = 'updated_at';
    } elseif ($schema['notification.created_at'] ?? false) {
        $tsExpr = 'created_at';
    }

    if ($tsExpr !== null) {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(notification_id), 0) AS max_id, MAX(' . $tsExpr . ') AS max_ts
             FROM notification
             WHERE profile_id = :profile_id'
        );
        $stmt->execute([':profile_id' => $profileId]);
        $row = $stmt->fetch();
        if ($row) {
            $maxId = (int) ($row['max_id'] ?? 0);
            if (!empty($row['max_ts'])) {
                $maxTsMarker = (string) $row['max_ts'];
                $ts = strtotime($maxTsMarker);
                if ($ts !== false) {
                    $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $ts);
                }
            }
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(notification_id), 0) AS max_id
             FROM notification
             WHERE profile_id = :profile_id'
        );
        $stmt->execute([':profile_id' => $profileId]);
        $maxId = (int) $stmt->fetchColumn();
    }

    $marker = 'f:' . $filter
        . '|c:' . ($cursor === null ? '' : (string) $cursor)
        . '|l:' . $limit
        . '|u:' . $unreadCount
        . '|max:' . $maxId
        . '|ts:' . $maxTsMarker;

    return [
        'etag' => 'W/"notif-u' . $profileId . '-' . sha1($marker) . '"',
        'last_updated' => $lastUpdated,
    ];
}

function notifications_target_params(string $raw)
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return new stdClass();
    }

    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return new stdClass();
    }
    return (object) $decoded;
}

function notifications_if_none_match_raw(): string
{
    foreach (['HTTP_IF_NONE_MATCH', 'REDIRECT_HTTP_IF_NONE_MATCH', 'If-None-Match'] as $key) {
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'if-none-match') {
                return trim((string) $value);
            }
        }
    }
    return '';
}

function notifications_if_none_match_matches(string $etag, string $ifNoneMatchRaw): bool
{
    if ($ifNoneMatchRaw === '') {
        return false;
    }

    $etagRaw = trim($etag);
    $etagWeak = preg_replace('/^W\//', '', $etagRaw) ?? $etagRaw;
    $etagNorm = trim($etagWeak, "\"' \t\r\n");

    foreach (array_map('trim', explode(',', $ifNoneMatchRaw)) as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if ($candidate === '*') {
            return true;
        }

        $candidateRaw = trim($candidate);
        $candidateWeak = preg_replace('/^W\//', '', $candidateRaw) ?? $candidateRaw;
        $candidateNorm = trim($candidateWeak, "\"' \t\r\n");
        $candidateNorm = str_replace('\\"', '"', $candidateNorm);

        if ($candidateRaw === $etagRaw || $candidateWeak === $etagWeak || $candidateNorm === $etagNorm) {
            return true;
        }
    }

    return false;
}

function notifications_authorization_header(): string
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

function notifications_require_auth_profile_id(): int
{
    $header = notifications_authorization_header();
    if ($header === '') {
        notifications_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = notifications_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function notifications_verify_jwt(string $token): array
{
    $secret = notifications_jwt_secret();
    if ($secret === '') {
        notifications_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) notifications_b64url_decode($h64), true);
    $payload = json_decode((string) notifications_b64url_decode($p64), true);
    $sig = notifications_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        notifications_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function notifications_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function notifications_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__) . '/config/app.php';
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

function notifications_datetime_iso(string $value, string $fallback): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return $fallback;
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
