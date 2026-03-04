<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        pl_invite_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_invite_resolve_league_id();
    $privateleagueId = pl_invite_resolve_privateleague_id();

    $pdo = pl_invite_db();
    $profileId = pl_invite_require_auth_profile_id();
    $schema = pl_invite_schema_info($pdo);
    $currentGw = pl_invite_current_gw($pdo, $leagueId, $schema);

    $privateleague = pl_invite_privateleague($pdo, $schema, $leagueId, $privateleagueId);
    if ($privateleague === null) {
        pl_invite_error(404, 'PRIVATE_LEAGUE_NOT_FOUND', 'Private league not found.');
    }

    $adminProfileId = (int) ($privateleague['admin_profile_id'] ?? 0);
    if ($profileId !== $adminProfileId) {
        pl_invite_error(403, 'NOT_ADMIN', 'Admin privileges required for action.');
    }

    $input = pl_invite_json_input_required();
    $targetCompetitorId = pl_invite_resolve_target_competitor_id($input);

    $target = pl_invite_competitor_by_id($pdo, $leagueId, $targetCompetitorId);
    if ($target === null) {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $status = pl_invite_membership_status($pdo, $schema, $privateleagueId, $targetCompetitorId);
    if ($status === 'member_confirmed') {
        pl_invite_error(409, 'ALREADY_MEMBER', 'Target already member.');
    }
    if ($status === 'pending') {
        pl_invite_error(409, 'ALREADY_INVITED', 'Invite already pending.');
    }

    $adminCompetitor = pl_invite_competitor_by_profile($pdo, $profileId, $leagueId, $schema);
    $adminCompetitorId = $adminCompetitor === null ? null : (int) $adminCompetitor['competitor_id'];

    try {
        $pdo->beginTransaction();
        pl_invite_insert_pending_membership($pdo, $schema, $privateleagueId, $targetCompetitorId, $profileId);
        pl_invite_touch_admin_membership($pdo, $schema, $privateleagueId, $adminCompetitorId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException && ($e->getCode() === '23000' || $e->getCode() === '23505')) {
            $statusAfter = pl_invite_membership_status($pdo, $schema, $privateleagueId, $targetCompetitorId);
            if ($statusAfter === 'member_confirmed') {
                pl_invite_error(409, 'ALREADY_MEMBER', 'Target already member.');
            }
            if ($statusAfter === 'pending') {
                pl_invite_error(409, 'ALREADY_INVITED', 'Invite already pending.');
            }
            pl_invite_error(409, 'ALREADY_INVITED', 'Invite already pending.');
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
    pl_invite_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_invite_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/\d+/invite/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_invite_resolve_privateleague_id(): int
{
    $raw = isset($_GET['privateleague_id']) ? (string) $_GET['privateleague_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/(\d+)/invite/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid privateleague_id.');
    }
    return (int) $raw;
}

function pl_invite_db(): PDO
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

function pl_invite_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_invite_json_input_required(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function pl_invite_resolve_target_competitor_id(array $input): int
{
    if (!array_key_exists('competitor_id', $input)) {
        pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $value = $input['competitor_id'];
    if (is_int($value)) {
        if ($value <= 0) {
            pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
        }
        return $value;
    }

    if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
        return (int) $value;
    }

    pl_invite_error(400, 'BAD_REQUEST', 'Invalid payload.');
}

function pl_invite_schema_info(PDO $pdo): array
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
           AND table_name IN ("gameweeks","privateleague","privateleaguemembers","competitor","profile")'
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

function pl_invite_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_invite_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "CASE WHEN {$alias}.competitor_id IS NULL THEN '' ELSE COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END) END";
    }
    return "CASE WHEN {$alias}.competitor_id IS NULL THEN '' WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_invite_current_gw(PDO $pdo, int $leagueId, array $schema): int
{
    $updatedPart = ($schema['gameweeks.updated_at'] ?? false) ? ', updated_at' : '';
    $stmt = $pdo->prepare(
        'SELECT gameweek' . $updatedPart . '
         FROM gameweeks
         WHERE league_id = :league_id
         ORDER BY (`open` = 1) DESC, gameweek DESC
         LIMIT 1'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $row = $stmt->fetch();
    if (!$row) {
        return 0;
    }
    return (int) ($row['gameweek'] ?? 0);
}

function pl_invite_privateleague(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): ?array
{
    $adminColumn = pl_invite_privateleague_admin_column($schema);
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

function pl_invite_competitor_by_id(PDO $pdo, int $leagueId, int $competitorId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id, profile_id
         FROM competitor
         WHERE league_id = :league_id
           AND competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':competitor_id' => $competitorId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_invite_competitor_by_profile(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['competitor.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT competitor_id' . $updatedPart . '
         FROM competitor
         WHERE profile_id = :profile_id
           AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_invite_membership_status(PDO $pdo, array $schema, int $privateleagueId, int $competitorId): string
{
    $statusExpr = pl_invite_member_status_expr($schema, 'plm');
    $stmt = $pdo->prepare(
        'SELECT ' . $statusExpr . ' AS status
         FROM privateleaguemembers plm
         WHERE plm.privateleague_id = :privateleague_id
           AND plm.competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute([
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ]);
    $status = $stmt->fetchColumn();
    return $status === false || $status === null ? '' : (string) $status;
}

function pl_invite_insert_pending_membership(
    PDO $pdo,
    array $schema,
    int $privateleagueId,
    int $competitorId,
    int $requestedByProfileId
): void {
    $columns = ['privateleague_id', 'competitor_id', 'confirmed'];
    $values = [':privateleague_id', ':competitor_id', ':confirmed'];
    $params = [
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
        ':confirmed' => 0,
    ];

    if ($schema['privateleaguemembers.status'] ?? false) {
        $columns[] = 'status';
        $values[] = ':status';
        $params[':status'] = 'pending';
    }
    if ($schema['privateleaguemembers.request_kind'] ?? false) {
        $columns[] = 'request_kind';
        $values[] = ':request_kind';
        $params[':request_kind'] = 'invite';
    }
    if ($schema['privateleaguemembers.requested_by_profile_id'] ?? false) {
        $columns[] = 'requested_by_profile_id';
        $values[] = ':requested_by_profile_id';
        $params[':requested_by_profile_id'] = $requestedByProfileId;
    }
    if ($schema['privateleaguemembers.decided_by_profile_id'] ?? false) {
        $columns[] = 'decided_by_profile_id';
        $values[] = ':decided_by_profile_id';
        $params[':decided_by_profile_id'] = null;
    }
    if ($schema['privateleaguemembers.requested_at'] ?? false) {
        $columns[] = 'requested_at';
        $values[] = 'UTC_TIMESTAMP()';
    }
    if ($schema['privateleaguemembers.responded_at'] ?? false) {
        $columns[] = 'responded_at';
        $values[] = 'NULL';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO privateleaguemembers (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $values) . ')'
    );
    $stmt->execute($params);
}

function pl_invite_touch_admin_membership(PDO $pdo, array $schema, int $privateleagueId, ?int $adminCompetitorId): void
{
    if (!($schema['privateleaguemembers.updated_at'] ?? false) || $adminCompetitorId === null || $adminCompetitorId <= 0) {
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
        ':competitor_id' => $adminCompetitorId,
    ]);
}

function pl_invite_authorization_header(): string
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

function pl_invite_require_auth_profile_id(): int
{
    $header = pl_invite_authorization_header();
    if ($header === '') {
        pl_invite_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_invite_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_invite_verify_jwt(string $token): array
{
    $secret = pl_invite_jwt_secret();
    if ($secret === '') {
        pl_invite_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_invite_b64url_decode($h64), true);
    $payload = json_decode((string) pl_invite_b64url_decode($p64), true);
    $sig = pl_invite_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_invite_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_invite_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_invite_jwt_secret(): string
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
