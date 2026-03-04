<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        pl_list_handle_get();
    }
    if ($method === 'POST') {
        pl_list_handle_post();
    }
    if ($method !== 'GET' && $method !== 'POST') {
        pl_list_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }
} catch (Throwable $e) {
    pl_list_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_list_handle_get(): void
{
    $leagueId = pl_list_resolve_league_id();
    $pdo = pl_list_db();
    $profileId = pl_list_require_auth_profile_id();
    $schema = pl_list_schema_info($pdo);

    if (!pl_list_league_exists($pdo, $leagueId)) {
        pl_list_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = pl_list_current_gameweek($pdo, $leagueId, $schema);
    if ($gw === null) {
        pl_list_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $competitor = pl_list_competitor($pdo, $profileId, $leagueId, $schema);
    $competitorId = $competitor === null ? null : (int) $competitor['competitor_id'];

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
    $invites = [];
    $leagues = [];
    if ($competitorId !== null) {
        $invites = pl_list_invites($pdo, $schema, $leagueId, $profileId, $competitorId, $serverTime);
        $leagues = pl_list_leagues($pdo, $schema, $leagueId, $profileId, $competitorId);
    }

    $etagBuild = pl_list_etag_and_last_updated($pdo, $schema, $profileId, $leagueId, $competitorId, $invites, $leagues, $serverTime);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (pl_list_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    echo json_encode([
        'meta' => [
            'server_time' => $serverTime,
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'league_id' => $leagueId,
            'invites' => $invites,
            'leagues' => $leagues,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_list_handle_post(): void
{
    header('Cache-Control: no-store');

    $leagueId = pl_list_resolve_league_id();
    $pdo = pl_list_db();
    $profileId = pl_list_require_auth_profile_id();
    $schema = pl_list_schema_info($pdo);

    if (!pl_list_league_exists($pdo, $leagueId)) {
        pl_list_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = pl_list_current_gameweek($pdo, $leagueId, $schema);
    if ($gw === null) {
        pl_list_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $competitor = pl_list_competitor($pdo, $profileId, $leagueId, $schema);
    if ($competitor === null) {
        pl_list_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }
    $competitorId = (int) $competitor['competitor_id'];

    $input = pl_list_json_input_required();
    $leaguename = pl_list_validated_leaguename($input);

    if (pl_list_privateleague_name_exists($pdo, $leagueId, $leaguename)) {
        pl_list_error(409, 'NAME_ALREADY_USED', 'Name already used.');
    }

    $privateleagueId = 0;
    try {
        $pdo->beginTransaction();
        $privateleagueId = pl_list_insert_privateleague($pdo, $schema, $leagueId, $leaguename, $profileId);
        pl_list_insert_creator_membership($pdo, $schema, $privateleagueId, $competitorId, $profileId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException && ($e->getCode() === '23000' || $e->getCode() === '23505')) {
            pl_list_error(409, 'NAME_ALREADY_USED', 'Name already used.');
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
            'privateleague_id' => $privateleagueId,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_list_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_list_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function pl_list_db(): PDO
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

function pl_list_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_list_json_input_required(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        pl_list_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        pl_list_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    return $decoded;
}

function pl_list_validated_leaguename(array $input): string
{
    if (!array_key_exists('leaguename', $input) || !is_string($input['leaguename'])) {
        pl_list_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $name = trim((string) $input['leaguename']);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($name === '' || $length < 3 || $length > 30) {
        pl_list_error(422, 'VALIDATION_ERROR', 'Invalid private league name.');
    }

    if (!preg_match("/^[\\p{L}\\p{N} ._\\-'&]+$/u", $name)) {
        pl_list_error(422, 'VALIDATION_ERROR', 'Invalid private league name.');
    }

    return $name;
}

function pl_list_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","competitor","privateleague","privateleaguemembers","profile")'
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

function pl_list_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function pl_list_current_gameweek(PDO $pdo, int $leagueId, array $schema): ?array
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
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function pl_list_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
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

function pl_list_privateleague_name_exists(PDO $pdo, int $leagueId, string $leaguename): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM privateleague
         WHERE league_id = :league_id
           AND LOWER(leaguename) = LOWER(:leaguename)
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':leaguename' => $leaguename,
    ]);
    return (bool) $stmt->fetchColumn();
}

function pl_list_insert_privateleague(PDO $pdo, array $schema, int $leagueId, string $leaguename, int $profileId): int
{
    $adminColumn = pl_list_privateleague_admin_column($schema);
    $stmt = $pdo->prepare(
        'INSERT INTO privateleague (league_id, leaguename, ' . $adminColumn . ')
         VALUES (:league_id, :leaguename, :admin_profile_id)'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':leaguename' => $leaguename,
        ':admin_profile_id' => $profileId,
    ]);

    $id = (int) $pdo->lastInsertId();
    if ($id <= 0) {
        pl_list_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }
    return $id;
}

function pl_list_insert_creator_membership(
    PDO $pdo,
    array $schema,
    int $privateleagueId,
    int $competitorId,
    int $profileId
): void {
    $columns = ['privateleague_id', 'competitor_id', 'confirmed'];
    $values = [':privateleague_id', ':competitor_id', ':confirmed'];
    $params = [
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
        ':confirmed' => 1,
    ];

    if ($schema['privateleaguemembers.status'] ?? false) {
        $columns[] = 'status';
        $values[] = ':status';
        $params[':status'] = 'member_confirmed';
    }
    if ($schema['privateleaguemembers.request_kind'] ?? false) {
        $columns[] = 'request_kind';
        $values[] = ':request_kind';
        $params[':request_kind'] = null;
    }
    if ($schema['privateleaguemembers.requested_by_profile_id'] ?? false) {
        $columns[] = 'requested_by_profile_id';
        $values[] = ':requested_by_profile_id';
        $params[':requested_by_profile_id'] = $profileId;
    }
    if ($schema['privateleaguemembers.decided_by_profile_id'] ?? false) {
        $columns[] = 'decided_by_profile_id';
        $values[] = ':decided_by_profile_id';
        $params[':decided_by_profile_id'] = $profileId;
    }
    if ($schema['privateleaguemembers.responded_at'] ?? false) {
        $columns[] = 'responded_at';
        $values[] = 'UTC_TIMESTAMP()';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO privateleaguemembers (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $values) . ')'
    );
    $stmt->execute($params);
}

function pl_list_invites(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $profileId,
    int $competitorId,
    string $serverTime
): array {
    $adminColumn = pl_list_privateleague_admin_column($schema);
    $statusExpr = pl_list_member_status_expr($schema, 'plm');
    $createdExpr = pl_list_membership_created_expr($schema, 'plm');
    $pendingCondition = pl_list_pending_condition($schema, 'plm');

    $stmt = $pdo->prepare(
        'SELECT
            pl.privateleague_id,
            pl.leaguename,
            COALESCE(admin_profile.alias, \'\') AS admin_alias,
            COALESCE(' . $createdExpr . ', UTC_TIMESTAMP()) AS created_at,
            ' . $statusExpr . ' AS your_status
         FROM privateleaguemembers plm
         INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
         LEFT JOIN profile admin_profile ON admin_profile.profile_id = pl.' . $adminColumn . '
         WHERE pl.league_id = :league_id
           AND plm.competitor_id = :competitor_id
           AND ' . $pendingCondition . '
         ORDER BY pl.privateleague_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':competitor_id' => $competitorId,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $privateleagueId = (int) ($row['privateleague_id'] ?? 0);
        $status = (string) ($row['your_status'] ?? 'pending');
        $status = $status !== '' ? $status : 'pending';
        $items[] = [
            'invite_id' => 'pl' . $privateleagueId . '-c' . $competitorId,
            'privateleague_id' => $privateleagueId,
            'leaguename' => (string) ($row['leaguename'] ?? ''),
            'admin_alias' => (string) ($row['admin_alias'] ?? ''),
            'created_at' => pl_list_datetime_iso((string) ($row['created_at'] ?? ''), $serverTime),
            'status' => $status,
            'target' => [
                'kind' => 'private_league_invite',
                'league_id' => $leagueId,
                'params' => [
                    'privateleague_id' => $privateleagueId,
                ],
            ],
        ];
    }

    return $items;
}

function pl_list_leagues(PDO $pdo, array $schema, int $leagueId, int $profileId, int $competitorId): array
{
    $adminColumn = pl_list_privateleague_admin_column($schema);
    $statusExpr = pl_list_member_status_expr($schema, 'plm');
    $confirmedCondition = pl_list_confirmed_condition($schema, 'plm');
    $memberCountCondition = pl_list_confirmed_condition($schema, 'plm_count');

    $stmt = $pdo->prepare(
        'SELECT
            pl.privateleague_id,
            pl.leaguename,
            COALESCE(admin_profile.alias, \'\') AS admin_alias,
            COALESCE(pl.' . $adminColumn . ', 0) AS admin_profile_id,
            COALESCE(mc.member_count, 0) AS member_count,
            ' . $statusExpr . ' AS your_status
         FROM privateleaguemembers plm
         INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
         LEFT JOIN profile admin_profile ON admin_profile.profile_id = pl.' . $adminColumn . '
         LEFT JOIN (
            SELECT
                plm_count.privateleague_id,
                COUNT(*) AS member_count
            FROM privateleaguemembers plm_count
            WHERE ' . $memberCountCondition . '
            GROUP BY plm_count.privateleague_id
         ) mc ON mc.privateleague_id = pl.privateleague_id
         WHERE pl.league_id = :league_id
           AND plm.competitor_id = :competitor_id
           AND ' . $confirmedCondition . '
         ORDER BY pl.privateleague_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':competitor_id' => $competitorId,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $adminProfileId = (int) ($row['admin_profile_id'] ?? 0);
        $status = (string) ($row['your_status'] ?? 'member_confirmed');
        $status = $status !== '' ? $status : 'member_confirmed';

        $items[] = [
            'privateleague_id' => (int) ($row['privateleague_id'] ?? 0),
            'leaguename' => (string) ($row['leaguename'] ?? ''),
            'admin_alias' => (string) ($row['admin_alias'] ?? ''),
            'member_count' => (int) ($row['member_count'] ?? 0),
            'your_role' => $adminProfileId === $profileId ? 'admin' : 'member',
            'your_status' => $status,
        ];
    }

    return $items;
}

function pl_list_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_list_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)";
    }
    return "CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_list_membership_created_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.requested_at'] ?? false) {
        return "{$alias}.requested_at";
    }
    if ($schema['privateleaguemembers.created_at'] ?? false) {
        return "{$alias}.created_at";
    }
    if ($schema['privateleaguemembers.updated_at'] ?? false) {
        return "{$alias}.updated_at";
    }
    return 'NULL';
}

function pl_list_pending_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'pending' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 0))";
    }
    return "{$alias}.confirmed = 0";
}

function pl_list_confirmed_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'member_confirmed' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 1))";
    }
    return "{$alias}.confirmed = 1";
}

function pl_list_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    ?int $competitorId,
    array $invites,
    array $leagues,
    string $serverTime
): array {
    $membershipCount = count($invites) + count($leagues);
    $maxPrivateleagueId = 0;
    foreach ($invites as $item) {
        $maxPrivateleagueId = max($maxPrivateleagueId, (int) ($item['privateleague_id'] ?? 0));
    }
    foreach ($leagues as $item) {
        $maxPrivateleagueId = max($maxPrivateleagueId, (int) ($item['privateleague_id'] ?? 0));
    }

    $maxMemberTouch = '0';
    $lastUpdated = $serverTime;
    if ($competitorId !== null && ($schema['privateleaguemembers.updated_at'] ?? false)) {
        $pendingCondition = pl_list_pending_condition($schema, 'plm');
        $confirmedCondition = pl_list_confirmed_condition($schema, 'plm');
        $stmt = $pdo->prepare(
            'SELECT MAX(plm.updated_at) AS max_touch
             FROM privateleaguemembers plm
             INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
             WHERE pl.league_id = :league_id
               AND plm.competitor_id = :competitor_id
               AND (' . $pendingCondition . ' OR ' . $confirmedCondition . ')'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':competitor_id' => $competitorId,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $maxMemberTouch = (string) $value;
            $ts = strtotime($maxMemberTouch);
            if ($ts !== false) {
                $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }
    }

    $marker = [
        'pl-list-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'touch:' . $maxMemberTouch,
        'cnt:' . $membershipCount,
        'maxpl:' . $maxPrivateleagueId,
    ];

    return [
        'etag' => 'W/"pl-list-u' . $profileId . '-l' . $leagueId . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => $lastUpdated,
    ];
}

function pl_list_if_none_match_matches(string $etag): bool
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

function pl_list_authorization_header(): string
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

function pl_list_require_auth_profile_id(): int
{
    $header = pl_list_authorization_header();
    if ($header === '') {
        pl_list_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_list_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_list_verify_jwt(string $token): array
{
    $secret = pl_list_jwt_secret();
    if ($secret === '') {
        pl_list_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_list_b64url_decode($h64), true);
    $payload = json_decode((string) pl_list_b64url_decode($p64), true);
    $sig = pl_list_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_list_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_list_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_list_jwt_secret(): string
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

function pl_list_datetime_iso(string $value, string $fallback): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return $fallback;
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
