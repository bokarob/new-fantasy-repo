<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST') {
        team_handle_create_post();
    }
    if ($method !== 'GET') {
        team_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = team_resolve_league_id();
    $pdo = team_db();
    $profileId = team_require_auth_profile_id();
    $schema = team_schema_info($pdo);

    if (!team_league_exists($pdo, $leagueId)) {
        team_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = team_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        team_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    $competitor = team_competitor($pdo, $profileId, $leagueId, $schema);
    if ($competitor === null) {
        team_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $roster = team_roster_with_autocreate($pdo, (int) $competitor['competitor_id'], (int) $gw['gw'], $schema);
    if ($roster === null) {
        team_error(404, 'ROSTER_NOT_FOUND', 'Roster not found.');
    }

    $positions = team_build_positions($pdo, $leagueId, (int) $gw['gw'], $roster, $schema);
    $transfersUsed = team_transfers_used($pdo, (int) $competitor['competitor_id'], (int) $gw['gw'], $schema);

    $etagBuild = team_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        (int) $gw['gw'],
        $competitor,
        $roster,
        $positions,
        $transfersUsed
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (team_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $response = [
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => (int) $gw['gw'],
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'competitor' => [
                'competitor_id' => (int) $competitor['competitor_id'],
                'teamname' => (string) $competitor['teamname'],
                'credits' => (float) $competitor['credits'],
                'favorite_team_id' => $competitor['favorite_team_id'] !== null ? (int) $competitor['favorite_team_id'] : null,
            ],
            'gameweek' => [
                'gw' => (int) $gw['gw'],
                'deadline' => (string) $gw['deadline'],
                'is_open' => (bool) $gw['is_open'],
                'transfers_allowed' => 2,
                'transfers_used' => $transfersUsed,
            ],
            'roster' => [
                'captain_player_id' => (int) $roster['captain'],
                'positions' => $positions,
            ],
            'config' => [
                'max_from_same_team' => 2,
                'starters_count' => 6,
                'subs_count' => 2,
            ],
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    team_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function team_handle_create_post(): void
{
    header('Cache-Control: no-store');

    $leagueId = team_resolve_league_id();
    $pdo = team_db();
    $profileId = team_require_auth_profile_id();
    $schema = team_schema_info($pdo);

    if (!team_league_exists($pdo, $leagueId)) {
        team_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = team_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        team_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }

    if (!(bool) $gw['is_open']) {
        team_error(409, 'TEAM_CREATION_NOT_ALLOWED', 'Team creation is not allowed for this gameweek.');
    }

    $existing = team_competitor($pdo, $profileId, $leagueId, $schema);
    if ($existing !== null) {
        team_error(409, 'TEAM_ALREADY_EXISTS', 'Team already exists for this user in this league.');
    }

    $input = team_json_input();
    if (!array_key_exists('teamname', $input) || !array_key_exists('player_ids', $input) || !array_key_exists('captain_player_id', $input) || !array_key_exists('favorite_team_id', $input)) {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $teamname = team_validate_teamname($input['teamname']);
    $playerIds = team_validate_player_ids($input['player_ids']);
    $captainPlayerId = team_required_int($input['captain_player_id']);
    $favoriteTeamId = team_required_int($input['favorite_team_id']);

    $playerRows = team_player_rows_for_creation($pdo, $leagueId, $playerIds);
    if (count($playerRows) !== count($playerIds)) {
        team_error(404, 'PLAYER_NOT_FOUND', 'Player not found.');
    }
    $playerById = [];
    foreach ($playerRows as $row) {
        $playerById[(int) $row['player_id']] = $row;
    }
    foreach ($playerIds as $pid) {
        if (!isset($playerById[$pid])) {
            team_error(404, 'PLAYER_NOT_FOUND', 'Player not found.');
        }
    }

    if (!team_favorite_team_valid($pdo, $leagueId, $favoriteTeamId)) {
        team_error(422, 'FAVORITE_TEAM_INVALID', 'Invalid favorite team.');
    }

    $captainPos = array_search($captainPlayerId, $playerIds, true);
    if ($captainPos === false) {
        team_error(422, 'CAPTAIN_INVALID', 'Captain must be in roster.');
    }
    if ((int) $captainPos >= 6) {
        team_error(422, 'CAPTAIN_NOT_STARTER', 'Captain must be selected from starters.');
    }

    $teamCounts = [];
    foreach ($playerIds as $pid) {
        $teamId = (int) $playerById[$pid]['team_id'];
        if (!isset($teamCounts[$teamId])) {
            $teamCounts[$teamId] = 0;
        }
        $teamCounts[$teamId]++;
        if ($teamCounts[$teamId] > 2) {
            team_error(422, 'MAX_PLAYERS_FROM_TEAM', 'Max 2 players from the same team.');
        }
    }

    $priceMap = team_price_map($pdo, $playerIds, (int) $gw['gw'], $schema);
    $totalCost = 0.0;
    foreach ($playerIds as $pid) {
        $totalCost += (float) ($priceMap[$pid] ?? 0.0);
    }
    if ($totalCost > 80.0) {
        team_error(422, 'INITIAL_BUDGET_EXCEEDED', 'Initial budget exceeded.');
    }
    $credits = round(80.0 - $totalCost, 1);

    try {
        $pdo->beginTransaction();

        $columns = ['profile_id', 'league_id', 'teamname', 'credits', 'favorite_team_id'];
        $values = [':profile_id', ':league_id', ':teamname', ':credits', ':favorite_team_id'];
        $params = [
            ':profile_id' => $profileId,
            ':league_id' => $leagueId,
            ':teamname' => $teamname,
            ':credits' => $credits,
            ':favorite_team_id' => $favoriteTeamId,
        ];
        if ($schema['competitor.favorite_team_changed'] ?? false) {
            $columns[] = 'favorite_team_changed';
            $values[] = ':favorite_team_changed';
            $params[':favorite_team_changed'] = 0;
        }

        $insertCompetitor = $pdo->prepare(
            'INSERT INTO competitor (' . implode(',', $columns) . ')
             VALUES (' . implode(',', $values) . ')'
        );
        $insertCompetitor->execute($params);
        $competitorId = (int) $pdo->lastInsertId();

        if ($competitorId <= 0) {
            throw new RuntimeException('COMPETITOR_CREATE_FAILED');
        }

        $insertRoster = $pdo->prepare(
            'INSERT INTO roster (competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain)
             VALUES (:competitor_id, :gw, :player1, :player2, :player3, :player4, :player5, :player6, :player7, :player8, :captain)'
        );
        $insertRoster->execute([
            ':competitor_id' => $competitorId,
            ':gw' => (int) $gw['gw'],
            ':player1' => $playerIds[0],
            ':player2' => $playerIds[1],
            ':player3' => $playerIds[2],
            ':player4' => $playerIds[3],
            ':player5' => $playerIds[4],
            ':player6' => $playerIds[5],
            ':player7' => $playerIds[6],
            ':player8' => $playerIds[7],
            ':captain' => $captainPlayerId,
        ]);

        $pdo->commit();

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
                'competitor_id' => $competitorId,
                'teamname' => $teamname,
                'credits' => $credits,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $txe) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($txe instanceof PDOException && $txe->getCode() === '23000') {
            team_error(409, 'TEAM_ALREADY_EXISTS', 'Team already exists for this user in this league.');
        }
        team_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }
}

function team_resolve_league_id(): int
{
    $raw = null;
    if (isset($_GET['league_id'])) {
        $raw = (string) $_GET['league_id'];
    } else {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/team/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw)) {
        team_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }

    $leagueId = (int) $raw;
    if ($leagueId <= 0) {
        team_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return $leagueId;
}

function team_db(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'fantasy_app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function team_error(int $status, string $code, string $message): void
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

function team_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $decoded;
}

function team_required_int($value): int
{
    if (is_int($value)) {
        $n = $value;
    } elseif (is_string($value) && ctype_digit($value)) {
        $n = (int) $value;
    } else {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    if ($n <= 0) {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    return $n;
}

function team_validate_teamname($value): string
{
    if (!is_string($value)) {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    $name = trim($value);
    if ($name === '' || strlen($name) > 50) {
        team_error(422, 'TEAMNAME_INVALID', 'Invalid team name.');
    }
    if (!preg_match("/^[A-Za-z0-9 ._\\-']+$/", $name)) {
        team_error(422, 'TEAMNAME_INVALID', 'Invalid team name.');
    }
    return $name;
}

function team_validate_player_ids($value): array
{
    if (!is_array($value)) {
        team_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }
    if (count($value) !== 8) {
        team_error(422, 'ROSTER_INVALID_SIZE', 'Roster must contain exactly 8 players.');
    }

    $playerIds = [];
    foreach ($value as $item) {
        $playerIds[] = team_required_int($item);
    }

    if (count(array_unique($playerIds)) !== 8) {
        team_error(422, 'ROSTER_INVALID_POSITION', 'Invalid roster ordering.');
    }
    return array_values($playerIds);
}

function team_player_rows_for_creation(PDO $pdo, int $leagueId, array $playerIds): array
{
    if (empty($playerIds)) {
        return [];
    }
    $bind = [];
    $params = [':league_id' => $leagueId];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }
    $stmt = $pdo->prepare(
        'SELECT player_id, team_id
         FROM player
         WHERE league_id = :league_id
           AND player_id IN (' . implode(',', $bind) . ')'
    );
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function team_favorite_team_valid(PDO $pdo, int $leagueId, int $favoriteTeamId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM team
         WHERE league_id = :league_id AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':team_id' => $favoriteTeamId,
    ]);
    return (bool) $stmt->fetchColumn();
}

function team_schema_info(PDO $pdo): array
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
           AND table_name IN ("competitor","gameweeks","roster","transfers","playertrade","playerresult","matches","team")'
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

function team_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function team_current_gameweek(PDO $pdo, int $leagueId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT gameweek, deadline, gamedate, `open`
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
        'deadline' => team_date_eod_iso((string) $row['deadline']),
        'is_open' => $isOpen,
        'gamedate' => (string) $row['gamedate'],
    ];
}

function team_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['competitor.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT competitor_id, teamname, credits, favorite_team_id' . $updatedPart . '
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

function team_roster_with_autocreate(PDO $pdo, int $competitorId, int $gw, array $schema): ?array
{
    $updatedPart = ($schema['roster.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $fetchSql = 'SELECT competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain' . $updatedPart . '
                 FROM roster
                 WHERE competitor_id = :competitor_id AND gameweek = :gw
                 LIMIT 1';
    $fetch = $pdo->prepare($fetchSql);
    $fetch->execute([':competitor_id' => $competitorId, ':gw' => $gw]);
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

    $fetch->execute([':competitor_id' => $competitorId, ':gw' => $gw]);
    $row = $fetch->fetch();
    return $row ?: null;
}

function team_build_positions(PDO $pdo, int $leagueId, int $gw, array $roster, array $schema): array
{
    $playerIds = [];
    for ($i = 1; $i <= 8; $i++) {
        $playerIds[] = (int) $roster['player' . $i];
    }
    $playerIds = array_values(array_unique($playerIds));
    if (empty($playerIds)) {
        return [];
    }

    $playerMap = team_player_map($pdo, $playerIds);
    $priceMap = team_price_map($pdo, $playerIds, $gw, $schema);
    $statsMap = team_stats_map($pdo, $playerIds, $gw);
    $fixtureMap = team_fixture_map($pdo, $leagueId, $gw);

    $positions = [];
    for ($pos = 1; $pos <= 8; $pos++) {
        $pid = (int) $roster['player' . $pos];
        $p = $playerMap[$pid] ?? null;
        $teamId = $p !== null ? (int) $p['team_id'] : null;
        $fixture = $teamId !== null && isset($fixtureMap[$teamId]) ? $fixtureMap[$teamId] : null;

        $positions[] = [
            'pos' => $pos,
            'player' => [
                'player_id' => $pid,
                'name' => $p !== null ? (string) $p['playername'] : '',
            ],
            'team' => [
                'team_id' => $teamId !== null ? $teamId : 0,
                'short' => $p !== null ? (string) $p['team_short'] : '',
                'logo_url' => $p !== null ? (string) $p['team_logo'] : '',
            ],
            'price' => isset($priceMap[$pid]) ? (float) $priceMap[$pid] : 0.0,
            'stats' => [
                'avg_points' => isset($statsMap[$pid]) ? (float) $statsMap[$pid]['avg_points'] : 0.0,
                'form_points' => isset($statsMap[$pid]) ? (float) $statsMap[$pid]['form_points'] : 0.0,
                'weekly_points' => isset($statsMap[$pid]) ? (float) $statsMap[$pid]['weekly_points'] : 0.0,
            ],
            'next_fixture' => $fixture,
        ];
    }

    return $positions;
}

function team_player_map(PDO $pdo, array $playerIds): array
{
    $bind = [];
    $params = [];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }

    $sql = 'SELECT p.player_id, p.playername, p.team_id, t.short AS team_short, t.logo AS team_logo
            FROM player p
            LEFT JOIN team t ON t.team_id = p.team_id AND t.league_id = p.league_id
            WHERE p.player_id IN (' . implode(',', $bind) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = $row;
    }
    return $map;
}

function team_price_map(PDO $pdo, array $playerIds, int $gw, array $schema): array
{
    $bind = [];
    $params = [':gw' => $gw];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }

    $sql = 'SELECT pt.player_id, pt.price
            FROM playertrade pt
            INNER JOIN (
                SELECT player_id, MAX(gameweek) AS max_gw
                FROM playertrade
                WHERE player_id IN (' . implode(',', $bind) . ') AND gameweek <= :gw
                GROUP BY player_id
            ) x ON x.player_id = pt.player_id AND x.max_gw = pt.gameweek';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = (float) $row['price'];
    }
    return $map;
}

function team_stats_map(PDO $pdo, array $playerIds, int $gw): array
{
    $bind = [];
    $params = [':gw' => $gw];
    foreach ($playerIds as $idx => $pid) {
        $k = ':p' . $idx;
        $bind[] = $k;
        $params[$k] = $pid;
    }

    $sql = 'SELECT player_id, gameweek, SUM(points) AS gw_points
            FROM playerresult
            WHERE player_id IN (' . implode(',', $bind) . ') AND gameweek <= :gw
            GROUP BY player_id, gameweek';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $byPlayer = [];
    foreach ($rows as $row) {
        $pid = (int) $row['player_id'];
        if (!isset($byPlayer[$pid])) {
            $byPlayer[$pid] = [];
        }
        $byPlayer[$pid][] = [
            'gw' => (int) $row['gameweek'],
            'pts' => (float) $row['gw_points'],
        ];
    }

    $out = [];
    foreach ($playerIds as $pid) {
        $entries = $byPlayer[$pid] ?? [];
        $weekly = 0.0;
        $sum = 0.0;
        $count = 0;
        foreach ($entries as $entry) {
            $sum += $entry['pts'];
            $count++;
            if ($entry['gw'] === $gw) {
                $weekly = $entry['pts'];
            }
        }

        usort($entries, static function (array $a, array $b): int {
            return $b['gw'] <=> $a['gw'];
        });
        $form = 0.0;
        foreach (array_slice($entries, 0, 5) as $entry) {
            $form += $entry['pts'];
        }

        $out[$pid] = [
            'weekly_points' => $weekly,
            'avg_points' => $count > 0 ? ($sum / $count) : 0.0,
            'form_points' => $form,
        ];
    }
    return $out;
}

function team_fixture_map(PDO $pdo, int $leagueId, int $gw): array
{
    $stmt = $pdo->prepare(
        'SELECT m.hometeam, m.awayteam,
                th.team_id AS home_team_id, th.short AS home_short, th.logo AS home_logo,
                ta.team_id AS away_team_id, ta.short AS away_short, ta.logo AS away_logo
         FROM matches m
         LEFT JOIN team th ON th.team_id = m.hometeam AND th.league_id = m.league_id
         LEFT JOIN team ta ON ta.team_id = m.awayteam AND ta.league_id = m.league_id
         WHERE m.league_id = :league_id AND m.gameweek = :gw'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $homeId = (int) $row['hometeam'];
        $awayId = (int) $row['awayteam'];

        $map[$homeId] = [
            'gw' => $gw,
            'opponent' => [
                'team_id' => $awayId,
                'short' => (string) ($row['away_short'] ?? ''),
                'logo_url' => (string) ($row['away_logo'] ?? ''),
            ],
            'home_away' => 'H',
        ];

        $map[$awayId] = [
            'gw' => $gw,
            'opponent' => [
                'team_id' => $homeId,
                'short' => (string) ($row['home_short'] ?? ''),
                'logo_url' => (string) ($row['home_logo'] ?? ''),
            ],
            'home_away' => 'A',
        ];
    }
    return $map;
}

function team_transfers_used(PDO $pdo, int $competitorId, int $gw, array $schema): int
{
    $where = ($schema['transfers.normal'] ?? false) ? 'AND normal = 1' : '';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM transfers WHERE competitor_id = :competitor_id AND gameweek = :gw ' . $where
    );
    $stmt->execute([
        ':competitor_id' => $competitorId,
        ':gw' => $gw,
    ]);
    return (int) $stmt->fetchColumn();
}

function team_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    int $gw,
    array $competitor,
    array $roster,
    array $positions,
    int $transfersUsed
): array {
    $timestamps = [];

    if (!empty($roster['updated_at'])) {
        $ts = strtotime((string) $roster['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }
    if (!empty($competitor['updated_at'])) {
        $ts = strtotime((string) $competitor['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }

    if ($schema['transfers.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) FROM transfers WHERE competitor_id = :competitor_id AND gameweek = :gw'
        );
        $stmt->execute([
            ':competitor_id' => (int) $competitor['competitor_id'],
            ':gw' => $gw,
        ]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $playerIds = [];
    foreach ($positions as $position) {
        $playerIds[] = (int) $position['player']['player_id'];
    }
    $playerIds = array_values(array_unique($playerIds));

    if (!empty($playerIds) && ($schema['playertrade.updated_at'] ?? false)) {
        $bind = [];
        $params = [':gw' => $gw];
        foreach ($playerIds as $idx => $pid) {
            $k = ':p' . $idx;
            $bind[] = $k;
            $params[$k] = $pid;
        }
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) FROM playertrade
             WHERE player_id IN (' . implode(',', $bind) . ') AND gameweek <= :gw'
        );
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $rosterSig = [];
    for ($i = 1; $i <= 8; $i++) {
        $rosterSig[] = (int) $roster['player' . $i];
    }
    $marker = [
        'team-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'gw:' . $gw,
        'r:' . implode(',', $rosterSig) . '|cap:' . (int) $roster['captain'],
        'c:name:' . (string) $competitor['teamname'] . '|cr:' . (string) $competitor['credits'] . '|fav:' . (string) ($competitor['favorite_team_id'] ?? ''),
        't:' . $transfersUsed,
    ];

    $priceSig = [];
    foreach ($positions as $position) {
        $priceSig[] = (int) $position['player']['player_id'] . ':' . (string) $position['price'];
    }
    $marker[] = 'p:' . implode(',', $priceSig);

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    $etag = 'W/"team-u' . $profileId . '-l' . $leagueId . '-' . $gw . '-' . sha1(implode('|', $marker)) . '"';

    return [
        'etag' => $etag,
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function team_if_none_match_matches(string $etag): bool
{
    $header = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($header === '') {
        return false;
    }
    $parts = array_map('trim', explode(',', $header));
    foreach ($parts as $candidate) {
        if ($candidate === $etag) {
            return true;
        }
    }
    return false;
}

function team_date_eod_iso(string $date): string
{
    $ts = strtotime($date . ' 23:59:59 UTC');
    if ($ts === false) {
        return '1970-01-01T23:59:59Z';
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function team_authorization_header(): string
{
    $keys = ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            return trim((string) $_SERVER[$key]);
        }
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }

    return '';
}

function team_require_auth_profile_id(): int
{
    $header = team_authorization_header();
    if ($header === '') {
        team_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = team_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub)) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $profileId = (int) $sub;
    if ($profileId <= 0) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return $profileId;
}

function team_verify_jwt(string $token): array
{
    $secret = team_jwt_secret();
    if ($secret === '') {
        team_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) team_b64url_decode($h64), true);
    $payload = json_decode((string) team_b64url_decode($p64), true);
    $signature = team_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $signature === null) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $signature)) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        team_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function team_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function team_jwt_secret(): string
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
