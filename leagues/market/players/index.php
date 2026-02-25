<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        market_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = market_resolve_league_id();
    $q = market_query_q();
    $teamId = market_query_team_id();
    $sort = market_query_sort();
    $limit = market_query_limit();
    $offset = market_query_offset();
    $context = market_query_outgoing_context();

    $pdo = market_db();
    $profileId = market_require_auth_profile_id();
    $schema = market_schema_info($pdo);

    if (!market_league_exists($pdo, $leagueId)) {
        market_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = market_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        market_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $competitor = market_competitor($pdo, $profileId, $leagueId, $schema);
    if ($competitor === null) {
        market_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $roster = market_roster_with_autocreate($pdo, (int) $competitor['competitor_id'], $currentGw, $schema);
    if ($roster === null) {
        market_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }

    $rosterIds = market_roster_ids($roster);
    $rosterTeamMap = market_player_team_map($pdo, $rosterIds);
    $creditsBefore = (float) $competitor['credits'];

    $outgoingIds = [];
    $isContextual = (bool) $context['provided'];
    $availableCredits = $creditsBefore;
    $rosterAfterRemoval = $rosterIds;

    if ($isContextual) {
        $outgoingIds = market_validate_outgoing_context_ids((array) $context['ids'], $rosterIds);
        $outgoingPriceMap = market_price_map($pdo, $outgoingIds, $currentGw);
        $outgoingTotal = 0.0;
        foreach ($outgoingIds as $pid) {
            $outgoingTotal += (float) ($outgoingPriceMap[$pid] ?? 0.0);
        }
        $availableCredits = round($creditsBefore + $outgoingTotal, 1);
        $rosterAfterRemoval = array_values(array_diff($rosterIds, $outgoingIds));
    }

    $rosterAfterTeamCounts = [];
    foreach ($rosterAfterRemoval as $pid) {
        if (!isset($rosterTeamMap[$pid])) {
            continue;
        }
        $tid = (int) $rosterTeamMap[$pid];
        if (!isset($rosterAfterTeamCounts[$tid])) {
            $rosterAfterTeamCounts[$tid] = 0;
        }
        $rosterAfterTeamCounts[$tid]++;
    }

    $total = market_total_count($pdo, $leagueId, $q, $teamId);

    $rows = [];
    $statsMap = [];
    if ($sort === 'price_asc' || $sort === 'price_desc') {
        $rows = market_player_rows_page_by_price($pdo, $leagueId, $currentGw, $q, $teamId, $sort, $limit, $offset);
        $pageIds = [];
        foreach ($rows as $row) {
            $pageIds[] = (int) $row['player_id'];
        }
        $statsMap = market_stats_map($pdo, array_values(array_unique($pageIds)), $currentGw);
    } else {
        $allRows = market_player_rows_all($pdo, $leagueId, $currentGw, $q, $teamId);
        $allIds = [];
        foreach ($allRows as $row) {
            $allIds[] = (int) $row['player_id'];
        }
        $statsMap = market_stats_map($pdo, array_values(array_unique($allIds)), $currentGw);

        foreach ($allRows as &$row) {
            $pid = (int) $row['player_id'];
            $row['avg_points'] = (float) (($statsMap[$pid]['avg_points'] ?? 0.0));
            $row['form_points'] = (float) (($statsMap[$pid]['form_points'] ?? 0.0));
        }
        unset($row);

        if ($sort === 'avg_points_desc') {
            usort($allRows, static function (array $a, array $b): int {
                $cmp = ((float) $b['avg_points']) <=> ((float) $a['avg_points']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int) $a['player_id']) <=> ((int) $b['player_id']);
            });
        } else {
            usort($allRows, static function (array $a, array $b): int {
                $cmp = ((float) $b['form_points']) <=> ((float) $a['form_points']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int) $a['player_id']) <=> ((int) $b['player_id']);
            });
        }

        $rows = array_slice($allRows, $offset, $limit);
    }

    $items = [];
    foreach ($rows as $row) {
        $pid = (int) $row['player_id'];
        $playerTeamId = (int) ($row['team_id'] ?? 0);
        $price = isset($row['price']) ? (float) $row['price'] : 0.0;

        $disabledReasons = [];
        if (in_array($pid, $rosterAfterRemoval, true)) {
            $disabledReasons[] = 'ALREADY_OWNED';
        }
        if ($price > $availableCredits) {
            $disabledReasons[] = 'BUDGET_INSUFFICIENT';
        }
        if (($rosterAfterTeamCounts[$playerTeamId] ?? 0) >= 2) {
            $disabledReasons[] = 'MAX_PLAYERS_FROM_TEAM';
        }

        $items[] = [
            'player_id' => $pid,
            'name' => (string) ($row['playername'] ?? ''),
            'team' => [
                'team_id' => $playerTeamId,
                'short' => (string) ($row['team_short'] ?? ''),
                'name' => (string) ($row['team_name'] ?? ''),
                'logo_url' => (string) ($row['team_logo'] ?? ''),
            ],
            'price' => $price,
            'stats' => [
                'avg_points' => isset($statsMap[$pid]) ? (float) $statsMap[$pid]['avg_points'] : 0.0,
                'form_points' => isset($statsMap[$pid]) ? (float) $statsMap[$pid]['form_points'] : 0.0,
            ],
            'availability' => [
                'can_select' => empty($disabledReasons),
                'disabled_reasons' => $disabledReasons,
            ],
        ];
    }

    $etagBuild = market_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        $currentGw,
        $q,
        $teamId,
        $sort,
        $limit,
        $offset,
        $isContextual,
        $outgoingIds,
        $creditsBefore,
        $availableCredits,
        $rosterIds,
        $total,
        $items
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (market_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $data = [
        'league_id' => $leagueId,
        'gw' => $currentGw,
        'items' => $items,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ];
    if ($isContextual) {
        $data['context'] = [
            'outgoing_player_ids' => $outgoingIds,
            'available_credits' => $availableCredits,
        ];
    }

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    market_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function market_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/market/players/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        market_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function market_query_q(): ?string
{
    if (!array_key_exists('q', $_GET)) {
        return null;
    }
    $q = trim((string) $_GET['q']);
    return $q === '' ? null : $q;
}

function market_query_team_id(): ?int
{
    if (!array_key_exists('team_id', $_GET)) {
        return null;
    }
    $raw = trim((string) $_GET['team_id']);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        market_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function market_query_sort(): string
{
    $allowed = ['price_asc', 'price_desc', 'avg_points_desc', 'form_points_desc'];
    if (!array_key_exists('sort', $_GET)) {
        return 'price_desc';
    }
    $sort = trim((string) $_GET['sort']);
    if (!in_array($sort, $allowed, true)) {
        market_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $sort;
}

function market_query_limit(): int
{
    if (!array_key_exists('limit', $_GET)) {
        return 50;
    }
    $raw = trim((string) $_GET['limit']);
    if ($raw === '' || !ctype_digit($raw)) {
        market_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    $n = (int) $raw;
    if ($n <= 0 || $n > 200) {
        market_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return $n;
}

function market_query_offset(): int
{
    if (!array_key_exists('offset', $_GET)) {
        return 0;
    }
    $raw = trim((string) $_GET['offset']);
    if ($raw === '' || !ctype_digit($raw)) {
        market_error(400, 'BAD_REQUEST', 'Invalid query params.');
    }
    return (int) $raw;
}

function market_query_outgoing_context(): array
{
    if (!array_key_exists('outgoing_player_ids', $_GET)) {
        return ['provided' => false, 'ids' => []];
    }

    $rawValue = $_GET['outgoing_player_ids'];
    if (is_array($rawValue)) {
        $rawList = $rawValue;
    } elseif (is_string($rawValue)) {
        $rawList = [$rawValue];
    } else {
        market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
    }

    $ids = [];
    foreach ($rawList as $raw) {
        if (is_int($raw)) {
            $n = $raw;
        } elseif (is_string($raw) && ctype_digit(trim($raw))) {
            $n = (int) trim($raw);
        } else {
            market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
        }
        if ($n <= 0) {
            market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
        }
        $ids[] = $n;
    }

    return ['provided' => true, 'ids' => $ids];
}

function market_validate_outgoing_context_ids(array $ids, array $rosterIds): array
{
    if (count($ids) < 1 || count($ids) > 2) {
        market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
    }
    if (count($ids) !== count(array_unique($ids))) {
        market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
    }
    foreach ($ids as $pid) {
        if (!in_array((int) $pid, $rosterIds, true)) {
            market_error(422, 'MARKET_CONTEXT_INVALID', 'Invalid market context.');
        }
    }
    return array_values(array_map('intval', $ids));
}

function market_db(): PDO
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

function market_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function market_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","competitor","roster","player","team","playertrade","playerresult")'
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

function market_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function market_current_gameweek(PDO $pdo, int $leagueId): ?array
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

function market_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
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

function market_roster_with_autocreate(PDO $pdo, int $competitorId, int $gw, array $schema): ?array
{
    $updatedPart = ($schema['roster.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $fetch = $pdo->prepare(
        'SELECT competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain' . $updatedPart . '
         FROM roster
         WHERE competitor_id = :competitor_id AND gameweek = :gw
         LIMIT 1'
    );
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

function market_roster_ids(array $roster): array
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

function market_player_team_map(PDO $pdo, array $playerIds): array
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

function market_filters_where(array &$params, int $leagueId, ?string $q, ?int $teamId): string
{
    $params = [':league_id' => $leagueId];
    $where = 'WHERE p.league_id = :league_id';
    if ($q !== null) {
        $where .= ' AND p.playername LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }
    if ($teamId !== null) {
        $where .= ' AND p.team_id = :team_id';
        $params[':team_id'] = $teamId;
    }
    return $where;
}

function market_total_count(PDO $pdo, int $leagueId, ?string $q, ?int $teamId): int
{
    $params = [];
    $where = market_filters_where($params, $leagueId, $q, $teamId);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM player p ' . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function market_player_rows_page_by_price(PDO $pdo, int $leagueId, int $gw, ?string $q, ?int $teamId, string $sort, int $limit, int $offset): array
{
    $params = [':gw' => $gw];
    $where = market_filters_where($params, $leagueId, $q, $teamId);
    $params[':gw'] = $gw;

    $orderDir = $sort === 'price_asc' ? 'ASC' : 'DESC';
    $sql = 'SELECT
                p.player_id,
                p.playername,
                p.team_id,
                t.short AS team_short,
                t.name AS team_name,
                t.logo AS team_logo,
                COALESCE(pr.price, 0) AS price
            FROM player p
            LEFT JOIN team t ON t.team_id = p.team_id AND t.league_id = p.league_id
            LEFT JOIN (
                SELECT pt.player_id, pt.price
                FROM playertrade pt
                INNER JOIN (
                    SELECT player_id, MAX(gameweek) AS max_gw
                    FROM playertrade
                    WHERE gameweek <= :gw
                    GROUP BY player_id
                ) latest ON latest.player_id = pt.player_id AND latest.max_gw = pt.gameweek
            ) pr ON pr.player_id = p.player_id
            ' . $where . '
            ORDER BY price ' . $orderDir . ', p.player_id ASC
            LIMIT ' . $limit . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function market_player_rows_all(PDO $pdo, int $leagueId, int $gw, ?string $q, ?int $teamId): array
{
    $params = [':gw' => $gw];
    $where = market_filters_where($params, $leagueId, $q, $teamId);
    $params[':gw'] = $gw;

    $sql = 'SELECT
                p.player_id,
                p.playername,
                p.team_id,
                t.short AS team_short,
                t.name AS team_name,
                t.logo AS team_logo,
                COALESCE(pr.price, 0) AS price
            FROM player p
            LEFT JOIN team t ON t.team_id = p.team_id AND t.league_id = p.league_id
            LEFT JOIN (
                SELECT pt.player_id, pt.price
                FROM playertrade pt
                INNER JOIN (
                    SELECT player_id, MAX(gameweek) AS max_gw
                    FROM playertrade
                    WHERE gameweek <= :gw
                    GROUP BY player_id
                ) latest ON latest.player_id = pt.player_id AND latest.max_gw = pt.gameweek
            ) pr ON pr.player_id = p.player_id
            ' . $where . '
            ORDER BY p.player_id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function market_price_map(PDO $pdo, array $playerIds, int $gw): array
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
         ) latest ON latest.player_id = pt.player_id AND latest.max_gw = pt.gameweek'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['player_id']] = (float) $row['price'];
    }
    return $map;
}

function market_stats_map(PDO $pdo, array $playerIds, int $gw): array
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
        'SELECT player_id, gameweek, SUM(points) AS gw_points
         FROM playerresult
         WHERE player_id IN (' . implode(',', $bind) . ') AND gameweek <= :gw
         GROUP BY player_id, gameweek'
    );
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
        $sum = 0.0;
        $count = 0;
        foreach ($entries as $entry) {
            $sum += $entry['pts'];
            $count++;
        }

        usort($entries, static function (array $a, array $b): int {
            return $b['gw'] <=> $a['gw'];
        });
        $form = 0.0;
        foreach (array_slice($entries, 0, 5) as $entry) {
            $form += $entry['pts'];
        }

        $out[$pid] = [
            'avg_points' => $count > 0 ? ($sum / $count) : 0.0,
            'form_points' => $form,
        ];
    }
    return $out;
}

function market_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    int $gw,
    ?string $q,
    ?int $teamId,
    string $sort,
    int $limit,
    int $offset,
    bool $isContextual,
    array $outgoingIds,
    float $creditsBefore,
    float $availableCredits,
    array $rosterIds,
    int $total,
    array $items
): array {
    $timestamps = [];

    if (($schema['playertrade.updated_at'] ?? false)) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM playertrade');
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if (($schema['playerresult.updated_at'] ?? false)) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM playerresult');
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if (($schema['competitor.updated_at'] ?? false)) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM competitor WHERE profile_id = :profile_id AND league_id = :league_id');
        $stmt->execute([
            ':profile_id' => $profileId,
            ':league_id' => $leagueId,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if (($schema['roster.updated_at'] ?? false)) {
        $stmt = $pdo->prepare(
            'SELECT MAX(r.updated_at)
             FROM roster r
             INNER JOIN competitor c ON c.competitor_id = r.competitor_id
             WHERE c.profile_id = :profile_id AND c.league_id = :league_id'
        );
        $stmt->execute([
            ':profile_id' => $profileId,
            ':league_id' => $leagueId,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $itemSig = [];
    foreach ($items as $item) {
        $itemSig[] = (int) $item['player_id']
            . ':' . (string) $item['price']
            . ':' . (string) $item['stats']['avg_points']
            . ':' . (string) $item['stats']['form_points']
            . ':' . ($item['availability']['can_select'] ? '1' : '0')
            . ':' . implode(',', $item['availability']['disabled_reasons']);
    }

    $marker = [
        'market-v1',
        'l:' . $leagueId,
        'gw:' . $gw,
        'q:' . ($q ?? ''),
        'team:' . ($teamId ?? 0),
        'sort:' . $sort,
        'p:' . $limit . ':' . $offset,
        'ctx:' . ($isContextual ? '1' : '0'),
        'out:' . implode(',', $outgoingIds),
        'cr:' . $creditsBefore . ':' . $availableCredits,
        'r:' . implode(',', $rosterIds),
        'tot:' . $total,
        'items:' . implode('|', $itemSig),
    ];

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    $etagUser = $isContextual ? $profileId : 0;
    $etag = 'W/"market-l' . $leagueId . '-gw' . $gw . '-u' . $etagUser . '-' . sha1(implode('|', $marker)) . '"';

    return [
        'etag' => $etag,
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function market_if_none_match_matches(string $etag): bool
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

function market_authorization_header(): string
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

function market_require_auth_profile_id(): int
{
    $header = market_authorization_header();
    if ($header === '') {
        market_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = market_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function market_verify_jwt(string $token): array
{
    $secret = market_jwt_secret();
    if ($secret === '') {
        market_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) market_b64url_decode($h64), true);
    $payload = json_decode((string) market_b64url_decode($p64), true);
    $sig = market_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        market_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function market_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function market_jwt_secret(): string
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
