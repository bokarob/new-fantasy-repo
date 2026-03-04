<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        pl_invite_decline_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_invite_decline_resolve_league_id();
    $invite = pl_invite_decline_resolve_invite_parts();
    $invitePrivateleagueId = $invite['privateleague_id'];
    $inviteCompetitorId = $invite['competitor_id'];

    $pdo = pl_invite_decline_db();
    $profileId = pl_invite_decline_require_auth_profile_id();
    $schema = pl_invite_decline_schema_info($pdo);
    $currentGw = pl_invite_decline_current_gw($pdo, $leagueId);

    $callerCompetitorId = pl_invite_decline_competitor_id_by_profile($pdo, $profileId, $leagueId);
    if ($callerCompetitorId === null || $callerCompetitorId !== $inviteCompetitorId) {
        pl_invite_decline_error(404, 'INVITE_NOT_FOUND', 'Invite expired/invalid.');
    }

    try {
        $pdo->beginTransaction();

        $membership = pl_invite_decline_membership_for_update(
            $pdo,
            $schema,
            $leagueId,
            $invitePrivateleagueId,
            $inviteCompetitorId
        );

        if ($membership === null) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            pl_invite_decline_error(404, 'INVITE_NOT_FOUND', 'Invite expired/invalid.');
        }

        if ($membership['status'] !== 'pending') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            pl_invite_decline_error(409, 'INVITE_NOT_PENDING', 'Invite not pending.');
        }

        pl_invite_decline_apply_decision(
            $pdo,
            $schema,
            $invitePrivateleagueId,
            $inviteCompetitorId,
            $profileId
        );

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
    pl_invite_decline_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_invite_decline_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/invites/.+/decline/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_invite_decline_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_invite_decline_resolve_invite_parts(): array
{
    $raw = isset($_GET['invite_id']) ? (string) $_GET['invite_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/invites/([^/]+)/decline/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    $raw = $raw === null ? null : rawurldecode($raw);
    if ($raw === null || $raw === '') {
        pl_invite_decline_error(400, 'BAD_REQUEST', 'Invalid invite_id.');
    }

    if (!preg_match('/^pl([1-9][0-9]*)-c([1-9][0-9]*)$/', $raw, $m)) {
        pl_invite_decline_error(400, 'BAD_REQUEST', 'Invalid invite_id.');
    }

    return [
        'privateleague_id' => (int) $m[1],
        'competitor_id' => (int) $m[2],
    ];
}

function pl_invite_decline_db(): PDO
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

function pl_invite_decline_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_invite_decline_schema_info(PDO $pdo): array
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

function pl_invite_decline_current_gw(PDO $pdo, int $leagueId): ?int
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

function pl_invite_decline_competitor_id_by_profile(PDO $pdo, int $profileId, int $leagueId): ?int
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

function pl_invite_decline_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)";
    }
    return "CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_invite_decline_membership_for_update(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $privateleagueId,
    int $competitorId
): ?array {
    $statusExpr = pl_invite_decline_member_status_expr($schema, 'plm');
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

function pl_invite_decline_apply_decision(
    PDO $pdo,
    array $schema,
    int $privateleagueId,
    int $competitorId,
    int $decidedByProfileId
): void {
    if (!($schema['privateleaguemembers.status'] ?? false)) {
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
        return;
    }

    $sets = [
        'status = :status',
        'confirmed = 0',
    ];
    $params = [
        ':status' => 'declined',
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ];

    if ($schema['privateleaguemembers.decided_by_profile_id'] ?? false) {
        $sets[] = 'decided_by_profile_id = :decided_by_profile_id';
        $params[':decided_by_profile_id'] = $decidedByProfileId;
    }
    if ($schema['privateleaguemembers.responded_at'] ?? false) {
        $sets[] = 'responded_at = UTC_TIMESTAMP()';
    }
    if ($schema['privateleaguemembers.declined_at'] ?? false) {
        $sets[] = 'declined_at = UTC_TIMESTAMP()';
    }
    if ($schema['privateleaguemembers.updated_at'] ?? false) {
        $sets[] = 'updated_at = UTC_TIMESTAMP()';
    }

    $stmt = $pdo->prepare(
        'UPDATE privateleaguemembers
         SET ' . implode(', ', $sets) . '
         WHERE privateleague_id = :privateleague_id
           AND competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute($params);
}

function pl_invite_decline_authorization_header(): string
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

function pl_invite_decline_require_auth_profile_id(): int
{
    $header = pl_invite_decline_authorization_header();
    if ($header === '') {
        pl_invite_decline_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_invite_decline_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_invite_decline_verify_jwt(string $token): array
{
    $secret = pl_invite_decline_jwt_secret();
    if ($secret === '') {
        pl_invite_decline_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_invite_decline_b64url_decode($h64), true);
    $payload = json_decode((string) pl_invite_decline_b64url_decode($p64), true);
    $sig = pl_invite_decline_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_invite_decline_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_invite_decline_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_invite_decline_jwt_secret(): string
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
