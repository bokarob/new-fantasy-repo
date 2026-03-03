<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        player_stats_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = player_stats_resolve_league_id();
    $teamId = player_stats_query_team_id();
    $sort = player_stats_query_sort();
    $limit = player_stats_query_limit();
    $offset = player_stats_query_offset();
    $weekGwParam = player_stats_query_week_gw();

    $pdo = player_stats_db();
    player_stats_require_auth_profile_id();
    $schema = player_stats_schema_info($pdo);

    if (!player_stats_league_exists($pdo, $leagueId)) {
        player_stats_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gwRows = player_stats_gameweeks($pdo, $leagueId);
    if (empty($gwRows)) {
        player_stats_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $currentGw = player_stats_pick_current_gw($gwRows);
    $totalsThroughGw = player_stats_pick_totals_through_gw($gwRows);
    $weekGw = $weekGwParam ?? $totalsThroughGw;

    if (!in_array($weekGw, array_column($gwRows, 'gw'), true)) {
        player_stats_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $allRows = player_stats_aggregate_rows($pdo, $leagueId, $totalsThroughGw, $weekGw, $teamId, $schema);
    $total = count($allRows);
    $sortedRows = player_stats_sort_rows($allRows, $sort);
    $pageRows = array_slice($sortedRows, $offset, $limit);
    $items = player_stats_items($pageRows);

    $etagBuild = player_stats_etag_and_last_updated(
        $pdo,
        $schema,
        $leagueId,
        $weekGw,
        $teamId,
        $sort,
        $limit,
        $offset,
        $totalsThroughGw,
        $items,
        $total
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (player_stats_if_none_match_matches($etagBuild['etag'])) {
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
            'totals_through_gw' => $totalsThroughGw,
            'week_gw' => $weekGw,
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    player_stats_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function player_stats_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/stats/players/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function player_stats_query_team_id(): ?int
{
    if (!array_key_exists('team_id', $_GET)) {
        return null;
    }
    $raw = trim((string) $_GET['team_id']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function player_stats_query_sort(): string
{
    $allowed = ['total_points_desc', 'avg_points_desc', 'last_gw_points_desc'];
    if (!array_key_exists('sort', $_GET)) {
        return 'total_points_desc';
    }
    $sort = trim((string) $_GET['sort']);
    if (!in_array($sort, $allowed, true)) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $sort;
}

function player_stats_query_limit(): int
{
    if (!array_key_exists('limit', $_GET)) {
        return 50;
    }
    $raw = trim((string) $_GET['limit']);
    if ($raw === '' || !ctype_digit($raw)) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    $n = (int) $raw;
    if ($n <= 0 || $n > 200) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $n;
}

function player_stats_query_offset(): int
{
    if (!array_key_exists('offset', $_GET)) {
        return 0;
    }
    $raw = trim((string) $_GET['offset']);
    if ($raw === '' || !ctype_digit($raw)) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function player_stats_query_week_gw(): ?int
{
    if (!array_key_exists('week_gw', $_GET)) {
        return null;
    }
    $raw = trim((string) $_GET['week_gw']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        player_stats_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function player_stats_db(): PDO
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

function player_stats_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function player_stats_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","player","team","playerresult")'
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

function player_stats_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function player_stats_gameweeks(PDO $pdo, int $leagueId): array
{
    $stmt = $pdo->prepare(
        'SELECT gameweek, `open`
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
        ];
    }
    return $out;
}

function player_stats_pick_current_gw(array $gwRows): int
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

function player_stats_pick_totals_through_gw(array $gwRows): int
{
    $closedRows = array_values(array_filter($gwRows, static function (array $row): bool {
        return (int) $row['open'] === 0;
    }));
    if (!empty($closedRows)) {
        usort($closedRows, static function (array $a, array $b): int {
            return (int) $b['gw'] <=> (int) $a['gw'];
        });
        return (int) $closedRows[0]['gw'];
    }
    return player_stats_pick_current_gw($gwRows);
}

function player_stats_aggregate_rows(PDO $pdo, int $leagueId, int $totalsThroughGw, int $weekGw, ?int $teamId, array $schema): array
{
    $teamLogoExpr = ($schema['team.logo'] ?? false) ? 'COALESCE(t.logo, \'\')' : '\'\'';
    $teamFilter = $teamId !== null ? ' AND p.team_id = :team_id' : '';

    $sql = 'SELECT
                p.player_id,
                p.playername,
                p.team_id,
                t.short AS team_short,
                ' . $teamLogoExpr . ' AS team_logo,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.played ELSE 0 END), 0) AS matches_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw AND pr.starter = 1 THEN 1 ELSE 0 END), 0) AS starter_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw AND pr.substituted = 1 THEN 1 ELSE 0 END), 0) AS substituted_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.pins ELSE 0 END), 0) AS pins_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.setpoints ELSE 0 END), 0) AS setpoints_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.matchpoints ELSE 0 END), 0) AS matchpoints_total,
                COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.points ELSE 0 END), 0) AS fantasy_points_total,
                COALESCE(SUM(CASE WHEN pr.gameweek = :week_gw THEN pr.points ELSE 0 END), 0) AS week_fantasy_points,
                COALESCE(SUM(CASE WHEN pr.gameweek = :week_gw THEN pr.pins ELSE 0 END), 0) AS week_pins,
                COALESCE(SUM(CASE WHEN pr.gameweek = :week_gw THEN pr.setpoints ELSE 0 END), 0) AS week_setpoints,
                COALESCE(SUM(CASE WHEN pr.gameweek = :week_gw THEN pr.matchpoints ELSE 0 END), 0) AS week_matchpoints
            FROM player p
            LEFT JOIN team t ON t.team_id = p.team_id AND t.league_id = p.league_id
            LEFT JOIN playerresult pr ON pr.player_id = p.player_id
            WHERE p.league_id = :league_id' . $teamFilter . '
            GROUP BY p.player_id, p.playername, p.team_id, t.short, t.logo
            HAVING COALESCE(SUM(CASE WHEN pr.gameweek <= :totals_gw THEN pr.played ELSE 0 END), 0) > 0
            ORDER BY p.player_id ASC';

    $params = [
        ':league_id' => $leagueId,
        ':totals_gw' => $totalsThroughGw,
        ':week_gw' => $weekGw,
    ];
    if ($teamId !== null) {
        $params[':team_id'] = $teamId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function player_stats_sort_rows(array $rows, string $sort): array
{
    usort($rows, static function (array $a, array $b) use ($sort): int {
        if ($sort === 'avg_points_desc') {
            $avgA = ((float) ($a['matches_total'] ?? 0)) > 0
                ? ((float) $a['fantasy_points_total']) / ((float) $a['matches_total'])
                : 0.0;
            $avgB = ((float) ($b['matches_total'] ?? 0)) > 0
                ? ((float) $b['fantasy_points_total']) / ((float) $b['matches_total'])
                : 0.0;
            $cmp = $avgB <=> $avgA;
        } elseif ($sort === 'last_gw_points_desc') {
            $cmp = ((float) $b['week_fantasy_points']) <=> ((float) $a['week_fantasy_points']);
        } else {
            $cmp = ((float) $b['fantasy_points_total']) <=> ((float) $a['fantasy_points_total']);
        }
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) $a['player_id']) <=> ((int) $b['player_id']);
    });
    return $rows;
}

function player_stats_items(array $rows): array
{
    $items = [];
    foreach ($rows as $row) {
        $matchesTotal = (int) ($row['matches_total'] ?? 0);
        $fantasyPointsTotal = (float) ($row['fantasy_points_total'] ?? 0.0);
        $avg = $matchesTotal > 0 ? ($fantasyPointsTotal / $matchesTotal) : 0.0;

        $items[] = [
            'player_id' => (int) $row['player_id'],
            'name' => (string) ($row['playername'] ?? ''),
            'team' => [
                'team_id' => (int) ($row['team_id'] ?? 0),
                'short' => (string) ($row['team_short'] ?? ''),
                'logo_url' => (string) ($row['team_logo'] ?? ''),
            ],
            'matches_total' => $matchesTotal,
            'starter_total' => (int) ($row['starter_total'] ?? 0),
            'substituted_total' => (int) ($row['substituted_total'] ?? 0),
            'pins_total' => (int) ($row['pins_total'] ?? 0),
            'setpoints_total' => (float) ($row['setpoints_total'] ?? 0.0),
            'matchpoints_total' => (float) ($row['matchpoints_total'] ?? 0.0),
            'fantasy_points_total' => $fantasyPointsTotal,
            'avg_points' => $avg,
            'total_points' => $fantasyPointsTotal,
            'last_gw_points' => (float) ($row['week_fantasy_points'] ?? 0.0),
            'week_fantasy_points' => (float) ($row['week_fantasy_points'] ?? 0.0),
            'week_pins' => (int) ($row['week_pins'] ?? 0),
            'week_setpoints' => (float) ($row['week_setpoints'] ?? 0.0),
            'week_matchpoints' => (float) ($row['week_matchpoints'] ?? 0.0),
        ];
    }
    return $items;
}

function player_stats_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $weekGw,
    ?int $teamId,
    string $sort,
    int $limit,
    int $offset,
    int $totalsThroughGw,
    array $items,
    int $total
): array {
    $timestamps = [];
    if (($schema['playerresult.updated_at'] ?? false)) {
        $stmt = $pdo->prepare(
            'SELECT MAX(pr.updated_at)
             FROM playerresult pr
             INNER JOIN player p ON p.player_id = pr.player_id
             WHERE p.league_id = :league_id'
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

    $itemSig = [];
    foreach ($items as $item) {
        $itemSig[] = (int) $item['player_id']
            . ':' . (string) $item['total_points']
            . ':' . (string) $item['avg_points']
            . ':' . (string) $item['last_gw_points'];
    }

    $marker = [
        'pstats-v1',
        'l:' . $leagueId,
        'tw:' . $totalsThroughGw,
        'w:' . $weekGw,
        'team:' . ($teamId ?? 0),
        'sort:' . $sort,
        'p:' . $limit . ':' . $offset,
        'total:' . $total,
        'items:' . sha1(implode('|', $itemSig)),
    ];

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    return [
        'etag' => 'W/"pstats-l' . $leagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\\TH:i:s\\Z', $lastUpdatedTs),
    ];
}

function player_stats_if_none_match_matches(string $etag): bool
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

function player_stats_authorization_header(): string
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

function player_stats_require_auth_profile_id(): int
{
    $header = player_stats_authorization_header();
    if ($header === '') {
        player_stats_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = player_stats_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function player_stats_verify_jwt(string $token): array
{
    $secret = player_stats_jwt_secret();
    if ($secret === '') {
        player_stats_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) player_stats_b64url_decode($h64), true);
    $payload = json_decode((string) player_stats_b64url_decode($p64), true);
    $sig = player_stats_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        player_stats_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function player_stats_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function player_stats_jwt_secret(): string
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
