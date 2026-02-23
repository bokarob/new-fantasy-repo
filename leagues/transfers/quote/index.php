<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        quote_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = quote_resolve_league_id();
    $input = quote_json_input();
    $outgoing = quote_required_int_list($input, 'outgoing_player_ids');
    $incoming = quote_required_int_list($input, 'incoming_player_ids');

    $pdo = quote_db();
    $profileId = quote_require_auth_profile_id();
    $schema = quote_schema_info($pdo);

    if (!quote_league_exists($pdo, $leagueId)) {
        quote_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = quote_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        quote_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    $competitor = quote_competitor($pdo, $profileId, $leagueId, $schema);
    if ($competitor === null) {
        quote_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $rosterIds = quote_roster_ids_for_current_context($pdo, (int) $competitor['competitor_id'], (int) $gw['gw']);
    if ($rosterIds === null) {
        quote_error(500, 'INTERNAL_ERROR', 'Roster missing.');
    }

    $violations = [];
    $outSet = array_values(array_unique($outgoing));
    $inSet = array_values(array_unique($incoming));
    $requestedCount = count($outgoing);

    $hasInvalidCount =
        (count($outgoing) !== count($incoming))
        || ($requestedCount < 1 || $requestedCount > 2)
        || (count($outSet) !== count($outgoing))
        || (count($inSet) !== count($incoming));
    if ($hasInvalidCount) {
        $violations[] = [
            'code' => 'TRANSFER_INVALID_COUNT',
            'message' => 'Invalid outgoing/incoming count.',
        ];
    }

    $overlap = array_values(array_intersect($outSet, $inSet));
    if (!empty($overlap)) {
        $violations[] = [
            'code' => 'TRANSFER_SAME_PLAYER',
            'message' => 'Outgoing and incoming players cannot overlap.',
        ];
    }

    foreach ($outSet as $pid) {
        if (!in_array($pid, $rosterIds, true)) {
            $violations[] = [
                'code' => 'TRANSFER_PLAYER_NOT_OWNED',
                'message' => 'Outgoing player is not in roster.',
            ];
            break;
        }
    }

    foreach ($inSet as $pid) {
        if (in_array($pid, $rosterIds, true)) {
            $violations[] = [
                'code' => 'TRANSFER_PLAYER_ALREADY_OWNED',
                'message' => 'Incoming player is already in roster.',
            ];
            break;
        }
    }

    if (!$gw['is_open']) {
        $violations[] = [
            'code' => 'TRANSFER_NOT_ALLOWED_GW_CLOSED',
            'message' => 'Transfers are not allowed after deadline.',
        ];
    }

    $usedNormal = quote_transfers_used($pdo, (int) $competitor['competitor_id'], (int) $gw['gw'], $schema);
    $isFreeGw = quote_is_free_transfer_gw($pdo, $leagueId, (int) $gw['gw'], $schema);
    if (!$isFreeGw && ($usedNormal + $requestedCount > 2)) {
        $violations[] = [
            'code' => 'TRANSFER_LIMIT_REACHED',
            'message' => 'Transfer limit reached.',
        ];
    }

    $priceMap = quote_price_map($pdo, array_values(array_unique(array_merge($outSet, $inSet))), (int) $gw['gw']);
    $creditsBefore = (float) $competitor['credits'];
    $outTotal = 0.0;
    foreach ($outSet as $pid) {
        $outTotal += (float) ($priceMap[$pid] ?? 0.0);
    }
    $inTotal = 0.0;
    foreach ($inSet as $pid) {
        $inTotal += (float) ($priceMap[$pid] ?? 0.0);
    }
    $creditsAfter = round($creditsBefore + $outTotal - $inTotal, 1);
    if ($creditsAfter < 0) {
        $violations[] = [
            'code' => 'TRANSFER_BUDGET_INSUFFICIENT',
            'message' => 'Not enough credits.',
        ];
    }

    $teamMap = quote_player_team_map($pdo, array_values(array_unique(array_merge($rosterIds, $outSet, $inSet))));
    $simRoster = $rosterIds;
    if (!empty($outSet)) {
        $simRoster = array_values(array_diff($simRoster, $outSet));
    }
    if (!empty($inSet)) {
        $simRoster = array_values(array_merge($simRoster, $inSet));
    }
    $teamCounts = [];
    foreach ($simRoster as $pid) {
        if (!isset($teamMap[$pid])) {
            continue;
        }
        $teamId = (int) $teamMap[$pid];
        if (!isset($teamCounts[$teamId])) {
            $teamCounts[$teamId] = 0;
        }
        $teamCounts[$teamId]++;
    }
    foreach ($teamCounts as $count) {
        if ($count > 2) {
            $violations[] = [
                'code' => 'MAX_PLAYERS_FROM_TEAM',
                'message' => 'Max 2 players from the same team.',
            ];
            break;
        }
    }

    $response = [
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => (int) $gw['gw'],
            'last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
            'etag' => null,
        ],
        'data' => [
            'is_valid' => empty($violations),
            'summary' => [
                'credits_before' => $creditsBefore,
                'credits_after' => $creditsAfter,
                'transfers_used_after' => $usedNormal + $requestedCount,
            ],
            'violations' => $violations,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    quote_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function quote_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        quote_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        quote_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function quote_required_int_list(array $input, string $field): array
{
    if (!array_key_exists($field, $input) || !is_array($input[$field])) {
        quote_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $out = [];
    foreach ($input[$field] as $v) {
        if (is_int($v)) {
            $n = $v;
        } elseif (is_string($v) && ctype_digit($v)) {
            $n = (int) $v;
        } else {
            quote_error(400, 'BAD_REQUEST', 'Invalid payload.');
        }
        if ($n <= 0) {
            quote_error(400, 'BAD_REQUEST', 'Invalid payload.');
        }
        $out[] = $n;
    }
    return $out;
}

function quote_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/transfers/quote/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }
    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        quote_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function quote_db(): PDO
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

function quote_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function quote_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","competitor","roster","transfers","playertrade","player")'
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

function quote_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function quote_current_gameweek(PDO $pdo, int $leagueId): ?array
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
    return [
        'gw' => (int) $row['gameweek'],
        'is_open' => $isOpen,
    ];
}

function quote_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id, credits
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

function quote_roster_ids_for_current_context(PDO $pdo, int $competitorId, int $gw): ?array
{
    $stmt = $pdo->prepare(
        'SELECT player1, player2, player3, player4, player5, player6, player7, player8
         FROM roster
         WHERE competitor_id = :competitor_id AND gameweek = :gw
         LIMIT 1'
    );
    $stmt->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        $prev = $pdo->prepare(
            'SELECT player1, player2, player3, player4, player5, player6, player7, player8
             FROM roster
             WHERE competitor_id = :competitor_id
             ORDER BY gameweek DESC
             LIMIT 1'
        );
        $prev->execute([':competitor_id' => $competitorId]);
        $row = $prev->fetch();
    }

    if (!$row) {
        return null;
    }

    return [
        (int) $row['player1'],
        (int) $row['player2'],
        (int) $row['player3'],
        (int) $row['player4'],
        (int) $row['player5'],
        (int) $row['player6'],
        (int) $row['player7'],
        (int) $row['player8'],
    ];
}

function quote_transfers_used(PDO $pdo, int $competitorId, int $gw, array $schema): int
{
    $whereNormal = ($schema['transfers.normal'] ?? false) ? 'AND normal = 1' : '';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transfers WHERE competitor_id = :competitor_id AND gameweek = :gw ' . $whereNormal
    );
    $stmt->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    return (int) $stmt->fetchColumn();
}

function quote_is_free_transfer_gw(PDO $pdo, int $leagueId, int $gw, array $schema): bool
{
    if (!($schema['leagues.free_transfer_gw'] ?? false)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT free_transfer_gw FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    $val = $stmt->fetchColumn();
    return $val !== false && $val !== null && (int) $val === $gw;
}

function quote_price_map(PDO $pdo, array $playerIds, int $gw): array
{
    if (empty($playerIds)) {
        return [];
    }

    $bind = [];
    $params = [':gw' => $gw];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }

    $stmt = $pdo->prepare(
        'SELECT pt.player_id, pt.price
         FROM playertrade pt
         INNER JOIN (
             SELECT player_id, MAX(gameweek) AS max_gw
             FROM playertrade
             WHERE player_id IN (' . implode(',', $bind) . ') AND gameweek <= :gw
             GROUP BY player_id
         ) x ON x.player_id = pt.player_id AND x.max_gw = pt.gameweek'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = (float) $row['price'];
    }
    return $map;
}

function quote_player_team_map(PDO $pdo, array $playerIds): array
{
    if (empty($playerIds)) {
        return [];
    }

    $bind = [];
    $params = [];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }

    $stmt = $pdo->prepare(
        'SELECT player_id, team_id
         FROM player
         WHERE player_id IN (' . implode(',', $bind) . ')'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = (int) $row['team_id'];
    }
    return $map;
}

function quote_authorization_header(): string
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

function quote_require_auth_profile_id(): int
{
    $header = quote_authorization_header();
    if ($header === '') {
        quote_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = quote_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function quote_verify_jwt(string $token): array
{
    $secret = quote_jwt_secret();
    if ($secret === '') {
        quote_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) quote_b64url_decode($h64), true);
    $payload = json_decode((string) quote_b64url_decode($p64), true);
    $sig = quote_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        quote_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return $payload;
}

function quote_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function quote_jwt_secret(): string
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
