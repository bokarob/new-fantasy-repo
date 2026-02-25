<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        transfers_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = transfers_resolve_league_id();
    $gwFilter = transfers_optional_positive_int_query('gw');
    $limit = transfers_query_limit();
    $offset = transfers_query_offset();

    $pdo = transfers_db();
    $profileId = transfers_require_auth_profile_id();
    $schema = transfers_schema_info($pdo);

    if (!transfers_league_exists($pdo, $leagueId)) {
        transfers_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = transfers_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        transfers_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    $competitor = transfers_competitor($pdo, $profileId, $leagueId);
    if ($competitor === null) {
        transfers_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $competitorId = (int) $competitor['competitor_id'];
    $currentGw = (int) $gw['gw'];
    $freeTransferGw = transfers_free_transfer_gw($pdo, $leagueId, $schema);
    $isFreeGw = $freeTransferGw !== null && $freeTransferGw === $currentGw;
    $transfersUsed = transfers_used_in_current_gw($pdo, $competitorId, $currentGw, $schema);

    $total = transfers_total_count($pdo, $competitorId, $gwFilter);
    $items = transfers_items($pdo, $competitorId, $gwFilter, $limit, $offset);

    $etagBuild = transfers_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        $competitorId,
        $currentGw,
        $gwFilter,
        $limit,
        $offset,
        $transfersUsed,
        $freeTransferGw,
        $total
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (transfers_if_none_match_matches($etagBuild['etag'])) {
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
            'competitor_id' => $competitorId,
            'current_gw' => $currentGw,
            'transfers_allowed' => 2,
            'transfers_used' => $transfersUsed,
            'is_free_gw' => $isFreeGw,
            'free_transfer_gw' => $freeTransferGw,
            'filter' => [
                'gw' => $gwFilter,
            ],
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    transfers_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function transfers_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/transfers/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }
    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function transfers_optional_positive_int_query(string $key): ?int
{
    if (!array_key_exists($key, $_GET)) {
        return null;
    }
    $raw = trim((string) $_GET[$key]);
    if ($raw === '' || !ctype_digit($raw)) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    $value = (int) $raw;
    if ($value <= 0) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $value;
}

function transfers_query_limit(): int
{
    if (!array_key_exists('limit', $_GET)) {
        return 50;
    }
    $raw = trim((string) $_GET['limit']);
    if ($raw === '' || !ctype_digit($raw)) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    $value = (int) $raw;
    if ($value <= 0 || $value > 200) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $value;
}

function transfers_query_offset(): int
{
    if (!array_key_exists('offset', $_GET)) {
        return 0;
    }
    $raw = trim((string) $_GET['offset']);
    if ($raw === '' || !ctype_digit($raw)) {
        transfers_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function transfers_db(): PDO
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

function transfers_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function transfers_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","competitor","transfers","player","team")'
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

function transfers_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function transfers_current_gameweek(PDO $pdo, int $leagueId): ?array
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
    $isOpen = ((int) $row['open'] === 1) && $deadlineTs !== false && time() <= $deadlineTs;
    return ['gw' => (int) $row['gameweek'], 'is_open' => $isOpen];
}

function transfers_competitor(PDO $pdo, int $profileId, int $leagueId): ?array
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

function transfers_free_transfer_gw(PDO $pdo, int $leagueId, array $schema): ?int
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

function transfers_used_in_current_gw(PDO $pdo, int $competitorId, int $gw, array $schema): int
{
    $normalWhere = ($schema['transfers.normal'] ?? false) ? 'AND normal = 1' : '';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transfers WHERE competitor_id = :competitor_id AND gameweek = :gw ' . $normalWhere
    );
    $stmt->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    return (int) $stmt->fetchColumn();
}

function transfers_total_count(PDO $pdo, int $competitorId, ?int $gwFilter): int
{
    $params = [':competitor_id' => $competitorId];
    $where = 'WHERE t.competitor_id = :competitor_id';
    if ($gwFilter !== null) {
        $where .= ' AND t.gameweek = :gw_filter';
        $params[':gw_filter'] = $gwFilter;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM transfers t ' . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function transfers_items(PDO $pdo, int $competitorId, ?int $gwFilter, int $limit, int $offset): array
{
    $params = [
        ':competitor_id' => $competitorId,
    ];
    $where = 'WHERE t.competitor_id = :competitor_id';
    if ($gwFilter !== null) {
        $where .= ' AND t.gameweek = :gw_filter';
        $params[':gw_filter'] = $gwFilter;
    }

    $sql = 'SELECT
                t.transfer_id,
                t.gameweek,
                t.normal,
                po.player_id AS outgoing_player_id,
                po.playername AS outgoing_player_name,
                tout.team_id AS outgoing_team_id,
                tout.short AS outgoing_team_short,
                tout.logo AS outgoing_team_logo,
                pi.player_id AS incoming_player_id,
                pi.playername AS incoming_player_name,
                tin.team_id AS incoming_team_id,
                tin.short AS incoming_team_short,
                tin.logo AS incoming_team_logo
            FROM transfers t
            LEFT JOIN player po ON po.player_id = t.playerout
            LEFT JOIN team tout ON tout.team_id = po.team_id AND tout.league_id = po.league_id
            LEFT JOIN player pi ON pi.player_id = t.playerin
            LEFT JOIN team tin ON tin.team_id = pi.team_id AND tin.league_id = pi.league_id
            ' . $where . '
            ORDER BY t.transfer_id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'transfer_id' => (int) $row['transfer_id'],
            'gw' => (int) $row['gameweek'],
            'is_free' => ((int) ($row['normal'] ?? 1) === 0),
            'outgoing' => [
                'player' => [
                    'player_id' => (int) ($row['outgoing_player_id'] ?? 0),
                    'name' => (string) ($row['outgoing_player_name'] ?? ''),
                ],
                'team' => [
                    'team_id' => (int) ($row['outgoing_team_id'] ?? 0),
                    'short' => (string) ($row['outgoing_team_short'] ?? ''),
                    'logo_url' => (string) ($row['outgoing_team_logo'] ?? ''),
                ],
            ],
            'incoming' => [
                'player' => [
                    'player_id' => (int) ($row['incoming_player_id'] ?? 0),
                    'name' => (string) ($row['incoming_player_name'] ?? ''),
                ],
                'team' => [
                    'team_id' => (int) ($row['incoming_team_id'] ?? 0),
                    'short' => (string) ($row['incoming_team_short'] ?? ''),
                    'logo_url' => (string) ($row['incoming_team_logo'] ?? ''),
                ],
            ],
        ];
    }

    return $items;
}

function transfers_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    int $competitorId,
    int $currentGw,
    ?int $gwFilter,
    int $limit,
    int $offset,
    int $transfersUsed,
    ?int $freeTransferGw,
    int $total
): array {
    $params = [':competitor_id' => $competitorId];
    $where = 'WHERE competitor_id = :competitor_id';
    if ($gwFilter !== null) {
        $where .= ' AND gameweek = :gw_filter';
        $params[':gw_filter'] = $gwFilter;
    }

    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(transfer_id), 0) FROM transfers ' . $where);
    $maxStmt->execute($params);
    $maxTransferId = (int) $maxStmt->fetchColumn();

    $lastUpdatedTs = time();
    if ($schema['transfers.updated_at'] ?? false) {
        $updatedStmt = $pdo->prepare('SELECT MAX(updated_at) FROM transfers ' . $where);
        $updatedStmt->execute($params);
        $updatedValue = $updatedStmt->fetchColumn();
        if ($updatedValue !== false && $updatedValue !== null) {
            $ts = strtotime((string) $updatedValue);
            if ($ts !== false) {
                $lastUpdatedTs = $ts;
            }
        }
    }

    $marker = [
        'transfers-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'gwcur:' . $currentGw,
        'gwf:' . ($gwFilter ?? 0),
        'p:' . $limit . ':' . $offset,
        'max:' . $maxTransferId,
        'tot:' . $total,
        'used:' . $transfersUsed,
        'free:' . ($freeTransferGw ?? 0),
    ];
    $etag = 'W/"transfers-u' . $profileId . '-l' . $leagueId . '-gw' . ($gwFilter ?? 0) . '-' . sha1(implode('|', $marker)) . '"';

    return [
        'etag' => $etag,
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function transfers_if_none_match_matches(string $etag): bool
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
            $candidateRaw = trim($candidate);
            $candidateWeak = preg_replace('/^W\//', '', $candidateRaw) ?? $candidateRaw;
            $candidateNorm = trim($candidateWeak, "\"' \t\r\n");
            if ($candidateRaw === $etagRaw || $candidateWeak === $etagWeak || $candidateNorm === $etagNorm) {
                return true;
            }
        }
    }

    return false;
}

function transfers_authorization_header(): string
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

function transfers_require_auth_profile_id(): int
{
    $header = transfers_authorization_header();
    if ($header === '') {
        transfers_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = transfers_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function transfers_verify_jwt(string $token): array
{
    $secret = transfers_jwt_secret();
    if ($secret === '') {
        transfers_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) transfers_b64url_decode($h64), true);
    $payload = json_decode((string) transfers_b64url_decode($p64), true);
    $sig = transfers_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        transfers_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function transfers_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function transfers_jwt_secret(): string
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
