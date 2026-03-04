<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        pl_invites_get_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_invites_get_resolve_league_id();
    $pdo = pl_invites_get_db();
    $profileId = pl_invites_get_require_auth_profile_id();
    $schema = pl_invites_get_schema_info($pdo);

    if (!pl_invites_get_league_exists($pdo, $leagueId)) {
        pl_invites_get_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $currentGw = pl_invites_get_current_gw($pdo, $leagueId);

    $competitor = pl_invites_get_competitor($pdo, $profileId, $leagueId, $schema);
    $competitorId = $competitor === null ? null : (int) $competitor['competitor_id'];

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
    $items = [];
    if ($competitorId !== null) {
        $items = pl_invites_get_items($pdo, $schema, $leagueId, $competitorId, $serverTime);
    }

    $etagBuild = pl_invites_get_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        $competitorId,
        $items,
        $serverTime
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (pl_invites_get_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    echo json_encode([
        'meta' => [
            'server_time' => $serverTime,
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'league_id' => $leagueId,
            'items' => $items,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    pl_invites_get_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_invites_get_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/invites/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invites_get_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_invites_get_db(): PDO
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

function pl_invites_get_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_invites_get_schema_info(PDO $pdo): array
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
           AND table_name IN ("privateleague","privateleaguemembers","competitor","profile")'
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

function pl_invites_get_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function pl_invites_get_current_gw(PDO $pdo, int $leagueId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT gameweek
         FROM gameweeks
         WHERE league_id = :league_id
         ORDER BY (`open` = 1) DESC, gameweek DESC
         LIMIT 1'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $gw = $stmt->fetchColumn();
    if ($gw === false || $gw === null) {
        return null;
    }
    return (int) $gw;
}

function pl_invites_get_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['competitor.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT competitor_id' . $updatedPart . '
         FROM competitor
         WHERE profile_id = :profile_id
           AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_invites_get_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_invites_get_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)";
    }
    return "CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_invites_get_membership_created_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.requested_at'] ?? false) {
        return "{$alias}.requested_at";
    }
    if ($schema['privateleaguemembers.created_at'] ?? false) {
        return "{$alias}.created_at";
    }
    if ($schema['privateleaguemembers.updated_at'] ?? false) {
        return "{$alias}.updated_at";
    }
    return 'NULL';
}

function pl_invites_get_pending_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'pending' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 0))";
    }
    return "{$alias}.confirmed = 0";
}

function pl_invites_get_items(PDO $pdo, array $schema, int $leagueId, int $competitorId, string $serverTime): array
{
    $adminColumn = pl_invites_get_privateleague_admin_column($schema);
    $statusExpr = pl_invites_get_member_status_expr($schema, 'plm');
    $createdExpr = pl_invites_get_membership_created_expr($schema, 'plm');
    $pendingCondition = pl_invites_get_pending_condition($schema, 'plm');

    $stmt = $pdo->prepare(
        'SELECT
            pl.privateleague_id,
            pl.leaguename,
            COALESCE(admin_profile.alias, \'\') AS admin_alias,
            COALESCE(' . $createdExpr . ', UTC_TIMESTAMP()) AS created_at,
            ' . $statusExpr . ' AS invite_status
         FROM privateleaguemembers plm
         INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
         LEFT JOIN profile admin_profile ON admin_profile.profile_id = pl.' . $adminColumn . '
         WHERE pl.league_id = :league_id
           AND plm.competitor_id = :competitor_id
           AND ' . $pendingCondition . '
         ORDER BY pl.privateleague_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':competitor_id' => $competitorId,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $privateleagueId = (int) ($row['privateleague_id'] ?? 0);
        $status = (string) ($row['invite_status'] ?? 'pending');
        if ($status === '') {
            $status = 'pending';
        }

        $items[] = [
            'invite_id' => 'pl' . $privateleagueId . '-c' . $competitorId,
            'privateleague_id' => $privateleagueId,
            'leaguename' => (string) ($row['leaguename'] ?? ''),
            'admin_alias' => (string) ($row['admin_alias'] ?? ''),
            'created_at' => pl_invites_get_datetime_iso((string) ($row['created_at'] ?? ''), $serverTime),
            'status' => $status,
        ];
    }

    return $items;
}

function pl_invites_get_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    ?int $competitorId,
    array $items,
    string $serverTime
): array {
    $count = count($items);
    $maxPrivateleagueId = 0;
    foreach ($items as $item) {
        $maxPrivateleagueId = max($maxPrivateleagueId, (int) ($item['privateleague_id'] ?? 0));
    }

    $maxMemberTouch = '0';
    $lastUpdated = $serverTime;
    if ($competitorId !== null && ($schema['privateleaguemembers.updated_at'] ?? false)) {
        $pendingCondition = pl_invites_get_pending_condition($schema, 'plm');
        $stmt = $pdo->prepare(
            'SELECT MAX(plm.updated_at) AS max_touch
             FROM privateleaguemembers plm
             INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
             WHERE pl.league_id = :league_id
               AND plm.competitor_id = :competitor_id
               AND ' . $pendingCondition
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':competitor_id' => $competitorId,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $maxMemberTouch = (string) $value;
            $ts = strtotime($maxMemberTouch);
            if ($ts !== false) {
                $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }
    }

    $marker = [
        'pl-invites-get-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'touch:' . $maxMemberTouch,
        'cnt:' . $count,
        'maxpl:' . $maxPrivateleagueId,
    ];

    return [
        'etag' => 'W/"pl-inv-u' . $profileId . '-l' . $leagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => $lastUpdated,
    ];
}

function pl_invites_get_if_none_match_matches(string $etag): bool
{
    $headers = [];
    foreach (['HTTP_IF_NONE_MATCH', 'REDIRECT_HTTP_IF_NONE_MATCH', 'If-None-Match'] as $key) {
        if (!empty($_SERVER[$key])) {
            $headers[] = (string) $_SERVER[$key];
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'if-none-match') {
                $headers[] = (string) $value;
            }
        }
    }

    if (empty($headers)) {
        return false;
    }

    $etagRaw = trim($etag);
    $etagWeak = preg_replace('/^W\//', '', $etagRaw) ?? $etagRaw;
    $etagNorm = trim($etagWeak, "\"' \t\r\n");

    foreach ($headers as $header) {
        foreach (array_map('trim', explode(',', $header)) as $candidate) {
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
    }

    return false;
}

function pl_invites_get_authorization_header(): string
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

function pl_invites_get_require_auth_profile_id(): int
{
    $header = pl_invites_get_authorization_header();
    if ($header === '') {
        pl_invites_get_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_invites_get_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_invites_get_verify_jwt(string $token): array
{
    $secret = pl_invites_get_jwt_secret();
    if ($secret === '') {
        pl_invites_get_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_invites_get_b64url_decode($h64), true);
    $payload = json_decode((string) pl_invites_get_b64url_decode($p64), true);
    $sig = pl_invites_get_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_invites_get_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_invites_get_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_invites_get_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__, 3) . '/config/app.php';
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

function pl_invites_get_datetime_iso(string $value, string $fallback): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return $fallback;
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
