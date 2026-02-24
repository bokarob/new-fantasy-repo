<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'GET') {
        builder_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = builder_resolve_league_id();
    $pdo = builder_db();
    $profileId = builder_require_auth_profile_id();
    $schema = builder_schema_info($pdo);

    if (!builder_league_exists($pdo, $leagueId)) {
        builder_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = builder_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        builder_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    $existingCompetitor = builder_competitor_exists($pdo, $profileId, $leagueId);
    if ($existingCompetitor) {
        builder_error(409, 'TEAM_ALREADY_EXISTS', 'Team already exists for this user in this league.');
    }

    if (!$gw['is_open']) {
        builder_error(409, 'TEAM_CREATION_NOT_ALLOWED', 'Team creation is not allowed for this gameweek.');
    }

    $players = builder_players_payload($pdo, $leagueId, (int) $gw['gw']);
    $etagBuild = builder_etag_and_last_updated($pdo, $schema, $profileId, $leagueId, $gw, $players);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (builder_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $response = [
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => (int) $gw['gw'],
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'rules' => [
                'roster_size' => 8,
                'starters' => 6,
                'subs' => 2,
                'budget' => 80.0,
                'max_from_same_team' => 2,
            ],
            'players' => $players,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    builder_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function builder_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/team/builder/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        builder_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function builder_db(): PDO
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

function builder_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function builder_schema_info(PDO $pdo): array
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
           AND table_name IN ("gameweeks","playertrade","playerresult")'
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

function builder_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function builder_current_gameweek(PDO $pdo, int $leagueId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT gameweek, deadline, `open`
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

    $deadlineRaw = (string) $row['deadline'];
    $deadlineTs = strtotime($deadlineRaw . ' 23:59:59 UTC');
    $isOpen = ((int) $row['open'] === 1) && $deadlineTs !== false && time() <= $deadlineTs;

    return [
        'gw' => (int) $row['gameweek'],
        'deadline' => $deadlineRaw,
        'open_state' => (int) $row['open'],
        'is_open' => $isOpen,
    ];
}

function builder_competitor_exists(PDO $pdo, int $profileId, int $leagueId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM competitor
         WHERE profile_id = :profile_id AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    return (bool) $stmt->fetchColumn();
}

function builder_players_payload(PDO $pdo, int $leagueId, int $gw): array
{
    $sql = 'SELECT p.player_id,
                   p.playername,
                   p.team_id,
                   COALESCE(t.short, "") AS team_short,
                   COALESCE(t.logo, "") AS team_logo,
                   COALESCE(prc.price, 0) AS price,
                   COALESCE(avgp.avg_points, 0) AS avg_points
            FROM player p
            LEFT JOIN team t
              ON t.team_id = p.team_id
             AND t.league_id = p.league_id
            LEFT JOIN (
                SELECT pt.player_id, pt.price
                FROM playertrade pt
                INNER JOIN (
                    SELECT pt2.player_id, MAX(pt2.gameweek) AS max_gw
                    FROM playertrade pt2
                    INNER JOIN player p2 ON p2.player_id = pt2.player_id
                    WHERE p2.league_id = :league_for_price
                      AND pt2.gameweek <= :gw_for_price
                    GROUP BY pt2.player_id
                ) pick
                  ON pick.player_id = pt.player_id
                 AND pick.max_gw = pt.gameweek
            ) prc
              ON prc.player_id = p.player_id
            LEFT JOIN (
                SELECT sums.player_id, AVG(sums.gw_points) AS avg_points
                FROM (
                    SELECT pr.player_id, pr.gameweek, SUM(pr.points) AS gw_points
                    FROM playerresult pr
                    INNER JOIN player p3 ON p3.player_id = pr.player_id
                    WHERE p3.league_id = :league_for_stats
                      AND pr.gameweek <= :gw_for_stats
                    GROUP BY pr.player_id, pr.gameweek
                ) sums
                GROUP BY sums.player_id
            ) avgp
              ON avgp.player_id = p.player_id
            WHERE p.league_id = :league_id
            ORDER BY p.player_id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':league_for_price' => $leagueId,
        ':gw_for_price' => $gw,
        ':league_for_stats' => $leagueId,
        ':gw_for_stats' => $gw,
        ':league_id' => $leagueId,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $players = [];
    foreach ($rows as $row) {
        $players[] = [
            'player_id' => (int) $row['player_id'],
            'name' => (string) $row['playername'],
            'team' => [
                'team_id' => (int) $row['team_id'],
                'short' => (string) $row['team_short'],
                'logo_url' => builder_logo_url((string) $row['team_logo']),
            ],
            'price' => (float) $row['price'],
            'stats' => [
                'avg_points' => round((float) $row['avg_points'], 1),
            ],
        ];
    }
    return $players;
}

function builder_logo_url(string $raw): string
{
    $logo = trim($raw);
    if ($logo === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $logo)) {
        return $logo;
    }
    if (strpos($logo, '/') !== false) {
        return $logo;
    }
    return '';
}

function builder_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    array $gw,
    array $players
): array {
    $markerPlayers = [];
    foreach ($players as $player) {
        $markerPlayers[] = implode(':', [
            (int) $player['player_id'],
            (string) $player['name'],
            (int) $player['team']['team_id'],
            (string) $player['team']['short'],
            (string) $player['team']['logo_url'],
            (string) $player['price'],
            (string) $player['stats']['avg_points'],
        ]);
    }
    $playersMarker = sha1(implode('|', $markerPlayers));

    $parts = [
        'builder-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'gw:' . (int) $gw['gw'],
        'open:' . (int) $gw['open_state'],
        'deadline:' . (string) $gw['deadline'],
        'team_exists:0',
        'players:' . $playersMarker,
        'players_count:' . count($players),
    ];

    $timestamps = [];
    $gwTs = strtotime((string) $gw['deadline'] . ' 23:59:59 UTC');
    if ($gwTs !== false) {
        $timestamps[] = $gwTs;
    }

    if ($schema['gameweeks.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) FROM gameweeks WHERE league_id = :league_id AND gameweek = :gw'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':gw' => (int) $gw['gw'],
        ]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if ($schema['playertrade.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(pt.updated_at)
             FROM playertrade pt
             INNER JOIN player p ON p.player_id = pt.player_id
             WHERE p.league_id = :league_id AND pt.gameweek <= :gw'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':gw' => (int) $gw['gw'],
        ]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if ($schema['playerresult.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(pr.updated_at)
             FROM playerresult pr
             INNER JOIN player p ON p.player_id = pr.player_id
             WHERE p.league_id = :league_id AND pr.gameweek <= :gw'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':gw' => (int) $gw['gw'],
        ]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    $etag = 'W/"builder-u' . $profileId . '-l' . $leagueId . '-' . (int) $gw['gw'] . '-' . sha1(implode('|', $parts)) . '"';

    return [
        'etag' => $etag,
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function builder_if_none_match_matches(string $etag): bool
{
    $header = '';
    foreach (['HTTP_IF_NONE_MATCH', 'REDIRECT_HTTP_IF_NONE_MATCH', 'If-None-Match'] as $key) {
        if (!empty($_SERVER[$key])) {
            $header = trim((string) $_SERVER[$key]);
            break;
        }
    }
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'if-none-match') {
                $header = trim((string) $value);
                break;
            }
        }
    }
    if ($header === '') {
        return false;
    }
    if (trim($header) === '*') {
        return true;
    }

    $etagRaw = trim($etag);
    $etagWeak = preg_replace('/^W\//', '', $etagRaw) ?? $etagRaw;
    $etagNorm = trim($etagWeak, "\"' \t\r\n");

    $parts = array_map('trim', explode(',', $header));
    foreach ($parts as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $candidateRaw = str_replace('\\"', '"', $candidate);
        $candidateWeak = preg_replace('/^W\//', '', $candidateRaw) ?? $candidateRaw;
        $candidateNorm = trim($candidateWeak, "\"' \t\r\n");
        if ($candidateRaw === $etagRaw || $candidateWeak === $etagWeak || $candidateNorm === $etagNorm) {
            return true;
        }
    }
    return false;
}

function builder_authorization_header(): string
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

function builder_require_auth_profile_id(): int
{
    $header = builder_authorization_header();
    if ($header === '') {
        builder_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = builder_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function builder_verify_jwt(string $token): array
{
    $secret = builder_jwt_secret();
    if ($secret === '') {
        builder_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) builder_b64url_decode($h64), true);
    $payload = json_decode((string) builder_b64url_decode($p64), true);
    $sig = builder_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        builder_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function builder_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function builder_jwt_secret(): string
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
