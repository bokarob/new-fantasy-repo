<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        me_teams_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $pdo = me_teams_db();
    $profileId = me_teams_require_auth_profile_id();
    $schema = me_teams_schema_info($pdo);

    $teams = me_teams_rows($pdo, $profileId, $schema);
    $etagBuild = me_teams_etag_and_last_updated($teams, $profileId, $schema);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    $ifNoneMatch = me_teams_if_none_match_raw();
    if (me_teams_if_none_match_matches($etagBuild['etag'], $ifNoneMatch)) {
        http_response_code(304);
        exit;
    }

    $items = [];
    foreach ($teams as $row) {
        $items[] = [
            'league' => [
                'league_id' => (int) $row['league_id'],
                'name' => (string) ($row['league_name'] ?? ''),
                'logo_url' => (string) ($row['league_logo'] ?? ''),
            ],
            'competitor' => [
                'competitor_id' => (int) $row['competitor_id'],
                'teamname' => (string) $row['teamname'],
                'created_at' => me_teams_datetime_iso((string) ($row['created_at'] ?? '')),
            ],
        ];
    }

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'teams' => $items,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    me_teams_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function me_teams_db(): PDO
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

function me_teams_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function me_teams_schema_info(PDO $pdo): array
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
           AND table_name IN ("competitor","leagues")'
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

function me_teams_rows(PDO $pdo, int $profileId, array $schema): array
{
    $createdExpr = ($schema['competitor.created_at'] ?? false) ? 'c.created_at' : 'NULL AS created_at';
    $updatedExpr = ($schema['competitor.updated_at'] ?? false) ? 'c.updated_at' : 'NULL AS updated_at';
    $leagueNameExpr = ($schema['leagues.name'] ?? false)
        ? 'l.name AS league_name'
        : 'l.`league name` AS league_name';
    $leagueLogoExpr = ($schema['leagues.logo'] ?? false)
        ? 'COALESCE(l.logo, \'\') AS league_logo'
        : '\'\' AS league_logo';

    $stmt = $pdo->prepare(
        'SELECT
            c.competitor_id,
            c.league_id,
            c.teamname,
            ' . $createdExpr . ',
            ' . $updatedExpr . ',
            ' . $leagueNameExpr . ',
            ' . $leagueLogoExpr . '
         FROM competitor c
         INNER JOIN leagues l ON l.league_id = c.league_id
         WHERE c.profile_id = :profile_id
         ORDER BY c.league_id ASC, c.competitor_id ASC'
    );
    $stmt->execute([':profile_id' => $profileId]);
    return $stmt->fetchAll() ?: [];
}

function me_teams_etag_and_last_updated(array $teams, int $profileId, array $schema): array
{
    $count = count($teams);
    $maxUpdatedTs = null;

    if ($schema['competitor.updated_at'] ?? false) {
        foreach ($teams as $row) {
            if (!empty($row['updated_at'])) {
                $ts = strtotime((string) $row['updated_at']);
                if ($ts !== false) {
                    $maxUpdatedTs = $maxUpdatedTs === null ? $ts : max($maxUpdatedTs, $ts);
                }
            }
        }
    }

    if ($maxUpdatedTs !== null) {
        $marker = 'u' . $maxUpdatedTs . '-c' . $count;
        $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $maxUpdatedTs);
    } else {
        $parts = [];
        $maxCreatedTs = null;
        foreach ($teams as $row) {
            $parts[] = (int) $row['competitor_id'] . ':' . (int) $row['league_id'] . ':' . (string) $row['teamname'];
            if (!empty($row['created_at'])) {
                $ts = strtotime((string) $row['created_at']);
                if ($ts !== false) {
                    $maxCreatedTs = $maxCreatedTs === null ? $ts : max($maxCreatedTs, $ts);
                }
            }
        }
        $marker = sha1(implode('|', $parts) . '|c:' . $count);
        $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $maxCreatedTs ?? time());
    }

    return [
        'etag' => 'W/"me-teams-u' . $profileId . '-' . $marker . '"',
        'last_updated' => $lastUpdated,
    ];
}

function me_teams_if_none_match_raw(): string
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

function me_teams_if_none_match_matches(string $etag, string $ifNoneMatchRaw): bool
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

function me_teams_authorization_header(): string
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

function me_teams_require_auth_profile_id(): int
{
    $header = me_teams_authorization_header();
    if ($header === '') {
        me_teams_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = me_teams_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function me_teams_verify_jwt(string $token): array
{
    $secret = me_teams_jwt_secret();
    if ($secret === '') {
        me_teams_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) me_teams_b64url_decode($h64), true);
    $payload = json_decode((string) me_teams_b64url_decode($p64), true);
    $sig = me_teams_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        me_teams_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function me_teams_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function me_teams_jwt_secret(): string
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

function me_teams_datetime_iso(string $value): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
