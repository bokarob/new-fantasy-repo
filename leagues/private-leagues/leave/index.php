<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        pl_leave_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_leave_resolve_league_id();
    $privateleagueId = pl_leave_resolve_privateleague_id();

    $pdo = pl_leave_db();
    $profileId = pl_leave_require_auth_profile_id();
    $schema = pl_leave_schema_info($pdo);
    $currentGw = pl_leave_current_gw($pdo, $leagueId);

    $privateleague = pl_leave_privateleague($pdo, $schema, $leagueId, $privateleagueId);
    if ($privateleague === null) {
        pl_leave_error(404, 'PRIVATE_LEAGUE_NOT_FOUND', 'Private league not found.');
    }

    $callerCompetitorId = pl_leave_competitor_id_by_profile($pdo, $profileId, $leagueId);
    if ($callerCompetitorId === null) {
        pl_leave_error(403, 'PRIVATE_LEAGUE_FORBIDDEN', 'Not a member.');
    }

    try {
        $pdo->beginTransaction();

        $membership = pl_leave_membership_for_update(
            $pdo,
            $schema,
            $leagueId,
            $privateleagueId,
            $callerCompetitorId
        );
        if ($membership === null || $membership['status'] !== 'member_confirmed') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            pl_leave_error(403, 'PRIVATE_LEAGUE_FORBIDDEN', 'Not a member.');
        }

        $isCallerAdmin = $profileId === (int) ($privateleague['admin_profile_id'] ?? 0);
        $confirmedMembers = pl_leave_confirmed_members_count($pdo, $schema, $privateleagueId);

        // v1 policy: admin cannot leave if the league would remain ownerless with other members still present.
        if ($isCallerAdmin && $confirmedMembers > 1) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            pl_leave_error(409, 'ADMIN_CANNOT_LEAVE', 'Admin must transfer admin/delete league before leaving.');
        }

        if ($isCallerAdmin && $confirmedMembers <= 1) {
            pl_leave_delete_membership($pdo, $privateleagueId, $callerCompetitorId);
            pl_leave_delete_privateleague($pdo, $privateleagueId);
        } else {
            pl_leave_delete_membership($pdo, $privateleagueId, $callerCompetitorId);
        }

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
    pl_leave_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_leave_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/\d+/leave/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_leave_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_leave_resolve_privateleague_id(): int
{
    $raw = isset($_GET['privateleague_id']) ? (string) $_GET['privateleague_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/(\d+)/leave/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_leave_error(400, 'BAD_REQUEST', 'Invalid privateleague_id.');
    }
    return (int) $raw;
}

function pl_leave_db(): PDO
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

function pl_leave_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_leave_schema_info(PDO $pdo): array
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

function pl_leave_current_gw(PDO $pdo, int $leagueId): ?int
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

function pl_leave_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_leave_privateleague(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): ?array
{
    $adminColumn = pl_leave_privateleague_admin_column($schema);
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

function pl_leave_competitor_id_by_profile(PDO $pdo, int $profileId, int $leagueId): ?int
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

function pl_leave_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)";
    }
    return "CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_leave_confirmed_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'member_confirmed' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 1))";
    }
    return "{$alias}.confirmed = 1";
}

function pl_leave_membership_for_update(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $privateleagueId,
    int $competitorId
): ?array {
    $statusExpr = pl_leave_member_status_expr($schema, 'plm');
    $stmt = $pdo->prepare(
        'SELECT
            plm.privateleague_id,
            plm.competitor_id,
            ' . $statusExpr . ' AS status
         FROM privateleaguemembers plm
         INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
         WHERE pl.league_id = :league_id
           AND plm.privateleague_id = :privateleague_id
           AND plm.competitor_id = :competitor_id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_leave_confirmed_members_count(PDO $pdo, array $schema, int $privateleagueId): int
{
    $confirmedCondition = pl_leave_confirmed_condition($schema, 'plm');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM privateleaguemembers plm
         WHERE plm.privateleague_id = :privateleague_id
           AND ' . $confirmedCondition
    );
    $stmt->execute([':privateleague_id' => $privateleagueId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function pl_leave_delete_membership(PDO $pdo, int $privateleagueId, int $competitorId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM privateleaguemembers
         WHERE privateleague_id = :privateleague_id
           AND competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute([
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ]);
}

function pl_leave_delete_privateleague(PDO $pdo, int $privateleagueId): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM privateleague
         WHERE privateleague_id = :privateleague_id
         LIMIT 1'
    );
    $stmt->execute([':privateleague_id' => $privateleagueId]);
}

function pl_leave_authorization_header(): string
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

function pl_leave_require_auth_profile_id(): int
{
    $header = pl_leave_authorization_header();
    if ($header === '') {
        pl_leave_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_leave_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_leave_verify_jwt(string $token): array
{
    $secret = pl_leave_jwt_secret();
    if ($secret === '') {
        pl_leave_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_leave_b64url_decode($h64), true);
    $payload = json_decode((string) pl_leave_b64url_decode($p64), true);
    $sig = pl_leave_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_leave_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_leave_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_leave_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__, 4) . '/config/app.php';
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
