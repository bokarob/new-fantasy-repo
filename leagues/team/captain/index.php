<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        captain_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = captain_resolve_league_id();
    $input = captain_json_input();
    $captainPlayerId = captain_required_int($input, 'captain_player_id');

    $pdo = captain_db();
    $profileId = captain_require_auth_profile_id();

    if (!captain_league_exists($pdo, $leagueId)) {
        captain_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = captain_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        captain_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    if ((int) $gw['open_state'] !== 1) {
        captain_error(409, 'GW_NOT_OPEN', 'Gameweek is not open.');
    }
    if (!(bool) $gw['before_deadline']) {
        captain_error(409, 'CAPTAIN_CHANGE_NOT_ALLOWED', 'Captain change not allowed after deadline.');
    }

    $competitor = captain_competitor($pdo, $profileId, $leagueId);
    if ($competitor === null) {
        captain_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $roster = captain_roster_with_autocreate($pdo, (int) $competitor['competitor_id'], (int) $gw['gw']);
    if ($roster === null) {
        captain_error(404, 'ROSTER_NOT_FOUND', 'Roster not found.');
    }

    $captainPos = captain_find_position($roster, $captainPlayerId);
    if ($captainPos === null) {
        captain_error(422, 'CAPTAIN_INVALID', 'Captain must be in roster.');
    }
    if ($captainPos >= 7) {
        captain_error(422, 'CAPTAIN_NOT_STARTER', 'Captain must be selected from starters.');
    }

    $update = $pdo->prepare(
        'UPDATE roster
         SET captain = :captain
         WHERE competitor_id = :competitor_id AND gameweek = :gw'
    );
    $update->execute([
        ':captain' => $captainPlayerId,
        ':competitor_id' => (int) $competitor['competitor_id'],
        ':gw' => (int) $gw['gw'],
    ]);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $response = [
        'meta' => [
            'server_time' => $now,
            'league_id' => $leagueId,
            'current_gw' => (int) $gw['gw'],
            'last_updated' => $now,
            'etag' => null,
        ],
        'data' => [
            'ok' => true,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    captain_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function captain_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        captain_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        captain_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function captain_required_int(array $input, string $field): int
{
    if (!array_key_exists($field, $input)) {
        captain_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $value = $input[$field];
    if (is_int($value)) {
        $intValue = $value;
    } elseif (is_string($value) && ctype_digit($value)) {
        $intValue = (int) $value;
    } else {
        captain_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    if ($intValue <= 0) {
        captain_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $intValue;
}

function captain_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/team/captain/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }
    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        captain_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function captain_db(): PDO
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

function captain_error(int $status, string $code, string $message): void
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

function captain_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function captain_current_gameweek(PDO $pdo, int $leagueId): ?array
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

    $deadlineTs = strtotime((string) $row['deadline'] . ' 23:59:59 UTC');
    $beforeDeadline = $deadlineTs !== false && time() <= $deadlineTs;

    return [
        'gw' => (int) $row['gameweek'],
        'open_state' => (int) $row['open'],
        'before_deadline' => $beforeDeadline,
    ];
}

function captain_competitor(PDO $pdo, int $profileId, int $leagueId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id
         FROM competitor
         WHERE profile_id = :profile_id AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function captain_roster_with_autocreate(PDO $pdo, int $competitorId, int $gw): ?array
{
    $fetch = $pdo->prepare(
        'SELECT competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain
         FROM roster
         WHERE competitor_id = :competitor_id AND gameweek = :gw
         LIMIT 1'
    );
    $fetch->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    $row = $fetch->fetch();
    if ($row) {
        return $row;
    }

    $prev = $pdo->prepare(
        'SELECT player1, player2, player3, player4, player5, player6, player7, player8, captain
         FROM roster
         WHERE competitor_id = :competitor_id
         ORDER BY gameweek DESC
         LIMIT 1'
    );
    $prev->execute([':competitor_id' => $competitorId]);
    $source = $prev->fetch();
    if (!$source) {
        return null;
    }

    $insert = $pdo->prepare(
        'INSERT INTO roster (competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain)
         VALUES (:competitor_id, :gw, :player1, :player2, :player3, :player4, :player5, :player6, :player7, :player8, :captain)'
    );
    $insert->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
        ':player1' => (int) $source['player1'],
        ':player2' => (int) $source['player2'],
        ':player3' => (int) $source['player3'],
        ':player4' => (int) $source['player4'],
        ':player5' => (int) $source['player5'],
        ':player6' => (int) $source['player6'],
        ':player7' => (int) $source['player7'],
        ':player8' => (int) $source['player8'],
        ':captain' => (int) $source['captain'],
    ]);

    $fetch->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    $row = $fetch->fetch();
    return $row ?: null;
}

function captain_find_position(array $roster, int $captainPlayerId): ?int
{
    for ($pos = 1; $pos <= 8; $pos++) {
        if ((int) $roster['player' . $pos] === $captainPlayerId) {
            return $pos;
        }
    }
    return null;
}

function captain_authorization_header(): string
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

function captain_require_auth_profile_id(): int
{
    $header = captain_authorization_header();
    if ($header === '') {
        captain_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = captain_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function captain_verify_jwt(string $token): array
{
    $secret = captain_jwt_secret();
    if ($secret === '') {
        captain_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) captain_b64url_decode($h64), true);
    $payload = json_decode((string) captain_b64url_decode($p64), true);
    $sig = captain_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        captain_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function captain_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function captain_jwt_secret(): string
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
