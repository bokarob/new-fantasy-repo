<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        substitute_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = substitute_resolve_league_id();
    $input = substitute_json_input();
    [$posA, $posB] = substitute_swap_positions($input);

    $pdo = substitute_db();
    $profileId = substitute_require_auth_profile_id();

    if (!substitute_league_exists($pdo, $leagueId)) {
        substitute_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = substitute_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        substitute_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    if ((int) $gw['open_state'] !== 1) {
        substitute_error(409, 'GW_NOT_OPEN', 'Gameweek is not open.');
    }
    if (!(bool) $gw['before_deadline']) {
        substitute_error(409, 'GW_CLOSED', 'Substitution is not allowed after deadline.');
    }

    $competitor = substitute_competitor_for_update($pdo, $profileId, $leagueId);
    if ($competitor === null) {
        substitute_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    try {
        $pdo->beginTransaction();

        $roster = substitute_roster_with_autocreate_for_update($pdo, (int) $competitor['competitor_id'], (int) $gw['gw']);
        if ($roster === null) {
            substitute_error(404, 'ROSTER_NOT_FOUND', 'Roster not found.');
        }

        $updated = [
            (int) $roster['player1'],
            (int) $roster['player2'],
            (int) $roster['player3'],
            (int) $roster['player4'],
            (int) $roster['player5'],
            (int) $roster['player6'],
            (int) $roster['player7'],
            (int) $roster['player8'],
        ];

        $aIdx = $posA - 1;
        $bIdx = $posB - 1;
        $tmp = $updated[$aIdx];
        $updated[$aIdx] = $updated[$bIdx];
        $updated[$bIdx] = $tmp;

        $captain = (int) $roster['captain'];
        $captainPos = array_search($captain, $updated, true);
        if ($captainPos === false || (int) $captainPos >= 6) {
            $captain = (int) $updated[0];
        }

        $update = $pdo->prepare(
            'UPDATE roster
             SET player1=:p1, player2=:p2, player3=:p3, player4=:p4, player5=:p5, player6=:p6, player7=:p7, player8=:p8, captain=:captain
             WHERE competitor_id=:competitor_id AND gameweek=:gw'
        );
        $update->execute([
            ':p1' => $updated[0],
            ':p2' => $updated[1],
            ':p3' => $updated[2],
            ':p4' => $updated[3],
            ':p5' => $updated[4],
            ':p6' => $updated[5],
            ':p7' => $updated[6],
            ':p8' => $updated[7],
            ':captain' => $captain,
            ':competitor_id' => (int) $competitor['competitor_id'],
            ':gw' => (int) $gw['gw'],
        ]);

        $pdo->commit();
    } catch (Throwable $txError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        substitute_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }

    $now = gmdate('Y-m-d\TH:i:s\Z');
    echo json_encode([
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
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    substitute_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function substitute_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        substitute_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        substitute_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function substitute_swap_positions(array $input): array
{
    $hasSwapA = array_key_exists('swap_pos_a', $input);
    $hasSwapB = array_key_exists('swap_pos_b', $input);

    if ($hasSwapA || $hasSwapB) {
        $posA = substitute_required_int_field($input, 'swap_pos_a');
        $posB = substitute_required_int_field($input, 'swap_pos_b');
    } else {
        $posA = substitute_required_int_field($input, 'pos_a');
        $posB = substitute_required_int_field($input, 'pos_b');
    }

    if ($posA < 1 || $posA > 8 || $posB < 1 || $posB > 8 || $posA === $posB) {
        substitute_error(422, 'ROSTER_INVALID_POSITION', 'Invalid swap positions.');
    }

    return [$posA, $posB];
}

function substitute_required_int_field(array $input, string $field): int
{
    if (!array_key_exists($field, $input)) {
        substitute_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $value = $input[$field];
    if (is_int($value)) {
        $intValue = $value;
    } elseif (is_string($value) && ctype_digit($value)) {
        $intValue = (int) $value;
    } else {
        substitute_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $intValue;
}

function substitute_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/team/substitute/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }
    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        substitute_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function substitute_db(): PDO
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

function substitute_error(int $status, string $code, string $message): void
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

function substitute_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function substitute_current_gameweek(PDO $pdo, int $leagueId): ?array
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

function substitute_competitor_for_update(PDO $pdo, int $profileId, int $leagueId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id
         FROM competitor
         WHERE profile_id = :profile_id AND league_id = :league_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function substitute_roster_with_autocreate_for_update(PDO $pdo, int $competitorId, int $gw): ?array
{
    $current = $pdo->prepare(
        'SELECT competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain
         FROM roster
         WHERE competitor_id = :competitor_id AND gameweek = :gw
         LIMIT 1
         FOR UPDATE'
    );
    $current->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    $row = $current->fetch();
    if ($row) {
        return $row;
    }

    $prev = $pdo->prepare(
        'SELECT player1, player2, player3, player4, player5, player6, player7, player8, captain
         FROM roster
         WHERE competitor_id = :competitor_id
         ORDER BY gameweek DESC
         LIMIT 1
         FOR UPDATE'
    );
    $prev->execute([':competitor_id' => $competitorId]);
    $src = $prev->fetch();
    if (!$src) {
        return null;
    }

    $insert = $pdo->prepare(
        'INSERT INTO roster (competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain)
         VALUES (:competitor_id, :gw, :player1, :player2, :player3, :player4, :player5, :player6, :player7, :player8, :captain)'
    );
    $insert->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
        ':player1' => (int) $src['player1'],
        ':player2' => (int) $src['player2'],
        ':player3' => (int) $src['player3'],
        ':player4' => (int) $src['player4'],
        ':player5' => (int) $src['player5'],
        ':player6' => (int) $src['player6'],
        ':player7' => (int) $src['player7'],
        ':player8' => (int) $src['player8'],
        ':captain' => (int) $src['captain'],
    ]);

    $current->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    $row = $current->fetch();
    return $row ?: null;
}

function substitute_authorization_header(): string
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

function substitute_require_auth_profile_id(): int
{
    $header = substitute_authorization_header();
    if ($header === '') {
        substitute_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = substitute_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function substitute_verify_jwt(string $token): array
{
    $secret = substitute_jwt_secret();
    if ($secret === '') {
        substitute_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) substitute_b64url_decode($h64), true);
    $payload = json_decode((string) substitute_b64url_decode($p64), true);
    $sig = substitute_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        substitute_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function substitute_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function substitute_jwt_secret(): string
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
