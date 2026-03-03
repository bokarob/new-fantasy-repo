<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        matches_list_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = matches_list_resolve_league_id();
    $gwParam = matches_list_resolve_gw_param();

    $pdo = matches_list_db();
    matches_list_require_auth_profile_id();
    $schema = matches_list_schema_info($pdo);

    if (!matches_list_league_exists($pdo, $leagueId)) {
        matches_list_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gwRows = matches_list_gameweeks($pdo, $leagueId);
    if (empty($gwRows)) {
        matches_list_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $currentGw = matches_list_pick_current_gw($gwRows);
    $selectedGw = $gwParam ?? $currentGw;

    if (!in_array($selectedGw, array_column($gwRows, 'gw'), true)) {
        matches_list_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $items = matches_list_items($pdo, $leagueId, $selectedGw, $schema);
    $etagBuild = matches_list_etag_and_last_updated($pdo, $leagueId, $selectedGw, $schema);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (matches_list_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'league_id' => $leagueId,
            'gw' => $selectedGw,
            'gw_picker' => [
                'selected_gw' => $selectedGw,
                'available_gws' => array_column($gwRows, 'gw'),
            ],
            'items' => $items,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    matches_list_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function matches_list_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/matches/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        matches_list_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function matches_list_resolve_gw_param(): ?int
{
    if (!array_key_exists('gw', $_GET)) {
        return null;
    }

    $raw = trim((string) $_GET['gw']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        matches_list_error(400, 'BAD_REQUEST', 'Invalid gw.');
    }
    return (int) $raw;
}

function matches_list_db(): PDO
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

function matches_list_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function matches_list_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","matches","team")'
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

function matches_list_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function matches_list_gameweeks(PDO $pdo, int $leagueId): array
{
    $stmt = $pdo->prepare(
        'SELECT gameweek, deadline, `open`
         FROM gameweeks
         WHERE league_id = :league_id
         ORDER BY gameweek ASC'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $rows = $stmt->fetchAll() ?: [];

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'gw' => (int) $row['gameweek'],
            'open' => (int) $row['open'],
            'deadline' => (string) $row['deadline'],
        ];
    }

    return $out;
}

function matches_list_pick_current_gw(array $gwRows): int
{
    $openRows = array_values(array_filter($gwRows, static function (array $row): bool {
        return (int) $row['open'] === 1;
    }));

    if (!empty($openRows)) {
        usort($openRows, static function (array $a, array $b): int {
            return (int) $b['gw'] <=> (int) $a['gw'];
        });
        return (int) $openRows[0]['gw'];
    }

    $all = $gwRows;
    usort($all, static function (array $a, array $b): int {
        return (int) $b['gw'] <=> (int) $a['gw'];
    });

    return (int) $all[0]['gw'];
}

function matches_list_items(PDO $pdo, int $leagueId, int $gw, array $schema): array
{
    $statusExpr = ($schema['matches.status'] ?? false) ? 'm.`status` AS raw_status' : 'NULL AS raw_status';
    $updatedExpr = ($schema['matches.updated_at'] ?? false) ? 'm.updated_at AS updated_at' : 'NULL AS updated_at';
    $teamLogoExpr = ($schema['team.logo'] ?? false) ? 'COALESCE(%s.logo, \'\')' : '\'\'';

    $sql = 'SELECT
                m.match_id,
                m.gameweek,
                m.hometeam,
                m.awayteam,
                m.homepoint,
                m.awaypoint,
                ' . $statusExpr . ',
                ' . $updatedExpr . ',
                ht.team_id AS home_team_id,
                ht.short AS home_short,
                ht.name AS home_name,
                ' . sprintf($teamLogoExpr, 'ht') . ' AS home_logo,
                at.team_id AS away_team_id,
                at.short AS away_short,
                at.name AS away_name,
                ' . sprintf($teamLogoExpr, 'at') . ' AS away_logo
            FROM matches m
            LEFT JOIN team ht ON ht.team_id = m.hometeam AND ht.league_id = m.league_id
            LEFT JOIN team at ON at.team_id = m.awayteam AND at.league_id = m.league_id
            WHERE m.league_id = :league_id
              AND m.gameweek = :gw
            ORDER BY m.match_id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $homePoint = (float) ($row['homepoint'] ?? 0.0);
        $awayPoint = (float) ($row['awaypoint'] ?? 0.0);
        $status = matches_list_map_status($row['raw_status'] ?? null, $homePoint, $awayPoint);
        $result = $status === 'finished'
            ? ['home' => $homePoint, 'away' => $awayPoint]
            : null;

        $items[] = [
            'match_id' => (int) $row['match_id'],
            'gw' => (int) $row['gameweek'],
            'status' => $status,
            'home_team' => [
                'team_id' => (int) ($row['home_team_id'] ?? $row['hometeam'] ?? 0),
                'short' => (string) ($row['home_short'] ?? ''),
                'name' => (string) ($row['home_name'] ?? ''),
                'logo_url' => (string) ($row['home_logo'] ?? ''),
            ],
            'away_team' => [
                'team_id' => (int) ($row['away_team_id'] ?? $row['awayteam'] ?? 0),
                'short' => (string) ($row['away_short'] ?? ''),
                'name' => (string) ($row['away_name'] ?? ''),
                'logo_url' => (string) ($row['away_logo'] ?? ''),
            ],
            'result' => $result,
        ];
    }

    return $items;
}

function matches_list_map_status($rawStatus, float $homePoint, float $awayPoint): string
{
    $allowed = [
        'scheduled' => true,
        'finished' => true,
        'postponed' => true,
        'cancelled' => true,
    ];

    if (is_string($rawStatus)) {
        $norm = strtolower(trim($rawStatus));
        if (isset($allowed[$norm])) {
            return $norm;
        }
        if ($norm === 'in_progress') {
            return 'scheduled';
        }
    }

    if ($homePoint > 0.0 || $awayPoint > 0.0) {
        return 'finished';
    }

    return 'scheduled';
}

function matches_list_etag_and_last_updated(PDO $pdo, int $leagueId, int $gw, array $schema): array
{
    if ($schema['matches.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS match_count,
                COALESCE(MAX(updated_at), "1970-01-01 00:00:00") AS max_updated_at,
                COALESCE(MAX(match_id), 0) AS max_match_id,
                COALESCE(SUM(homepoint), 0) AS sum_home,
                COALESCE(SUM(awaypoint), 0) AS sum_away
             FROM matches
             WHERE league_id = :league_id
               AND gameweek = :gw'
        );
        $stmt->execute([':league_id' => $leagueId, ':gw' => $gw]);
        $row = $stmt->fetch() ?: [];
        $maxUpdated = (string) ($row['max_updated_at'] ?? '1970-01-01 00:00:00');
    } else {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS match_count,
                COALESCE(MAX(match_id), 0) AS max_match_id,
                COALESCE(SUM(homepoint), 0) AS sum_home,
                COALESCE(SUM(awaypoint), 0) AS sum_away
             FROM matches
             WHERE league_id = :league_id
               AND gameweek = :gw'
        );
        $stmt->execute([':league_id' => $leagueId, ':gw' => $gw]);
        $row = $stmt->fetch() ?: [];
        $maxUpdated = '';
    }

    $markerParts = [
        'matches-v1',
        'l:' . $leagueId,
        'gw:' . $gw,
        'cnt:' . (int) ($row['match_count'] ?? 0),
        'umax:' . $maxUpdated,
        'mmax:' . (int) ($row['max_match_id'] ?? 0),
        'sumh:' . (string) ($row['sum_home'] ?? '0'),
        'suma:' . (string) ($row['sum_away'] ?? '0'),
    ];

    $lastUpdatedTs = strtotime($maxUpdated);
    if ($lastUpdatedTs === false || $lastUpdatedTs <= 0) {
        $lastUpdatedTs = time();
    }

    return [
        'etag' => 'W/"matches-l' . $leagueId . '-gw' . $gw . '-' . sha1(implode('|', $markerParts)) . '"',
        'last_updated' => gmdate('Y-m-d\\TH:i:s\\Z', $lastUpdatedTs),
    ];
}

function matches_list_if_none_match_matches(string $etag): bool
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

function matches_list_authorization_header(): string
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

function matches_list_require_auth_profile_id(): int
{
    $header = matches_list_authorization_header();
    if ($header === '') {
        matches_list_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = matches_list_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function matches_list_verify_jwt(string $token): array
{
    $secret = matches_list_jwt_secret();
    if ($secret === '') {
        matches_list_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) matches_list_b64url_decode($h64), true);
    $payload = json_decode((string) matches_list_b64url_decode($p64), true);
    $sig = matches_list_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        matches_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function matches_list_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function matches_list_jwt_secret(): string
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