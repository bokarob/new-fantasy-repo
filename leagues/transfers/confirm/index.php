<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        confirm_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = confirm_resolve_league_id();
    $input = confirm_json_input();
    $outgoing = confirm_required_int_list($input, 'outgoing_player_ids');
    $incoming = confirm_required_int_list($input, 'incoming_player_ids');

    $pdo = confirm_db();
    $profileId = confirm_require_auth_profile_id();
    $schema = confirm_schema_info($pdo);

    if (!confirm_league_exists($pdo, $leagueId)) {
        confirm_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = confirm_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        confirm_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    $error = null;
    $response = null;

    try {
        $pdo->beginTransaction();

        $competitor = confirm_competitor_for_update($pdo, $profileId, $leagueId);
        if ($competitor === null) {
            $error = [409, 'NO_COMPETITOR', 'User has no team in this league.'];
            throw new RuntimeException('NO_COMPETITOR');
        }

        $roster = confirm_roster_with_autocreate_for_update($pdo, (int) $competitor['competitor_id'], (int) $gw['gw']);
        if ($roster === null) {
            $error = [500, 'TRANSFER_ATOMICITY_FAILED', 'Atomic transfer failed.'];
            throw new RuntimeException('ROSTER_MISSING');
        }

        $outSet = array_values(array_unique($outgoing));
        $inSet = array_values(array_unique($incoming));
        $requestedCount = count($outgoing);

        $hasInvalidCount =
            (count($outgoing) !== count($incoming))
            || ($requestedCount < 1 || $requestedCount > 2)
            || (count($outSet) !== count($outgoing))
            || (count($inSet) !== count($incoming));
        if ($hasInvalidCount) {
            $error = [422, 'TRANSFER_INVALID_COUNT', 'Invalid outgoing/incoming count.'];
            throw new RuntimeException('TRANSFER_INVALID_COUNT');
        }

        if (!empty(array_intersect($outSet, $inSet))) {
            $error = [422, 'TRANSFER_SAME_PLAYER', 'Outgoing and incoming players cannot overlap.'];
            throw new RuntimeException('TRANSFER_SAME_PLAYER');
        }

        $rosterIds = confirm_roster_ids($roster);
        foreach ($outSet as $pid) {
            if (!in_array($pid, $rosterIds, true)) {
                $error = [422, 'TRANSFER_PLAYER_NOT_OWNED', 'Outgoing player is not in roster.'];
                throw new RuntimeException('TRANSFER_PLAYER_NOT_OWNED');
            }
        }
        foreach ($inSet as $pid) {
            if (in_array($pid, $rosterIds, true)) {
                $error = [422, 'TRANSFER_PLAYER_ALREADY_OWNED', 'Incoming player is already in roster.'];
                throw new RuntimeException('TRANSFER_PLAYER_ALREADY_OWNED');
            }
        }

        if (!$gw['is_open']) {
            $error = [409, 'TRANSFER_NOT_ALLOWED_GW_CLOSED', 'Transfers are not allowed after deadline.'];
            throw new RuntimeException('TRANSFER_NOT_ALLOWED_GW_CLOSED');
        }

        $usedNormal = confirm_transfers_used($pdo, (int) $competitor['competitor_id'], (int) $gw['gw'], $schema);
        $isFreeGw = confirm_is_free_transfer_gw($pdo, $leagueId, (int) $gw['gw'], $schema);
        if (!$isFreeGw && ($usedNormal + $requestedCount > 2)) {
            $error = [409, 'TRANSFER_LIMIT_REACHED', 'Transfer limit reached.'];
            throw new RuntimeException('TRANSFER_LIMIT_REACHED');
        }

        $priceMap = confirm_price_map($pdo, array_values(array_unique(array_merge($outSet, $inSet))), (int) $gw['gw']);
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
            $error = [422, 'TRANSFER_BUDGET_INSUFFICIENT', 'Not enough credits.'];
            throw new RuntimeException('TRANSFER_BUDGET_INSUFFICIENT');
        }

        $simulated = confirm_simulate_roster($rosterIds, $outgoing, $incoming);
        $teamMap = confirm_player_team_map($pdo, array_values(array_unique($simulated)));
        $teamCounts = [];
        foreach ($simulated as $pid) {
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
                $error = [422, 'MAX_PLAYERS_FROM_TEAM', 'Max 2 players from the same team.'];
                throw new RuntimeException('MAX_PLAYERS_FROM_TEAM');
            }
        }

        $updatedRoster = confirm_apply_roster_mapping($roster, $outgoing, $incoming);
        $updatedCaptain = confirm_fix_captain((int) $roster['captain'], $updatedRoster);

        $updateRoster = $pdo->prepare(
            'UPDATE roster
             SET player1=:p1, player2=:p2, player3=:p3, player4=:p4, player5=:p5, player6=:p6, player7=:p7, player8=:p8, captain=:captain
             WHERE competitor_id=:competitor_id AND gameweek=:gw'
        );
        $updateRoster->execute([
            ':p1' => $updatedRoster[0],
            ':p2' => $updatedRoster[1],
            ':p3' => $updatedRoster[2],
            ':p4' => $updatedRoster[3],
            ':p5' => $updatedRoster[4],
            ':p6' => $updatedRoster[5],
            ':p7' => $updatedRoster[6],
            ':p8' => $updatedRoster[7],
            ':captain' => $updatedCaptain,
            ':competitor_id' => (int) $competitor['competitor_id'],
            ':gw' => (int) $gw['gw'],
        ]);

        $updateCompetitor = $pdo->prepare('UPDATE competitor SET credits = :credits WHERE competitor_id = :competitor_id');
        $updateCompetitor->execute([
            ':credits' => $creditsAfter,
            ':competitor_id' => (int) $competitor['competitor_id'],
        ]);

        $insertTransfer = $pdo->prepare(
            'INSERT INTO transfers (competitor_id, gameweek, playerout, playerin, normal)
             VALUES (:competitor_id, :gw, :playerout, :playerin, 1)'
        );

        $firstTransferId = null;
        for ($i = 0; $i < $requestedCount; $i++) {
            $insertTransfer->execute([
                ':competitor_id' => (int) $competitor['competitor_id'],
                ':gw' => (int) $gw['gw'],
                ':playerout' => (int) $outgoing[$i],
                ':playerin' => (int) $incoming[$i],
            ]);
            if ($firstTransferId === null) {
                $firstTransferId = (int) $pdo->lastInsertId();
            }
        }

        $pdo->commit();

        $response = [
            'meta' => [
                'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
                'league_id' => $leagueId,
                'current_gw' => (int) $gw['gw'],
                'last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
                'etag' => null,
            ],
            'data' => [
                'ok' => true,
                'transfer_id' => $firstTransferId ?? 0,
            ],
        ];
    } catch (Throwable $txError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error !== null) {
            confirm_error($error[0], $error[1], $error[2]);
        }
        confirm_error(500, 'TRANSFER_ATOMICITY_FAILED', 'Atomic transfer failed.');
    }

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    confirm_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function confirm_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        confirm_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        confirm_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function confirm_required_int_list(array $input, string $field): array
{
    if (!array_key_exists($field, $input) || !is_array($input[$field])) {
        confirm_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $out = [];
    foreach ($input[$field] as $v) {
        if (is_int($v)) {
            $n = $v;
        } elseif (is_string($v) && ctype_digit($v)) {
            $n = (int) $v;
        } else {
            confirm_error(400, 'BAD_REQUEST', 'Invalid payload.');
        }
        if ($n <= 0) {
            confirm_error(400, 'BAD_REQUEST', 'Invalid payload.');
        }
        $out[] = $n;
    }
    return $out;
}

function confirm_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/transfers/confirm/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }
    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        confirm_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function confirm_db(): PDO
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

function confirm_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function confirm_schema_info(PDO $pdo): array
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

function confirm_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function confirm_current_gameweek(PDO $pdo, int $leagueId): ?array
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

function confirm_competitor_for_update(PDO $pdo, int $profileId, int $leagueId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id, credits
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

function confirm_roster_with_autocreate_for_update(PDO $pdo, int $competitorId, int $gw): ?array
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

function confirm_roster_ids(array $roster): array
{
    return [
        (int) $roster['player1'],
        (int) $roster['player2'],
        (int) $roster['player3'],
        (int) $roster['player4'],
        (int) $roster['player5'],
        (int) $roster['player6'],
        (int) $roster['player7'],
        (int) $roster['player8'],
    ];
}

function confirm_transfers_used(PDO $pdo, int $competitorId, int $gw, array $schema): int
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

function confirm_is_free_transfer_gw(PDO $pdo, int $leagueId, int $gw, array $schema): bool
{
    if (!($schema['leagues.free_transfer_gw'] ?? false)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT free_transfer_gw FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    $val = $stmt->fetchColumn();
    return $val !== false && $val !== null && (int) $val === $gw;
}

function confirm_price_map(PDO $pdo, array $playerIds, int $gw): array
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

function confirm_player_team_map(PDO $pdo, array $playerIds): array
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
        'SELECT player_id, team_id FROM player WHERE player_id IN (' . implode(',', $bind) . ')'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = (int) $row['team_id'];
    }
    return $map;
}

function confirm_simulate_roster(array $rosterIds, array $outgoing, array $incoming): array
{
    $updated = $rosterIds;
    for ($i = 0; $i < count($outgoing); $i++) {
        $out = (int) $outgoing[$i];
        $in = (int) $incoming[$i];
        $pos = array_search($out, $updated, true);
        if ($pos !== false) {
            $updated[(int) $pos] = $in;
        }
    }
    return $updated;
}

function confirm_apply_roster_mapping(array $roster, array $outgoing, array $incoming): array
{
    $updated = confirm_roster_ids($roster);
    for ($i = 0; $i < count($outgoing); $i++) {
        $out = (int) $outgoing[$i];
        $in = (int) $incoming[$i];
        $pos = array_search($out, $updated, true);
        if ($pos === false) {
            confirm_error(422, 'TRANSFER_PLAYER_NOT_OWNED', 'Outgoing player is not in roster.');
        }
        $updated[(int) $pos] = $in;
    }
    return $updated;
}

function confirm_fix_captain(int $currentCaptain, array $updatedRoster): int
{
    $captainPos = array_search($currentCaptain, $updatedRoster, true);
    $captainValid = $captainPos !== false && (int) $captainPos <= 5;
    if ($captainValid) {
        return $currentCaptain;
    }

    for ($i = 0; $i <= 5; $i++) {
        if (isset($updatedRoster[$i])) {
            return (int) $updatedRoster[$i];
        }
    }
    return (int) $updatedRoster[0];
}

function confirm_authorization_header(): string
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

function confirm_require_auth_profile_id(): int
{
    $header = confirm_authorization_header();
    if ($header === '') {
        confirm_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    $payload = confirm_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function confirm_verify_jwt(string $token): array
{
    $secret = confirm_jwt_secret();
    if ($secret === '') {
        confirm_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) confirm_b64url_decode($h64), true);
    $payload = json_decode((string) confirm_b64url_decode($p64), true);
    $sig = confirm_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        confirm_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function confirm_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function confirm_jwt_secret(): string
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
