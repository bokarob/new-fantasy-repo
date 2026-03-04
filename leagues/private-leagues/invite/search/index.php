<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        pl_invite_search_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_invite_search_resolve_league_id();
    $privateleagueId = pl_invite_search_resolve_privateleague_id();
    $q = pl_invite_search_resolve_q();
    $limit = pl_invite_search_resolve_limit();

    $pdo = pl_invite_search_db();
    $profileId = pl_invite_search_require_auth_profile_id();
    $schema = pl_invite_search_schema_info($pdo);

    $privateleague = pl_invite_search_privateleague($pdo, $schema, $leagueId, $privateleagueId);
    if ($privateleague === null) {
        pl_invite_search_error(404, 'PRIVATE_LEAGUE_NOT_FOUND', 'Private league not found.');
    }

    $adminProfileId = (int) ($privateleague['admin_profile_id'] ?? 0);
    if ($profileId !== $adminProfileId) {
        pl_invite_search_error(403, 'NOT_ADMIN', 'Admin privileges required for action.');
    }

    $gw = pl_invite_search_current_gameweek($pdo, $leagueId, $schema);
    $currentGw = $gw === null ? 0 : (int) $gw['gw'];

    $adminCompetitor = pl_invite_search_competitor_by_profile($pdo, $profileId, $leagueId, $schema);
    $excludeCompetitorId = $adminCompetitor === null ? null : (int) $adminCompetitor['competitor_id'];

    $items = pl_invite_search_items($pdo, $schema, $leagueId, $privateleagueId, $q, $limit, $excludeCompetitorId);
    $etagBuild = pl_invite_search_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        $privateleagueId,
        $q,
        $limit,
        $items,
        $gw ?? []
    );

    header('Cache-Control: private, max-age=30');
    header('ETag: ' . $etagBuild['etag']);

    if (pl_invite_search_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
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
            'q' => $q,
            'items' => $items,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    pl_invite_search_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_invite_search_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/\d+/invite/search/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invite_search_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_invite_search_resolve_privateleague_id(): int
{
    $raw = isset($_GET['privateleague_id']) ? (string) $_GET['privateleague_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/(\d+)/invite/search/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invite_search_error(400, 'BAD_REQUEST', 'Invalid privateleague_id.');
    }
    return (int) $raw;
}

function pl_invite_search_resolve_q(): string
{
    if (!array_key_exists('q', $_GET) || !is_string($_GET['q'])) {
        pl_invite_search_error(422, 'QUERY_TOO_SHORT', 'Query is too short.');
    }

    $q = trim((string) $_GET['q']);
    if (mb_strlen($q, 'UTF-8') < 2) {
        pl_invite_search_error(422, 'QUERY_TOO_SHORT', 'Query is too short.');
    }

    return $q;
}

function pl_invite_search_resolve_limit(): int
{
    if (!isset($_GET['limit'])) {
        return 10;
    }

    $raw = (string) $_GET['limit'];
    if ($raw === '' || !ctype_digit($raw)) {
        pl_invite_search_error(400, 'BAD_REQUEST', 'Invalid limit.');
    }

    $value = (int) $raw;
    if ($value <= 0) {
        pl_invite_search_error(400, 'BAD_REQUEST', 'Invalid limit.');
    }

    return min(25, $value);
}

function pl_invite_search_db(): PDO
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

function pl_invite_search_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_invite_search_schema_info(PDO $pdo): array
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
           AND table_name IN ("gameweeks","privateleague","privateleaguemembers","competitor","profile")'
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

function pl_invite_search_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_invite_search_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "CASE WHEN {$alias}.competitor_id IS NULL THEN '' ELSE COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END) END";
    }
    return "CASE WHEN {$alias}.competitor_id IS NULL THEN '' WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_invite_search_privateleague(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): ?array
{
    $adminColumn = pl_invite_search_privateleague_admin_column($schema);
    $stmt = $pdo->prepare(
        'SELECT privateleague_id, league_id, COALESCE(' . $adminColumn . ', 0) AS admin_profile_id
         FROM privateleague
         WHERE league_id = :league_id
           AND privateleague_id = :privateleague_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_invite_search_current_gameweek(PDO $pdo, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['gameweeks.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT gameweek, deadline, `open`' . $updatedPart . '
         FROM gameweeks
         WHERE league_id = :league_id
         ORDER BY (`open` = 1) DESC, gameweek DESC
         LIMIT 1'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'gw' => (int) $row['gameweek'],
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function pl_invite_search_competitor_by_profile(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
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

function pl_invite_search_items(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $privateleagueId,
    string $q,
    int $limit,
    ?int $excludeCompetitorId
): array {
    $statusExpr = pl_invite_search_member_status_expr($schema, 'plm');
    $needle = '%' . pl_invite_search_escape_like($q) . '%';

    $sql =
        'SELECT
            c.competitor_id,
            c.profile_id,
            COALESCE(p.alias, \'\') AS alias,
            COALESCE(c.teamname, \'\') AS teamname,
            ' . $statusExpr . ' AS membership_status
         FROM competitor c
         LEFT JOIN profile p ON p.profile_id = c.profile_id
         LEFT JOIN privateleaguemembers plm
            ON plm.privateleague_id = :privateleague_id
           AND plm.competitor_id = c.competitor_id
         WHERE c.league_id = :league_id
           AND (
                LOWER(COALESCE(p.alias, \'\')) LIKE LOWER(:needle) ESCAPE \'\\\\\'
                OR LOWER(COALESCE(c.teamname, \'\')) LIKE LOWER(:needle) ESCAPE \'\\\\\'
           )';

    $params = [
        ':privateleague_id' => $privateleagueId,
        ':league_id' => $leagueId,
        ':needle' => $needle,
    ];

    if ($excludeCompetitorId !== null) {
        $sql .= ' AND c.competitor_id <> :exclude_competitor_id';
        $params[':exclude_competitor_id'] = $excludeCompetitorId;
    }

    $sql .= ' ORDER BY COALESCE(p.alias, \'\') ASC, COALESCE(c.teamname, \'\') ASC, c.competitor_id ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $status = (string) ($row['membership_status'] ?? '');
        $items[] = [
            'competitor_id' => (int) ($row['competitor_id'] ?? 0),
            'profile_id' => (int) ($row['profile_id'] ?? 0),
            'alias' => (string) ($row['alias'] ?? ''),
            'teamname' => (string) ($row['teamname'] ?? ''),
            'already_member' => $status === 'member_confirmed',
            'already_invited' => $status === 'pending',
        ];
    }

    return $items;
}

function pl_invite_search_escape_like(string $input): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $input);
}

function pl_invite_search_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    int $privateleagueId,
    string $q,
    int $limit,
    array $items,
    array $gw
): array {
    $marker = [
        'pl-invite-search-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'pl:' . $privateleagueId,
        'q:' . mb_strtolower($q, 'UTF-8'),
        'limit:' . $limit,
    ];

    foreach ($items as $item) {
        $marker[] = implode(':', [
            (int) ($item['competitor_id'] ?? 0),
            (int) ($item['profile_id'] ?? 0),
            (string) ($item['alias'] ?? ''),
            (string) ($item['teamname'] ?? ''),
            ($item['already_member'] ?? false) ? '1' : '0',
            ($item['already_invited'] ?? false) ? '1' : '0',
        ]);
    }

    $timestamps = [];
    if (!empty($gw['updated_at'])) {
        $ts = strtotime((string) $gw['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }
    if ($schema['privateleague.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT updated_at FROM privateleague WHERE privateleague_id = :privateleague_id LIMIT 1');
        $stmt->execute([':privateleague_id' => $privateleagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if ($schema['privateleaguemembers.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM privateleaguemembers WHERE privateleague_id = :privateleague_id');
        $stmt->execute([':privateleague_id' => $privateleagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if ($schema['competitor.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM competitor WHERE league_id = :league_id');
        $stmt->execute([':league_id' => $leagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if ($schema['profile.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(p.updated_at)
             FROM profile p
             INNER JOIN competitor c ON c.profile_id = p.profile_id
             WHERE c.league_id = :league_id'
        );
        $stmt->execute([':league_id' => $leagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    return [
        'etag' => 'W/"pl-invite-search-u' . $profileId . '-l' . $leagueId . '-pl' . $privateleagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function pl_invite_search_if_none_match_matches(string $etag): bool
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

function pl_invite_search_authorization_header(): string
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

function pl_invite_search_require_auth_profile_id(): int
{
    $header = pl_invite_search_authorization_header();
    if ($header === '') {
        pl_invite_search_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_invite_search_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_invite_search_verify_jwt(string $token): array
{
    $secret = pl_invite_search_jwt_secret();
    if ($secret === '') {
        pl_invite_search_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_invite_search_b64url_decode($h64), true);
    $payload = json_decode((string) pl_invite_search_b64url_decode($p64), true);
    $sig = pl_invite_search_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_invite_search_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_invite_search_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_invite_search_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__, 5) . '/config/app.php';
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
