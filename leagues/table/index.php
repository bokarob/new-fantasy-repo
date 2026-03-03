<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        table_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = table_resolve_league_id();
    $gwParam = table_resolve_gw_param();

    $pdo = table_db();
    table_require_auth_profile_id();
    $schema = table_schema_info($pdo);

    if (!table_league_exists($pdo, $leagueId)) {
        table_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gwRows = table_gameweeks($pdo, $leagueId);
    if (empty($gwRows)) {
        table_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $currentGw = table_pick_current_gw($gwRows);
    $selectedGw = $gwParam ?? $currentGw;

    if (!in_array($selectedGw, array_column($gwRows, 'gw'), true)) {
        table_error(404, 'GAMEWEEK_NOT_FOUND', 'Gameweek not found.');
    }

    $tableRows = table_snapshot_rows($pdo, $leagueId, $selectedGw, $schema);
    if (empty($tableRows)) {
        table_error(409, 'TABLE_NOT_AVAILABLE', 'Table is not available.');
    }

    $etagBuild = table_etag_and_last_updated($tableRows, $leagueId, $selectedGw, $schema);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (table_if_none_match_matches($etagBuild['etag'])) {
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
            'table' => $tableRows,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    table_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function table_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/table/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        table_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function table_resolve_gw_param(): ?int
{
    if (!array_key_exists('gw', $_GET)) {
        return null;
    }

    $raw = trim((string) $_GET['gw']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        table_error(400, 'BAD_REQUEST', 'Invalid gw.');
    }
    return (int) $raw;
}

function table_db(): PDO
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

function table_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function table_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","leaguetable","team")'
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

function table_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function table_gameweeks(PDO $pdo, int $leagueId): array
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

function table_pick_current_gw(array $gwRows): int
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

function table_snapshot_rows(PDO $pdo, int $leagueId, int $gw, array $schema): array
{
    $updatedExpr = ($schema['leaguetable.updated_at'] ?? false) ? 'lt.updated_at AS updated_at' : 'NULL AS updated_at';
    $teamLogoExpr = ($schema['team.logo'] ?? false) ? 'COALESCE(t.logo, \'\')' : '\'\'';

    $stmt = $pdo->prepare(
        'SELECT
            lt.team_id,
            lt.win,
            lt.draw,
            lt.loss,
            lt.team_points,
            lt.match_points,
            lt.set_points,
            ' . $updatedExpr . ',
            t.short AS team_short,
            t.name AS team_name,
            ' . $teamLogoExpr . ' AS team_logo
         FROM leaguetable lt
         LEFT JOIN team t ON t.team_id = lt.team_id AND t.league_id = lt.league_id
         WHERE lt.league_id = :league_id
           AND lt.gameweek = :gw
         ORDER BY lt.team_points DESC, lt.match_points DESC, lt.set_points DESC, lt.team_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $idx => $row) {
        $win = (int) ($row['win'] ?? 0);
        $draw = (int) ($row['draw'] ?? 0);
        $loss = (int) ($row['loss'] ?? 0);
        $items[] = [
            'rank' => $idx + 1,
            'team' => [
                'team_id' => (int) $row['team_id'],
                'short' => (string) ($row['team_short'] ?? ''),
                'name' => (string) ($row['team_name'] ?? ''),
                'logo_url' => (string) ($row['team_logo'] ?? ''),
            ],
            'played' => $win + $draw + $loss,
            'win' => $win,
            'draw' => $draw,
            'loss' => $loss,
            'team_points' => (int) ($row['team_points'] ?? 0),
            'match_points' => (int) ($row['match_points'] ?? 0),
            'set_points' => (int) ($row['set_points'] ?? 0),
            '_updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $items;
}

function table_etag_and_last_updated(array $tableRows, int $leagueId, int $gw, array $schema): array
{
    $count = count($tableRows);
    $sumTeam = 0;
    $sumMatch = 0;
    $sumSet = 0;
    $maxUpdatedTs = null;
    $rowSig = [];

    foreach ($tableRows as $row) {
        $sumTeam += (int) $row['team_points'];
        $sumMatch += (int) $row['match_points'];
        $sumSet += (int) $row['set_points'];
        $rowSig[] = (int) $row['team']['team_id']
            . ':' . (int) $row['win']
            . ':' . (int) $row['draw']
            . ':' . (int) $row['loss']
            . ':' . (int) $row['team_points']
            . ':' . (int) $row['match_points']
            . ':' . (int) $row['set_points'];

        if (($schema['leaguetable.updated_at'] ?? false) && !empty($row['_updated_at'])) {
            $ts = strtotime((string) $row['_updated_at']);
            if ($ts !== false) {
                $maxUpdatedTs = $maxUpdatedTs === null ? $ts : max($maxUpdatedTs, $ts);
            }
        }
    }

    $marker = [
        'table-v1',
        'l:' . $leagueId,
        'gw:' . $gw,
        'cnt:' . $count,
        'sumtp:' . $sumTeam,
        'summp:' . $sumMatch,
        'sumsp:' . $sumSet,
        'rows:' . sha1(implode('|', $rowSig)),
    ];
    if ($maxUpdatedTs !== null) {
        $marker[] = 'u:' . $maxUpdatedTs;
    }

    $lastUpdatedTs = $maxUpdatedTs ?? time();
    return [
        'etag' => 'W/"table-l' . $leagueId . '-gw' . $gw . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\\TH:i:s\\Z', $lastUpdatedTs),
    ];
}

function table_if_none_match_matches(string $etag): bool
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

function table_authorization_header(): string
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

function table_require_auth_profile_id(): int
{
    $header = table_authorization_header();
    if ($header === '') {
        table_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = table_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function table_verify_jwt(string $token): array
{
    $secret = table_jwt_secret();
    if ($secret === '') {
        table_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) table_b64url_decode($h64), true);
    $payload = json_decode((string) table_b64url_decode($p64), true);
    $sig = table_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        table_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function table_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function table_jwt_secret(): string
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
