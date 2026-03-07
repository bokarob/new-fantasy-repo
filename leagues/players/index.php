<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        player_detail_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = player_detail_resolve_league_id();
    $playerId = player_detail_resolve_player_id();

    $pdo = player_detail_db();
    $profileId = player_detail_require_auth_profile_id();
    $schema = player_detail_schema_info($pdo);

    if (!player_detail_league_exists($pdo, $leagueId)) {
        player_detail_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = player_detail_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        player_detail_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $competitor = player_detail_competitor($pdo, $profileId, $leagueId, $schema);
    if ($competitor === null) {
        player_detail_error(409, 'NO_COMPETITOR', 'User has no team in this league.');
    }

    $roster = player_detail_roster_with_autocreate($pdo, (int) $competitor['competitor_id'], $currentGw, $schema);
    if ($roster === null) {
        player_detail_error(404, 'ROSTER_NOT_FOUND', 'Roster not found.');
    }

    $player = player_detail_player_row($pdo, $leagueId, $playerId, $schema);
    if ($player === null) {
        player_detail_error(404, 'PLAYER_NOT_FOUND', 'Player not found.');
    }

    $rosterIds = player_detail_roster_ids($roster);
    $ownership = player_detail_ownership($roster, $playerId);
    $price = player_detail_price($pdo, $playerId, $currentGw);
    $stats = player_detail_stats($pdo, $playerId, $currentGw);
    $selectionPercent = player_detail_selection_percent($pdo, $leagueId, $playerId, $currentGw);
    $transfersUsed = player_detail_transfers_used($pdo, (int) $competitor['competitor_id'], $currentGw, $schema);
    $isFreeTransferGw = player_detail_is_free_transfer_gw($pdo, $leagueId, $currentGw, $schema);
    $actions = player_detail_actions(
        $ownership,
        (int) $player['team_id'],
        $price['current'],
        (float) $competitor['credits'],
        $rosterIds,
        $roster,
        $gw['is_open'],
        $transfersUsed,
        $isFreeTransferGw,
        player_detail_player_team_map($pdo, $rosterIds)
    );

    $etagBuild = player_detail_etag_and_last_updated(
        $pdo,
        $schema,
        $profileId,
        $leagueId,
        $currentGw,
        $playerId,
        $player,
        $ownership,
        $price,
        $stats,
        $selectionPercent,
        $actions,
        $competitor,
        $roster,
        $transfersUsed
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (player_detail_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'league_id' => $leagueId,
            'gw' => $currentGw,
            'player' => [
                'player_id' => $playerId,
                'name' => (string) ($player['playername'] ?? ''),
                'team' => [
                    'team_id' => (int) ($player['team_id'] ?? 0),
                    'short' => (string) ($player['team_short'] ?? ''),
                    'name' => (string) ($player['team_name'] ?? ''),
                    'logo_url' => (string) ($player['team_logo'] ?? ''),
                ],
            ],
            'ownership' => $ownership,
            'price' => $price,
            'base_stats' => [
                'avg_points' => $stats['avg_points'],
                'form_points' => $stats['form_points'],
                'selection_percent' => $selectionPercent,
            ],
            'actions' => $actions,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    player_detail_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function player_detail_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/players/(\d+)/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        player_detail_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function player_detail_resolve_player_id(): int
{
    $raw = isset($_GET['player_id']) ? (string) $_GET['player_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/players/(\d+)/?$#', $path, $m)) {
            $raw = $m[2];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        player_detail_error(400, 'BAD_REQUEST', 'Invalid player_id.');
    }
    return (int) $raw;
}

function player_detail_db(): PDO
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

function player_detail_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function player_detail_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","competitor","roster","player","team","playertrade","playerresult","transfers")'
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

function player_detail_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function player_detail_current_gameweek(PDO $pdo, int $leagueId): ?array
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

function player_detail_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['competitor.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT competitor_id, credits' . $updatedPart . '
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

function player_detail_roster_with_autocreate(PDO $pdo, int $competitorId, int $gw, array $schema): ?array
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

function player_detail_player_row(PDO $pdo, int $leagueId, int $playerId, array $schema): ?array
{
    $teamLogoExpr = ($schema['team.logo'] ?? false) ? 'COALESCE(t.logo, \'\')' : '\'\''; 
    $stmt = $pdo->prepare(
        'SELECT
            p.player_id,
            p.playername,
            p.team_id,
            t.short AS team_short,
            t.name AS team_name,
            ' . $teamLogoExpr . ' AS team_logo
         FROM player p
         LEFT JOIN team t ON t.team_id = p.team_id AND t.league_id = p.league_id
         WHERE p.league_id = :league_id
           AND p.player_id = :player_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':player_id' => $playerId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function player_detail_roster_ids(array $roster): array
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

function player_detail_ownership(array $roster, int $playerId): array
{
    for ($pos = 1; $pos <= 8; $pos++) {
        if ((int) $roster['player' . $pos] === $playerId) {
            return [
                'owned_by_you' => true,
                'roster_position' => $pos,
            ];
        }
    }

    return [
        'owned_by_you' => false,
        'roster_position' => null,
    ];
}

function player_detail_price(PDO $pdo, int $playerId, int $gw): array
{
    $currentStmt = $pdo->prepare(
        'SELECT price
         FROM playertrade
         WHERE player_id = :player_id AND gameweek <= :gw
         ORDER BY gameweek DESC
         LIMIT 1'
    );
    $currentStmt->execute([
        ':player_id' => $playerId,
        ':gw' => $gw,
    ]);
    $current = $currentStmt->fetchColumn();
    $currentPrice = $current !== false && $current !== null ? (float) $current : 0.0;

    $prevStmt = $pdo->prepare(
        'SELECT price
         FROM playertrade
         WHERE player_id = :player_id AND gameweek < :gw
         ORDER BY gameweek DESC
         LIMIT 1'
    );
    $prevStmt->execute([
        ':player_id' => $playerId,
        ':gw' => $gw,
    ]);
    $prev = $prevStmt->fetchColumn();
    $previousPrice = $prev !== false && $prev !== null ? (float) $prev : $currentPrice;

    return [
        'current' => $currentPrice,
        'previous' => $previousPrice,
    ];
}

function player_detail_stats(PDO $pdo, int $playerId, int $gw): array
{
    $stmt = $pdo->prepare(
        'SELECT gameweek, SUM(points) AS gw_points
         FROM playerresult
         WHERE player_id = :player_id AND gameweek <= :gw
         GROUP BY gameweek'
    );
    $stmt->execute([
        ':player_id' => $playerId,
        ':gw' => $gw,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $sum = 0.0;
    $count = 0;
    $entries = [];
    foreach ($rows as $row) {
        $points = (float) ($row['gw_points'] ?? 0.0);
        $sum += $points;
        $count++;
        $entries[] = [
            'gw' => (int) ($row['gameweek'] ?? 0),
            'pts' => $points,
        ];
    }

    usort($entries, static function (array $a, array $b): int {
        return $b['gw'] <=> $a['gw'];
    });

    $form = 0.0;
    foreach (array_slice($entries, 0, 5) as $entry) {
        $form += (float) $entry['pts'];
    }

    return [
        'avg_points' => $count > 0 ? ($sum / $count) : 0.0,
        'form_points' => $form,
    ];
}

function player_detail_selection_percent(PDO $pdo, int $leagueId, int $playerId, int $gw): float
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) 
         FROM competitor
         WHERE league_id = :league_id'
    );
    $stmt->execute([':league_id' => $leagueId]);
    $totalCompetitors = (int) $stmt->fetchColumn();
    if ($totalCompetitors <= 0) {
        return 0.0;
    }

    $ownedStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM (
            SELECT r.competitor_id, r.player1, r.player2, r.player3, r.player4, r.player5, r.player6, r.player7, r.player8
            FROM roster r
            INNER JOIN (
                SELECT c.competitor_id, MAX(r2.gameweek) AS max_gw
                FROM competitor c
                INNER JOIN roster r2 ON r2.competitor_id = c.competitor_id
                WHERE c.league_id = :league_id AND r2.gameweek <= :gw
                GROUP BY c.competitor_id
            ) latest ON latest.competitor_id = r.competitor_id AND latest.max_gw = r.gameweek
         ) roster_latest
         WHERE :player_id IN (player1, player2, player3, player4, player5, player6, player7, player8)'
    );
    $ownedStmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
        ':player_id' => $playerId,
    ]);
    $ownedCount = (int) $ownedStmt->fetchColumn();

    return round(($ownedCount / $totalCompetitors) * 100, 1);
}

function player_detail_transfers_used(PDO $pdo, int $competitorId, int $gw, array $schema): int
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

function player_detail_is_free_transfer_gw(PDO $pdo, int $leagueId, int $gw, array $schema): bool
{
    if (!($schema['leagues.free_transfer_gw'] ?? false)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT free_transfer_gw FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null && (int) $value === $gw;
}

function player_detail_player_team_map(PDO $pdo, array $playerIds): array
{
    if (empty($playerIds)) {
        return [];
    }

    $bind = [];
    $params = [];
    foreach ($playerIds as $idx => $pid) {
        $key = ':p' . $idx;
        $bind[] = $key;
        $params[$key] = $pid;
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

function player_detail_actions(
    array $ownership,
    int $playerTeamId,
    float $currentPrice,
    float $credits,
    array $rosterIds,
    array $roster,
    bool $gwOpen,
    int $transfersUsed,
    bool $isFreeTransferGw,
    array $rosterTeamMap
): array {
    $disabledReasons = [];
    $transferLimitReached = !$isFreeTransferGw && $transfersUsed >= 2;

    if ($ownership['owned_by_you']) {
        if (!$gwOpen) {
            $disabledReasons[] = 'GW_CLOSED';
        }
        if ($transferLimitReached) {
            $disabledReasons[] = 'TRANSFER_LIMIT_REACHED';
        }

        $rosterPosition = $ownership['roster_position'];
        $canCaptain = $gwOpen
            && $rosterPosition !== null
            && $rosterPosition <= 6
            && (int) $roster['captain'] !== (int) player_detail_owned_player_id($roster, $rosterPosition);

        $canTransferOwned = empty($disabledReasons);

        return [
            'can_buy' => false,
            'can_sell' => $canTransferOwned,
            'can_replace' => $canTransferOwned,
            'can_captain' => $canCaptain,
            'disabled_reasons' => array_values(array_unique($disabledReasons)),
        ];
    }

    if (!$gwOpen) {
        $disabledReasons[] = 'GW_CLOSED';
    }
    if ($currentPrice > $credits) {
        $disabledReasons[] = 'BUDGET_INSUFFICIENT';
    }

    $teamCount = 0;
    foreach ($rosterIds as $pid) {
        if (($rosterTeamMap[$pid] ?? null) === $playerTeamId) {
            $teamCount++;
        }
    }
    if ($teamCount >= 2) {
        $disabledReasons[] = 'MAX_PLAYERS_FROM_TEAM';
    }
    if ($transferLimitReached) {
        $disabledReasons[] = 'TRANSFER_LIMIT_REACHED';
    }

    $canAcquire = empty($disabledReasons);

    return [
        'can_buy' => $canAcquire,
        'can_sell' => false,
        'can_replace' => $canAcquire,
        'can_captain' => false,
        'disabled_reasons' => array_values(array_unique($disabledReasons)),
    ];
}

function player_detail_owned_player_id(array $roster, int $position): int
{
    return (int) $roster['player' . $position];
}

function player_detail_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $profileId,
    int $leagueId,
    int $gw,
    int $playerId,
    array $player,
    array $ownership,
    array $price,
    array $stats,
    float $selectionPercent,
    array $actions,
    array $competitor,
    array $roster,
    int $transfersUsed
): array {
    $timestamps = [];

    if (!empty($competitor['updated_at'])) {
        $ts = strtotime((string) $competitor['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }
    if (!empty($roster['updated_at'])) {
        $ts = strtotime((string) $roster['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }

    if ($schema['playertrade.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) FROM playertrade WHERE player_id = :player_id AND gameweek <= :gw'
        );
        $stmt->execute([
            ':player_id' => $playerId,
            ':gw' => $gw,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if ($schema['playerresult.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) FROM playerresult WHERE player_id = :player_id AND gameweek <= :gw'
        );
        $stmt->execute([
            ':player_id' => $playerId,
            ':gw' => $gw,
        ]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
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
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $marker = [
        'player-detail-v1',
        'u:' . $profileId,
        'l:' . $leagueId,
        'gw:' . $gw,
        'p:' . $playerId,
        'n:' . (string) ($player['playername'] ?? ''),
        't:' . (int) ($player['team_id'] ?? 0),
        'own:' . ($ownership['owned_by_you'] ? '1' : '0') . ':' . (($ownership['roster_position'] ?? 0) ?: 0),
        'price:' . $price['current'] . ':' . $price['previous'],
        'stats:' . $stats['avg_points'] . ':' . $stats['form_points'] . ':' . $selectionPercent,
        'actions:' . ($actions['can_buy'] ? '1' : '0')
            . ($actions['can_sell'] ? '1' : '0')
            . ($actions['can_replace'] ? '1' : '0')
            . ($actions['can_captain'] ? '1' : '0')
            . ':' . implode(',', $actions['disabled_reasons']),
        'credits:' . (string) ($competitor['credits'] ?? '0'),
        'captain:' . (int) ($roster['captain'] ?? 0),
        'transfers:' . $transfersUsed,
    ];

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();

    return [
        'etag' => 'W/"player-l' . $leagueId . '-p' . $playerId . '-u' . $profileId . '-gw' . $gw . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function player_detail_if_none_match_matches(string $etag): bool
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

function player_detail_authorization_header(): string
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

function player_detail_require_auth_profile_id(): int
{
    $header = player_detail_authorization_header();
    if ($header === '') {
        player_detail_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = player_detail_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function player_detail_verify_jwt(string $token): array
{
    $secret = player_detail_jwt_secret();
    if ($secret === '') {
        player_detail_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) player_detail_b64url_decode($h64), true);
    $payload = json_decode((string) player_detail_b64url_decode($p64), true);
    $sig = player_detail_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        player_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function player_detail_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function player_detail_jwt_secret(): string
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
