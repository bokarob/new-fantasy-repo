<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        rules_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = rules_resolve_league_id();
    $pdo = rules_db();
    rules_require_auth_profile_id();
    $schema = rules_schema_info($pdo);

    if (!rules_league_exists($pdo, $leagueId)) {
        rules_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = rules_current_gameweek($pdo, $leagueId, $schema);
    if ($gw === null) {
        rules_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $freeTransferGw = rules_free_transfer_gw($pdo, $leagueId, $schema);
    $isFreeGw = $freeTransferGw !== null && $freeTransferGw === $currentGw;
    $isLocked = rules_season_is_locked($pdo, $leagueId, $schema);

    $etagBuild = rules_etag_and_last_updated(
        $pdo,
        $schema,
        $leagueId,
        $currentGw,
        $freeTransferGw,
        $isLocked,
        $gw
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (rules_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $response = [
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'league_id' => $leagueId,
            'season' => [
                'is_locked' => $isLocked,
            ],
            'rules' => [
                'roster_size' => 8,
                'starters' => 6,
                'subs' => 2,
                'max_from_same_team' => 2,
                'transfers_allowed_per_gw' => 2,
                'free_transfer_gw' => $freeTransferGw,
                'is_free_gw' => $isFreeGw,
                'initial_budget' => 80.0,
            ],
            'links' => [
                'full_rules_url' => '',
            ],
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    rules_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function rules_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/rules/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        rules_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function rules_db(): PDO
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

function rules_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function rules_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks")'
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

function rules_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function rules_current_gameweek(PDO $pdo, int $leagueId, array $schema): ?array
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
        'deadline' => (string) $row['deadline'],
        'open' => (int) $row['open'],
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function rules_free_transfer_gw(PDO $pdo, int $leagueId, array $schema): ?int
{
    if (!($schema['leagues.free_transfer_gw'] ?? false)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT free_transfer_gw FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    $value = $stmt->fetchColumn();
    if ($value === false || $value === null) {
        return null;
    }
    return (int) $value;
}

function rules_season_is_locked(PDO $pdo, int $leagueId, array $schema): bool
{
    $candidates = ['season_locked', 'is_locked', 'locked', 'league_locked'];
    foreach ($candidates as $column) {
        if (!($schema['leagues.' . $column] ?? false)) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT `' . $column . '` FROM leagues WHERE league_id = :league_id LIMIT 1');
        $stmt->execute([':league_id' => $leagueId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null && (int) $value === 1;
    }
    return false;
}

function rules_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $currentGw,
    ?int $freeTransferGw,
    bool $isLocked,
    array $gw
): array {
    $marker = [
        'rules-v1',
        'gw:' . $currentGw,
        'free:' . ($freeTransferGw === null ? 'null' : (string) $freeTransferGw),
        'locked:' . ($isLocked ? '1' : '0'),
    ];

    $timestamps = [];
    if (($schema['gameweeks.updated_at'] ?? false) && !empty($gw['updated_at'])) {
        $ts = strtotime((string) $gw['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
            $marker[] = 'gwu:' . (string) $gw['updated_at'];
        }
    }
    if ($schema['leagues.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT updated_at FROM leagues WHERE league_id = :league_id LIMIT 1');
        $stmt->execute([':league_id' => $leagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $marker[] = 'lu:' . (string) $value;
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    return [
        'etag' => 'W/"rules-l' . $leagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function rules_if_none_match_matches(string $etag): bool
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

function rules_authorization_header(): string
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

function rules_require_auth_profile_id(): int
{
    $header = rules_authorization_header();
    if ($header === '') {
        rules_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = rules_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function rules_verify_jwt(string $token): array
{
    $secret = rules_jwt_secret();
    if ($secret === '') {
        rules_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) rules_b64url_decode($h64), true);
    $payload = json_decode((string) rules_b64url_decode($p64), true);
    $sig = rules_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        rules_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function rules_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function rules_jwt_secret(): string
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
