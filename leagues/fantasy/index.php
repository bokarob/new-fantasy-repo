<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        fantasy_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = fantasy_resolve_league_id();
    $pdo = fantasy_db();
    $profileId = fantasy_require_auth_profile_id();
    $schema = fantasy_schema_info($pdo);

    if (!fantasy_league_exists($pdo, $leagueId)) {
        fantasy_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = fantasy_current_gameweek($pdo, $leagueId);
    if ($gw === null) {
        fantasy_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    if (!fantasy_rankings_available($pdo, $leagueId, $currentGw)) {
        fantasy_error(409, 'RANKING_NOT_AVAILABLE', 'Rankings are not available yet.');
    }

    $yourCompetitor = fantasy_competitor($pdo, $profileId, $leagueId, $schema);
    $etagBuild = fantasy_etag_and_last_updated($pdo, $schema, $leagueId, $currentGw, $yourCompetitor);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (fantasy_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $overallItems = fantasy_overall_items($pdo, $leagueId, $currentGw);
    $overallYou = null;
    if ($yourCompetitor !== null) {
        $overallYou = fantasy_overall_you($overallItems, (int) $yourCompetitor['competitor_id']);
    }

    $fanLeague = fantasy_fan_league($pdo, $leagueId, $currentGw, $yourCompetitor);
    $privateLeagues = fantasy_private_leagues($pdo, $schema, $leagueId, $yourCompetitor);

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => $leagueId,
            'current_gw' => $currentGw,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'gameweek' => [
                'gw' => $currentGw,
                'has_postponed_matches' => fantasy_has_postponed_matches($pdo, $schema, $leagueId, $currentGw),
                'last_update_at' => $etagBuild['last_updated'],
            ],
            'overall' => [
                'items' => $overallItems,
                'you' => $overallYou,
            ],
            'fan_league' => $fanLeague,
            'private_leagues' => [
                'items' => $privateLeagues,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    fantasy_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function fantasy_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/fantasy/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        fantasy_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }
    return (int) $raw;
}

function fantasy_db(): PDO
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

function fantasy_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function fantasy_schema_info(PDO $pdo): array
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
           AND table_name IN ("gameweeks","matches","competitor","profile","teamranking","teamresult","privateleague","privateleaguemembers","team")'
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

function fantasy_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function fantasy_current_gameweek(PDO $pdo, int $leagueId): ?array
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

function fantasy_rankings_available(PDO $pdo, int $leagueId, int $gw): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM teamranking tr
         INNER JOIN competitor c ON c.competitor_id = tr.competitor_id
         WHERE c.league_id = :league_id
           AND tr.gameweek = :gw'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
    ]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function fantasy_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
{
    $updatedPart = ($schema['competitor.updated_at'] ?? false) ? ', updated_at' : ', NULL AS updated_at';
    $stmt = $pdo->prepare(
        'SELECT competitor_id, favorite_team_id' . $updatedPart . '
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

function fantasy_overall_items(PDO $pdo, int $leagueId, int $gw): array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.competitor_id,
            c.teamname,
            p.alias,
            tr.`rank` AS current_rank,
            COALESCE(pr.`rank`, tr.`rank`) AS previous_rank,
            COALESCE(w.weekly_points, 0) AS weekly_points,
            COALESCE(t.total_points, 0) AS total_points
         FROM competitor c
         INNER JOIN profile p ON p.profile_id = c.profile_id
         INNER JOIN teamranking tr ON tr.competitor_id = c.competitor_id AND tr.gameweek = :gw
         LEFT JOIN teamranking pr ON pr.competitor_id = c.competitor_id AND pr.gameweek = :prev_gw
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS weekly_points
            FROM teamresult
            WHERE gameweek = :gw_weekly
            GROUP BY competitor_id
         ) w ON w.competitor_id = c.competitor_id
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS total_points
            FROM teamresult
            WHERE gameweek <= :gw_total
            GROUP BY competitor_id
         ) t ON t.competitor_id = c.competitor_id
         WHERE c.league_id = :league_id
         ORDER BY tr.`rank` ASC, c.teamname ASC, c.competitor_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
        ':prev_gw' => $gw - 1,
        ':gw_weekly' => $gw,
        ':gw_total' => $gw,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $currentRank = (int) $row['current_rank'];
        $previousRank = (int) $row['previous_rank'];
        $items[] = [
            'rank' => $currentRank,
            'previous_rank' => $previousRank,
            'rank_change' => $previousRank - $currentRank,
            'competitor_id' => (int) $row['competitor_id'],
            'teamname' => (string) $row['teamname'],
            'alias' => (string) $row['alias'],
            'total_points' => (float) $row['total_points'],
            'weekly_points' => (float) $row['weekly_points'],
        ];
    }

    return $items;
}

function fantasy_overall_you(array $overallItems, int $competitorId): ?array
{
    foreach ($overallItems as $item) {
        if ((int) $item['competitor_id'] === $competitorId) {
            return [
                'competitor_id' => $competitorId,
                'rank' => (int) $item['rank'],
            ];
        }
    }
    return null;
}

function fantasy_fan_league(PDO $pdo, int $leagueId, int $gw, ?array $yourCompetitor): array
{
    if ($yourCompetitor === null || $yourCompetitor['favorite_team_id'] === null) {
        return [
            'enabled' => false,
            'favorite_team_id' => null,
            'favorite_team' => null,
            'items' => [],
        ];
    }

    $favoriteTeamId = (int) $yourCompetitor['favorite_team_id'];
    $favoriteTeam = fantasy_favorite_team($pdo, $leagueId, $favoriteTeamId);

    $stmt = $pdo->prepare(
        'SELECT
            c.competitor_id,
            c.teamname,
            p.alias,
            COALESCE(w.weekly_points, 0) AS weekly_points,
            COALESCE(tc.total_points, 0) AS total_points,
            COALESCE(tp.total_points, 0) AS previous_total_points
         FROM competitor c
         INNER JOIN profile p ON p.profile_id = c.profile_id
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS weekly_points
            FROM teamresult
            WHERE gameweek = :gw_weekly
            GROUP BY competitor_id
         ) w ON w.competitor_id = c.competitor_id
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS total_points
            FROM teamresult
            WHERE gameweek <= :gw_current
            GROUP BY competitor_id
         ) tc ON tc.competitor_id = c.competitor_id
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS total_points
            FROM teamresult
            WHERE gameweek <= :gw_prev
            GROUP BY competitor_id
         ) tp ON tp.competitor_id = c.competitor_id
         WHERE c.league_id = :league_id
           AND c.favorite_team_id = :favorite_team_id'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':favorite_team_id' => $favoriteTeamId,
        ':gw_weekly' => $gw,
        ':gw_current' => $gw,
        ':gw_prev' => $gw - 1,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    usort($rows, static function (array $a, array $b): int {
        $cmp = ((float) $b['total_points']) <=> ((float) $a['total_points']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) $a['competitor_id']) <=> ((int) $b['competitor_id']);
    });
    $currentRankMap = fantasy_rank_map_from_sorted_rows($rows);

    usort($rows, static function (array $a, array $b): int {
        $cmp = ((float) $b['previous_total_points']) <=> ((float) $a['previous_total_points']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) $a['competitor_id']) <=> ((int) $b['competitor_id']);
    });
    $previousRankMap = fantasy_rank_map_from_sorted_rows($rows);

    usort($rows, static function (array $a, array $b): int {
        $cmp = ((int) ($currentRankMap[(int) $a['competitor_id']] ?? PHP_INT_MAX))
            <=> ((int) ($currentRankMap[(int) $b['competitor_id']] ?? PHP_INT_MAX));
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((int) $a['competitor_id']) <=> ((int) $b['competitor_id']);
    });

    $items = [];
    foreach ($rows as $row) {
        $competitorId = (int) $row['competitor_id'];
        $currentRank = (int) ($currentRankMap[$competitorId] ?? 0);
        $previousRank = (int) ($previousRankMap[$competitorId] ?? $currentRank);
        $items[] = [
            'rank' => $currentRank,
            'previous_rank' => $previousRank,
            'rank_change' => $previousRank - $currentRank,
            'competitor_id' => $competitorId,
            'teamname' => (string) $row['teamname'],
            'alias' => (string) $row['alias'],
            'total_points' => (float) $row['total_points'],
            'weekly_points' => (float) $row['weekly_points'],
        ];
    }

    return [
        'enabled' => true,
        'favorite_team_id' => $favoriteTeamId,
        'favorite_team' => $favoriteTeam,
        'items' => $items,
    ];
}

function fantasy_rank_map_from_sorted_rows(array $rows): array
{
    $rankMap = [];
    foreach ($rows as $idx => $row) {
        $rankMap[(int) $row['competitor_id']] = $idx + 1;
    }
    return $rankMap;
}

function fantasy_favorite_team(PDO $pdo, int $leagueId, int $favoriteTeamId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT team_id, name, short, logo
         FROM team
         WHERE league_id = :league_id
           AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':team_id' => $favoriteTeamId,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return [
            'team_id' => $favoriteTeamId,
            'name' => '',
            'short' => '',
            'logo_url' => '',
        ];
    }

    return [
        'team_id' => (int) $row['team_id'],
        'name' => (string) $row['name'],
        'short' => (string) $row['short'],
        'logo_url' => (string) $row['logo'],
    ];
}

function fantasy_private_leagues(PDO $pdo, array $schema, int $leagueId, ?array $yourCompetitor): array
{
    if ($yourCompetitor === null) {
        return [];
    }

    $statusExpr = ($schema['privateleaguemembers.status'] ?? false)
        ? "COALESCE(plm.status, CASE WHEN plm.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)"
        : "CASE WHEN plm.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
    $statusFilter = ($schema['privateleaguemembers.status'] ?? false)
        ? "AND plm.status IN ('member_confirmed','pending')"
        : '';

    $stmt = $pdo->prepare(
        'SELECT
            pl.privateleague_id,
            pl.leaguename,
            COALESCE(admin_profile.alias, \'\') AS admin_alias,
            COALESCE(mc.member_count, 0) AS member_count,
            ' . $statusExpr . ' AS your_status
         FROM privateleague pl
         INNER JOIN privateleaguemembers plm
            ON plm.privateleague_id = pl.privateleague_id
           AND plm.competitor_id = :competitor_id
         LEFT JOIN profile admin_profile ON admin_profile.profile_id = pl.admin
         LEFT JOIN (
            SELECT privateleague_id, COUNT(*) AS member_count
            FROM privateleaguemembers
            WHERE confirmed = 1
            GROUP BY privateleague_id
         ) mc ON mc.privateleague_id = pl.privateleague_id
         WHERE pl.league_id = :league_id
           ' . $statusFilter . '
         ORDER BY pl.privateleague_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':competitor_id' => (int) $yourCompetitor['competitor_id'],
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'privateleague_id' => (int) $row['privateleague_id'],
            'leaguename' => (string) $row['leaguename'],
            'admin_alias' => (string) $row['admin_alias'],
            'member_count' => (int) $row['member_count'],
            'your_status' => (string) $row['your_status'],
        ];
    }
    return $items;
}

function fantasy_has_postponed_matches(PDO $pdo, array $schema, int $leagueId, int $gw): bool
{
    if (!($schema['matches.status'] ?? false)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM matches
         WHERE league_id = :league_id
           AND gameweek = :gw
           AND `status` = :status
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
        ':status' => 'postponed',
    ]);
    return (bool) $stmt->fetchColumn();
}

function fantasy_etag_and_last_updated(PDO $pdo, array $schema, int $leagueId, int $gw, ?array $yourCompetitor): array
{
    $sumStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS rank_cnt,
            COALESCE(MAX(tr.`rank`), 0) AS rank_max,
            COALESCE(SUM(CASE WHEN tr.gameweek = :gw_week THEN trw.weeklypoints ELSE 0 END), 0) AS sum_week,
            COALESCE(SUM(COALESCE(tt.total_points, 0)), 0) AS sum_total
         FROM teamranking tr
         INNER JOIN competitor c ON c.competitor_id = tr.competitor_id
         LEFT JOIN teamresult trw ON trw.competitor_id = tr.competitor_id AND trw.gameweek = :gw_weekly
         LEFT JOIN (
            SELECT competitor_id, SUM(weeklypoints) AS total_points
            FROM teamresult
            WHERE gameweek <= :gw_total
            GROUP BY competitor_id
         ) tt ON tt.competitor_id = tr.competitor_id
         WHERE c.league_id = :league_id
           AND tr.gameweek = :gw'
    );
    $sumStmt->execute([
        ':league_id' => $leagueId,
        ':gw' => $gw,
        ':gw_week' => $gw,
        ':gw_weekly' => $gw,
        ':gw_total' => $gw,
    ]);
    $rankRow = $sumStmt->fetch() ?: [
        'rank_cnt' => 0,
        'rank_max' => 0,
        'sum_week' => '0',
        'sum_total' => '0',
    ];

    $plStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS pl_cnt,
            COALESCE(MAX(privateleague_id), 0) AS pl_max
         FROM privateleague
         WHERE league_id = :league_id'
    );
    $plStmt->execute([':league_id' => $leagueId]);
    $plRow = $plStmt->fetch() ?: ['pl_cnt' => 0, 'pl_max' => 0];

    $memCount = 0;
    if ($yourCompetitor !== null) {
        $memStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM privateleaguemembers plm
             INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
             WHERE pl.league_id = :league_id
               AND plm.competitor_id = :competitor_id'
        );
        $memStmt->execute([
            ':league_id' => $leagueId,
            ':competitor_id' => (int) $yourCompetitor['competitor_id'],
        ]);
        $memCount = (int) $memStmt->fetchColumn();
    }

    $timestamps = [];
    if ($schema['teamranking.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(tr.updated_at)
             FROM teamranking tr
             INNER JOIN competitor c ON c.competitor_id = tr.competitor_id
             WHERE c.league_id = :league_id
               AND tr.gameweek = :gw'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
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
    if ($schema['teamresult.updated_at'] ?? false) {
        $stmt = $pdo->prepare(
            'SELECT MAX(tr.updated_at)
             FROM teamresult tr
             INNER JOIN competitor c ON c.competitor_id = tr.competitor_id
             WHERE c.league_id = :league_id
               AND tr.gameweek <= :gw'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
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
    if ($schema['privateleague.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT MAX(updated_at) FROM privateleague WHERE league_id = :league_id');
        $stmt->execute([':league_id' => $leagueId]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if (($schema['privateleaguemembers.updated_at'] ?? false) && $yourCompetitor !== null) {
        $stmt = $pdo->prepare(
            'SELECT MAX(plm.updated_at)
             FROM privateleaguemembers plm
             INNER JOIN privateleague pl ON pl.privateleague_id = plm.privateleague_id
             WHERE pl.league_id = :league_id
               AND plm.competitor_id = :competitor_id'
        );
        $stmt->execute([
            ':league_id' => $leagueId,
            ':competitor_id' => (int) $yourCompetitor['competitor_id'],
        ]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $marker = [
        'fantasy-v1',
        'l:' . $leagueId,
        'gw:' . $gw,
        'rcnt:' . (int) $rankRow['rank_cnt'],
        'rmax:' . (int) $rankRow['rank_max'],
        'w:' . (string) $rankRow['sum_week'],
        't:' . (string) $rankRow['sum_total'],
        'pl:' . (int) $plRow['pl_cnt'] . ':' . (int) $plRow['pl_max'],
        'mem:' . $memCount,
    ];

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    return [
        'etag' => 'W/"fantasy-' . $leagueId . '-' . $gw . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function fantasy_if_none_match_matches(string $etag): bool
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

function fantasy_authorization_header(): string
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

function fantasy_require_auth_profile_id(): int
{
    $header = fantasy_authorization_header();
    if ($header === '') {
        fantasy_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = fantasy_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function fantasy_verify_jwt(string $token): array
{
    $secret = fantasy_jwt_secret();
    if ($secret === '') {
        fantasy_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) fantasy_b64url_decode($h64), true);
    $payload = json_decode((string) fantasy_b64url_decode($p64), true);
    $sig = fantasy_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        fantasy_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function fantasy_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function fantasy_jwt_secret(): string
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
