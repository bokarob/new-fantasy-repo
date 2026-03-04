<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        pl_rename_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_rename_resolve_league_id();
    $privateleagueId = pl_rename_resolve_privateleague_id();

    $pdo = pl_rename_db();
    $profileId = pl_rename_require_auth_profile_id();
    $schema = pl_rename_schema_info($pdo);
    $currentGw = pl_rename_current_gw($pdo, $leagueId);

    $privateleague = pl_rename_privateleague($pdo, $schema, $leagueId, $privateleagueId);
    if ($privateleague === null) {
        pl_rename_error(404, 'PRIVATE_LEAGUE_NOT_FOUND', 'Private league not found.');
    }

    $adminProfileId = (int) ($privateleague['admin_profile_id'] ?? 0);
    if ($profileId !== $adminProfileId) {
        pl_rename_error(403, 'NOT_ADMIN', 'Admin privileges required for action.');
    }

    $input = pl_rename_json_input_required();
    $leaguename = pl_rename_validated_leaguename($input);

    $callerCompetitorId = pl_rename_competitor_id_by_profile($pdo, $profileId, $leagueId);

    try {
        $pdo->beginTransaction();
        pl_rename_update_privateleague_name($pdo, $leagueId, $privateleagueId, $leaguename);
        pl_rename_touch_membership($pdo, $schema, $privateleagueId, $callerCompetitorId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
    echo json_encode([
        'meta' => [
            'server_time' => $serverTime,
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $serverTime,
            'etag' => null,
        ],
        'data' => [
            'ok' => true,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    pl_rename_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_rename_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/\d+/rename/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_rename_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_rename_resolve_privateleague_id(): int
{
    $raw = isset($_GET['privateleague_id']) ? (string) $_GET['privateleague_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/(\d+)/rename/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_rename_error(400, 'BAD_REQUEST', 'Invalid privateleague_id.');
    }
    return (int) $raw;
}

function pl_rename_db(): PDO
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

function pl_rename_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_rename_json_input_required(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        pl_rename_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        pl_rename_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function pl_rename_validated_leaguename(array $input): string
{
    if (!array_key_exists('leaguename', $input) || !is_string($input['leaguename'])) {
        pl_rename_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $name = trim((string) $input['leaguename']);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($name === '' || $length < 3 || $length > 30) {
        pl_rename_error(422, 'VALIDATION_ERROR', 'Invalid private league name.');
    }

    if (!preg_match("/^[\\p{L}\\p{N} ._\\-'&]+$/u", $name)) {
        pl_rename_error(422, 'VALIDATION_ERROR', 'Invalid private league name.');
    }

    return $name;
}

function pl_rename_schema_info(PDO $pdo): array
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
           AND table_name IN ("gameweeks","privateleague","privateleaguemembers","competitor")'
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

function pl_rename_current_gw(PDO $pdo, int $leagueId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT gameweek
         FROM gameweeks
         WHERE league_id = :league_id
         ORDER BY (`open` = 1) DESC, gameweek DESC
         LIMIT 1'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $gw = $stmt->fetchColumn();
    if ($gw === false || $gw === null) {
        return null;
    }
    return (int) $gw;
}

function pl_rename_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_rename_privateleague(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): ?array
{
    $adminColumn = pl_rename_privateleague_admin_column($schema);
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

function pl_rename_competitor_id_by_profile(PDO $pdo, int $profileId, int $leagueId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id
         FROM competitor
         WHERE profile_id = :profile_id
           AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $value = $stmt->fetchColumn();
    if ($value === false || $value === null) {
        return null;
    }
    return (int) $value;
}

function pl_rename_update_privateleague_name(PDO $pdo, int $leagueId, int $privateleagueId, string $leaguename): void
{
    $stmt = $pdo->prepare(
        'UPDATE privateleague
         SET leaguename = :leaguename
         WHERE league_id = :league_id
           AND privateleague_id = :privateleague_id
         LIMIT 1'
    );
    $stmt->execute([
        ':leaguename' => $leaguename,
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
    ]);
}

function pl_rename_touch_membership(PDO $pdo, array $schema, int $privateleagueId, ?int $competitorId): void
{
    if (!($schema['privateleaguemembers.updated_at'] ?? false) || $competitorId === null || $competitorId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE privateleaguemembers
         SET updated_at = UTC_TIMESTAMP()
         WHERE privateleague_id = :privateleague_id
           AND competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute([
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ]);
}

function pl_rename_authorization_header(): string
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

function pl_rename_require_auth_profile_id(): int
{
    $header = pl_rename_authorization_header();
    if ($header === '') {
        pl_rename_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_rename_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_rename_verify_jwt(string $token): array
{
    $secret = pl_rename_jwt_secret();
    if ($secret === '') {
        pl_rename_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_rename_b64url_decode($h64), true);
    $payload = json_decode((string) pl_rename_b64url_decode($p64), true);
    $sig = pl_rename_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_rename_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_rename_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_rename_jwt_secret(): string
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
