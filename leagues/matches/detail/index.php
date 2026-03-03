<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        matches_detail_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = matches_detail_resolve_league_id();
    $matchId = matches_detail_resolve_match_id();

    $pdo = matches_detail_db();
    matches_detail_require_auth_profile_id();
    $schema = matches_detail_schema_info($pdo);

    if (!matches_detail_league_exists($pdo, $leagueId)) {
        matches_detail_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $match = matches_detail_match($pdo, $leagueId, $matchId, $schema);
    if ($match === null) {
        matches_detail_error(404, 'MATCH_NOT_FOUND', 'Match not found.');
    }

    $gwRows = matches_detail_gameweeks($pdo, $leagueId);
    $currentGw = matches_detail_pick_current_gw($gwRows);

    $rows = matches_detail_rows($pdo, $matchId, $schema);
    $etagBuild = matches_detail_etag_and_last_updated($match, $schema);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (matches_detail_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $status = matches_detail_map_status(
        $match['raw_status'] ?? null,
        (float) ($match['homepoint'] ?? 0.0),
        (float) ($match['awaypoint'] ?? 0.0)
    );

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
            'gw' => (int) $match['gameweek'],
            'match' => [
                'match_id' => (int) $match['match_id'],
                'kickoff_at' => matches_detail_kickoff_iso($match['kickoff_at'] ?? null),
                'status' => $status,
                'is_postponed' => $status === 'postponed',
                'home' => [
                    'team_id' => (int) ($match['home_team_id'] ?? $match['hometeam'] ?? 0),
                    'short' => (string) ($match['home_short'] ?? ''),
                    'name' => (string) ($match['home_name'] ?? ''),
                    'logo_url' => (string) ($match['home_logo'] ?? ''),
                ],
                'away' => [
                    'team_id' => (int) ($match['away_team_id'] ?? $match['awayteam'] ?? 0),
                    'short' => (string) ($match['away_short'] ?? ''),
                    'name' => (string) ($match['away_name'] ?? ''),
                    'logo_url' => (string) ($match['away_logo'] ?? ''),
                ],
                'score' => [
                    'home' => (float) ($match['homepoint'] ?? 0.0),
                    'away' => (float) ($match['awaypoint'] ?? 0.0),
                ],
            ],
            'rows' => $rows,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    matches_detail_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function matches_detail_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\\d+)/matches/(\\d+)/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        matches_detail_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function matches_detail_resolve_match_id(): int
{
    $raw = isset($_GET['match_id']) ? (string) $_GET['match_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\\d+)/matches/(\\d+)/?$#', $path, $m)) {
            $raw = $m[2];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        matches_detail_error(400, 'BAD_REQUEST', 'Invalid match_id.');
    }
    return (int) $raw;
}

function matches_detail_db(): PDO
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

function matches_detail_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function matches_detail_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","matches","team","playerresult","player")'
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

function matches_detail_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function matches_detail_gameweeks(PDO $pdo, int $leagueId): array
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

function matches_detail_pick_current_gw(array $gwRows): ?int
{
    if (empty($gwRows)) {
        return null;
    }

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

function matches_detail_match(PDO $pdo, int $leagueId, int $matchId, array $schema): ?array
{
    $statusExpr = ($schema['matches.status'] ?? false) ? 'm.`status` AS raw_status' : 'NULL AS raw_status';
    $updatedExpr = ($schema['matches.updated_at'] ?? false) ? 'm.updated_at AS updated_at' : 'NULL AS updated_at';
    $kickoffExpr = ($schema['matches.kickoff_at'] ?? false) ? 'm.kickoff_at AS kickoff_at' : 'NULL AS kickoff_at';
    $teamLogoExpr = ($schema['team.logo'] ?? false) ? 'COALESCE(%s.logo, \'\')' : '\'\'';

    $sql = 'SELECT
                m.match_id,
                m.league_id,
                m.gameweek,
                m.hometeam,
                m.awayteam,
                m.homepoint,
                m.awaypoint,
                ' . $statusExpr . ',
                ' . $updatedExpr . ',
                ' . $kickoffExpr . ',
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
              AND m.match_id = :match_id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':league_id' => $leagueId,
        ':match_id' => $matchId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function matches_detail_rows(PDO $pdo, int $matchId, array $schema): array
{
    if (!($schema['playerresult.match_id'] ?? false)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT
            pr.homegame,
            pr.`row`,
            pr.player_id,
            p.playername,
            pr.pins,
            pr.setpoints,
            pr.matchpoints,
            pr.points,
            pr.starter,
            pr.substituted
         FROM playerresult pr
         INNER JOIN player p ON p.player_id = pr.player_id
         WHERE pr.match_id = :match_id
         ORDER BY pr.`row` ASC, pr.homegame DESC, pr.player_id ASC'
    );
    $stmt->execute([':match_id' => $matchId]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'side' => ((int) $row['homegame'] === 1) ? 'home' : 'away',
            'row' => (int) $row['row'],
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) ($row['playername'] ?? ''),
            'pins' => (int) ($row['pins'] ?? 0),
            'setpoints' => (float) ($row['setpoints'] ?? 0.0),
            'matchpoints' => (float) ($row['matchpoints'] ?? 0.0),
            'fantasy_points' => (float) ($row['points'] ?? 0.0),
            'was_starter' => ((int) ($row['starter'] ?? 0)) === 1,
            'was_substituted' => ((int) ($row['substituted'] ?? 0)) === 1,
        ];
    }

    return $items;
}

function matches_detail_map_status($rawStatus, float $homePoint, float $awayPoint): string
{
    $allowed = [
        'scheduled' => true,
        'in_progress' => true,
        'finished' => true,
        'postponed' => true,
        'cancelled' => true,
    ];

    if (is_string($rawStatus)) {
        $norm = strtolower(trim($rawStatus));
        if (isset($allowed[$norm])) {
            return $norm;
        }
    }

    if ($homePoint > 0.0 || $awayPoint > 0.0) {
        return 'finished';
    }
    return 'scheduled';
}

function matches_detail_etag_and_last_updated(array $match, array $schema): array
{
    $home = (float) ($match['homepoint'] ?? 0.0);
    $away = (float) ($match['awaypoint'] ?? 0.0);
    $status = matches_detail_map_status($match['raw_status'] ?? null, $home, $away);
    $updated = (string) ($match['updated_at'] ?? '');

    $marker = [
        'match-v1',
        'league:' . (int) ($match['league_id'] ?? 0),
        'match:' . (int) ($match['match_id'] ?? 0),
        'gw:' . (int) ($match['gameweek'] ?? 0),
        'status:' . $status,
        'home:' . (string) $home,
        'away:' . (string) $away,
        'u:' . $updated,
    ];

    $lastUpdatedTs = ($schema['matches.updated_at'] ?? false) ? strtotime($updated) : false;
    if ($lastUpdatedTs === false || $lastUpdatedTs <= 0) {
        $lastUpdatedTs = time();
    }

    $leagueId = (int) ($match['league_id'] ?? 0);
    $matchId = (int) ($match['match_id'] ?? 0);
    return [
        'etag' => 'W/"match-' . $matchId . '-l' . $leagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\\TH:i:s\\Z', $lastUpdatedTs),
    ];
}

function matches_detail_kickoff_iso($raw): ?string
{
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    return gmdate('Y-m-d\\TH:i:s\\Z', $ts);
}

function matches_detail_if_none_match_matches(string $etag): bool
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

function matches_detail_authorization_header(): string
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

function matches_detail_require_auth_profile_id(): int
{
    $header = matches_detail_authorization_header();
    if ($header === '') {
        matches_detail_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = matches_detail_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function matches_detail_verify_jwt(string $token): array
{
    $secret = matches_detail_jwt_secret();
    if ($secret === '') {
        matches_detail_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) matches_detail_b64url_decode($h64), true);
    $payload = json_decode((string) matches_detail_b64url_decode($p64), true);
    $sig = matches_detail_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        matches_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function matches_detail_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function matches_detail_jwt_secret(): string
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
