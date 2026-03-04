<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        pl_detail_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $leagueId = pl_detail_resolve_league_id();
    $privateleagueId = pl_detail_resolve_privateleague_id();

    $pdo = pl_detail_db();
    $profileId = pl_detail_require_auth_profile_id();
    $schema = pl_detail_schema_info($pdo);

    if (!pl_detail_league_exists($pdo, $leagueId)) {
        pl_detail_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $gw = pl_detail_current_gameweek($pdo, $leagueId, $schema);
    if ($gw === null) {
        pl_detail_error(409, 'GW_NOT_AVAILABLE', 'League GW not initialized.');
    }
    $currentGw = (int) $gw['gw'];

    $privateleague = pl_detail_privateleague($pdo, $schema, $leagueId, $privateleagueId);
    if ($privateleague === null) {
        pl_detail_error(404, 'PRIVATE_LEAGUE_NOT_FOUND', 'Private league not found.');
    }
    $adminProfileId = (int) $privateleague['admin_profile_id'];

    $competitor = pl_detail_competitor($pdo, $profileId, $leagueId, $schema);
    $callerCompetitorId = $competitor === null ? null : (int) $competitor['competitor_id'];
    $callerMembership = null;
    if ($callerCompetitorId !== null) {
        $callerMembership = pl_detail_membership_for_competitor($pdo, $schema, $privateleagueId, $callerCompetitorId);
    }

    $isAdmin = $profileId === $adminProfileId;
    $callerStatus = $callerMembership !== null ? (string) $callerMembership['status'] : '';
    $hasAllowedMembership = in_array($callerStatus, ['member_confirmed', 'pending'], true);
    if (!$isAdmin && !$hasAllowedMembership) {
        pl_detail_error(403, 'PRIVATE_LEAGUE_FORBIDDEN', 'Not allowed to access this private league.');
    }

    $yourRole = $isAdmin ? 'admin' : 'member';
    $yourStatus = $callerMembership !== null ? (string) $callerMembership['status'] : 'member_confirmed';
    if ($yourStatus === '') {
        $yourStatus = 'member_confirmed';
    }

    $permissions = pl_detail_permissions($yourRole, $yourStatus);
    $pendingMembers = pl_detail_pending_members($pdo, $schema, $leagueId, $privateleagueId, $callerCompetitorId);

    $confirmedCompetitorIds = pl_detail_confirmed_competitor_ids($pdo, $schema, $leagueId, $privateleagueId);
    $standings = pl_detail_standings($pdo, $schema, $leagueId, $currentGw, $confirmedCompetitorIds, $callerCompetitorId);
    if (count($confirmedCompetitorIds) > 0 && count($standings['items']) === 0) {
        pl_detail_error(409, 'RANKING_NOT_AVAILABLE', 'Standings are not available yet.');
    }

    $etagBuild = pl_detail_etag_and_last_updated(
        $pdo,
        $schema,
        $leagueId,
        $privateleagueId,
        $currentGw,
        $confirmedCompetitorIds,
        $pendingMembers,
        $standings,
        $gw
    );

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    if (pl_detail_if_none_match_matches($etagBuild['etag'])) {
        http_response_code(304);
        exit;
    }

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
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
            'privateleague' => [
                'privateleague_id' => (int) $privateleague['privateleague_id'],
                'leaguename' => (string) $privateleague['leaguename'],
                'admin_profile_id' => $adminProfileId,
                'admin_alias' => (string) $privateleague['admin_alias'],
                'member_count' => (int) $privateleague['member_count'],
            ],
            'membership' => [
                'your_role' => $yourRole,
                'your_status' => $yourStatus,
            ],
            'gameweek' => [
                'gw' => $currentGw,
                'is_open' => (bool) $gw['is_open'],
                'deadline' => (string) $gw['deadline'],
            ],
            'standings' => $standings,
            'pending_members' => $pendingMembers,
            'permissions' => $permissions,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    pl_detail_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function pl_detail_resolve_league_id(): int
{
    $raw = isset($_GET['league_id']) ? (string) $_GET['league_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/(\d+)/private-leagues/\d+/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_detail_error(400, 'BAD_REQUEST', 'Invalid league_id.');
    }

    return (int) $raw;
}

function pl_detail_resolve_privateleague_id(): int
{
    $raw = isset($_GET['privateleague_id']) ? (string) $_GET['privateleague_id'] : null;
    if ($raw === null) {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/leagues/\d+/private-leagues/(\d+)/?$#', $path, $m)) {
            $raw = $m[1];
        }
    }

    if ($raw === null || $raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
        pl_detail_error(400, 'BAD_REQUEST', 'Invalid privateleague_id.');
    }

    return (int) $raw;
}

function pl_detail_db(): PDO
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

function pl_detail_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function pl_detail_schema_info(PDO $pdo): array
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
           AND table_name IN ("leagues","gameweeks","privateleague","privateleaguemembers","competitor","profile","teamranking","teamresult")'
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

function pl_detail_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :league_id LIMIT 1');
    $stmt->execute([':league_id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function pl_detail_current_gameweek(PDO $pdo, int $leagueId, array $schema): ?array
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

    $deadlineTs = strtotime((string) $row['deadline'] . ' 23:59:59 UTC');
    $isOpen = ((int) $row['open'] === 1) && $deadlineTs !== false && time() <= $deadlineTs;

    return [
        'gw' => (int) $row['gameweek'],
        'deadline' => pl_detail_deadline_iso((string) $row['deadline']),
        'is_open' => $isOpen,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function pl_detail_privateleague_admin_column(array $schema): string
{
    if ($schema['privateleague.admin_profile_id'] ?? false) {
        return 'admin_profile_id';
    }
    return 'admin';
}

function pl_detail_member_status_expr(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "COALESCE(NULLIF(TRIM({$alias}.status), ''), CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END)";
    }
    return "CASE WHEN {$alias}.confirmed = 1 THEN 'member_confirmed' ELSE 'pending' END";
}

function pl_detail_pending_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'pending' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 0))";
    }
    return "{$alias}.confirmed = 0";
}

function pl_detail_confirmed_condition(array $schema, string $alias): string
{
    if ($schema['privateleaguemembers.status'] ?? false) {
        return "({$alias}.status = 'member_confirmed' OR ({$alias}.status IS NULL AND {$alias}.confirmed = 1))";
    }
    return "{$alias}.confirmed = 1";
}

function pl_detail_membership_created_expr(array $schema, string $alias): string
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

function pl_detail_privateleague(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): ?array
{
    $adminColumn = pl_detail_privateleague_admin_column($schema);
    $memberCountCondition = pl_detail_confirmed_condition($schema, 'plm_count');
    $stmt = $pdo->prepare(
        'SELECT
            pl.privateleague_id,
            pl.leaguename,
            COALESCE(pl.' . $adminColumn . ', 0) AS admin_profile_id,
            COALESCE(ap.alias, \'\') AS admin_alias,
            COALESCE(mc.member_count, 0) AS member_count
         FROM privateleague pl
         LEFT JOIN profile ap ON ap.profile_id = pl.' . $adminColumn . '
         LEFT JOIN (
            SELECT privateleague_id, COUNT(*) AS member_count
            FROM privateleaguemembers plm_count
            WHERE ' . $memberCountCondition . '
            GROUP BY privateleague_id
         ) mc ON mc.privateleague_id = pl.privateleague_id
         WHERE pl.league_id = :league_id
           AND pl.privateleague_id = :privateleague_id
         LIMIT 1'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_detail_competitor(PDO $pdo, int $profileId, int $leagueId, array $schema): ?array
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

function pl_detail_membership_for_competitor(PDO $pdo, array $schema, int $privateleagueId, int $competitorId): ?array
{
    $statusExpr = pl_detail_member_status_expr($schema, 'plm');
    $stmt = $pdo->prepare(
        'SELECT
            plm.privateleague_id,
            plm.competitor_id,
            ' . $statusExpr . ' AS status
         FROM privateleaguemembers plm
         WHERE plm.privateleague_id = :privateleague_id
           AND plm.competitor_id = :competitor_id
         LIMIT 1'
    );
    $stmt->execute([
        ':privateleague_id' => $privateleagueId,
        ':competitor_id' => $competitorId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pl_detail_permissions(string $yourRole, string $yourStatus): array
{
    if ($yourRole === 'admin') {
        return [
            'can_invite' => true,
            'can_remove_members' => true,
            'can_rename' => true,
            'can_delete' => true,
            'can_leave' => true,
        ];
    }

    if ($yourStatus === 'member_confirmed') {
        return [
            'can_invite' => false,
            'can_remove_members' => false,
            'can_rename' => false,
            'can_delete' => false,
            'can_leave' => true,
        ];
    }

    return [
        'can_invite' => false,
        'can_remove_members' => false,
        'can_rename' => false,
        'can_delete' => false,
        'can_leave' => false,
    ];
}

function pl_detail_pending_members(PDO $pdo, array $schema, int $leagueId, int $privateleagueId, ?int $excludeCompetitorId): array
{
    $pendingCondition = pl_detail_pending_condition($schema, 'plm');
    $createdExpr = pl_detail_membership_created_expr($schema, 'plm');
    $sql =
        'SELECT
            c.competitor_id,
            COALESCE(p.alias, \'\') AS alias,
            COALESCE(c.teamname, \'\') AS teamname,
            COALESCE(' . $createdExpr . ', UTC_TIMESTAMP()) AS invited_at
         FROM privateleaguemembers plm
         INNER JOIN competitor c ON c.competitor_id = plm.competitor_id
         LEFT JOIN profile p ON p.profile_id = c.profile_id
         WHERE c.league_id = :league_id
           AND plm.privateleague_id = :privateleague_id
           AND ' . $pendingCondition;

    $params = [
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
    ];

    if ($excludeCompetitorId !== null) {
        $sql .= ' AND c.competitor_id <> :exclude_competitor_id';
        $params[':exclude_competitor_id'] = $excludeCompetitorId;
    }
    $sql .= ' ORDER BY c.competitor_id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'competitor_id' => (int) ($row['competitor_id'] ?? 0),
            'alias' => (string) ($row['alias'] ?? ''),
            'teamname' => (string) ($row['teamname'] ?? ''),
            'invited_at' => pl_detail_datetime_iso((string) ($row['invited_at'] ?? '')),
        ];
    }
    return $items;
}

function pl_detail_confirmed_competitor_ids(PDO $pdo, array $schema, int $leagueId, int $privateleagueId): array
{
    $confirmedCondition = pl_detail_confirmed_condition($schema, 'plm');
    $stmt = $pdo->prepare(
        'SELECT c.competitor_id
         FROM privateleaguemembers plm
         INNER JOIN competitor c ON c.competitor_id = plm.competitor_id
         WHERE c.league_id = :league_id
           AND plm.privateleague_id = :privateleague_id
           AND ' . $confirmedCondition . '
         ORDER BY c.competitor_id ASC'
    );
    $stmt->execute([
        ':league_id' => $leagueId,
        ':privateleague_id' => $privateleagueId,
    ]);
    $rows = $stmt->fetchAll() ?: [];

    $ids = [];
    foreach ($rows as $row) {
        $id = (int) ($row['competitor_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function pl_detail_standings(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $currentGw,
    array $confirmedCompetitorIds,
    ?int $callerCompetitorId
): array {
    if (empty($confirmedCompetitorIds)) {
        return [
            'items' => [],
            'you' => null,
        ];
    }

    $inParts = [];
    $params = [
        ':gw' => $currentGw,
        ':prev_gw' => $currentGw - 1,
        ':gw_weekly' => $currentGw,
        ':gw_total' => $currentGw,
    ];
    foreach ($confirmedCompetitorIds as $idx => $competitorId) {
        $key = ':cid' . $idx;
        $inParts[] = $key;
        $params[$key] = (int) $competitorId;
    }
    $inSql = implode(',', $inParts);

    $stmt = $pdo->prepare(
        'SELECT
            c.competitor_id,
            c.teamname,
            COALESCE(p.alias, \'\') AS alias,
            tr.`rank` AS current_rank,
            COALESCE(pr.`rank`, tr.`rank`) AS previous_rank,
            COALESCE(w.weekly_points, 0) AS weekly_points,
            COALESCE(t.total_points, 0) AS total_points
         FROM competitor c
         INNER JOIN profile p ON p.profile_id = c.profile_id
         INNER JOIN teamranking tr
            ON tr.competitor_id = c.competitor_id
           AND tr.gameweek = :gw
         LEFT JOIN teamranking pr
            ON pr.competitor_id = c.competitor_id
           AND pr.gameweek = :prev_gw
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
           AND c.competitor_id IN (' . $inSql . ')
         ORDER BY tr.`rank` ASC, c.competitor_id ASC'
    );
    $params[':league_id'] = $leagueId;
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $currentRank = (int) ($row['current_rank'] ?? 0);
        $previousRank = (int) ($row['previous_rank'] ?? $currentRank);
        $items[] = [
            'rank' => $currentRank,
            'previous_rank' => $previousRank,
            'rank_change' => $previousRank - $currentRank,
            'competitor_id' => (int) ($row['competitor_id'] ?? 0),
            'teamname' => (string) ($row['teamname'] ?? ''),
            'alias' => (string) ($row['alias'] ?? ''),
            'total_points' => (float) ($row['total_points'] ?? 0),
            'weekly_points' => (float) ($row['weekly_points'] ?? 0),
        ];
    }

    $you = null;
    if ($callerCompetitorId !== null) {
        foreach ($items as $item) {
            if ((int) $item['competitor_id'] === $callerCompetitorId) {
                $you = [
                    'competitor_id' => $callerCompetitorId,
                    'rank' => (int) $item['rank'],
                ];
                break;
            }
        }
    }

    return [
        'items' => $items,
        'you' => $you,
    ];
}

function pl_detail_etag_and_last_updated(
    PDO $pdo,
    array $schema,
    int $leagueId,
    int $privateleagueId,
    int $currentGw,
    array $confirmedCompetitorIds,
    array $pendingMembers,
    array $standings,
    array $gw
): array {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM privateleaguemembers WHERE privateleague_id = :privateleague_id');
    $countStmt->execute([':privateleague_id' => $privateleagueId]);
    $membershipCount = (int) $countStmt->fetchColumn();

    $maxMemberCompetitorId = 0;
    $maxMemberTouched = '0';
    if ($schema['privateleaguemembers.updated_at'] ?? false) {
        $touchStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(competitor_id), 0) AS max_cid, MAX(updated_at) AS max_touch
             FROM privateleaguemembers
             WHERE privateleague_id = :privateleague_id'
        );
        $touchStmt->execute([':privateleague_id' => $privateleagueId]);
        $touchRow = $touchStmt->fetch();
        if ($touchRow) {
            $maxMemberCompetitorId = (int) ($touchRow['max_cid'] ?? 0);
            if (!empty($touchRow['max_touch'])) {
                $maxMemberTouched = (string) $touchRow['max_touch'];
            }
        }
    } else {
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(competitor_id), 0) FROM privateleaguemembers WHERE privateleague_id = :privateleague_id');
        $maxStmt->execute([':privateleague_id' => $privateleagueId]);
        $maxMemberCompetitorId = (int) $maxStmt->fetchColumn();
    }

    $sumWeekly = 0.0;
    $sumTotal = 0.0;
    $maxRank = 0;
    foreach (($standings['items'] ?? []) as $item) {
        $sumWeekly += (float) ($item['weekly_points'] ?? 0);
        $sumTotal += (float) ($item['total_points'] ?? 0);
        $maxRank = max($maxRank, (int) ($item['rank'] ?? 0));
    }

    $marker = [
        'pl-detail-v1',
        'l:' . $leagueId,
        'pl:' . $privateleagueId,
        'gw:' . $currentGw,
        'mcnt:' . $membershipCount,
        'mmax:' . $maxMemberCompetitorId,
        'mts:' . $maxMemberTouched,
        'ccnt:' . count($confirmedCompetitorIds),
        'pcnt:' . count($pendingMembers),
        'rankmax:' . $maxRank,
        'sumw:' . number_format($sumWeekly, 1, '.', ''),
        'sumt:' . number_format($sumTotal, 1, '.', ''),
    ];

    $timestamps = [];
    if (!empty($gw['updated_at'])) {
        $ts = strtotime((string) $gw['updated_at']);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }
    if (($schema['privateleaguemembers.updated_at'] ?? false) && $maxMemberTouched !== '0') {
        $ts = strtotime($maxMemberTouched);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }
    if ($schema['privateleague.updated_at'] ?? false) {
        $stmt = $pdo->prepare('SELECT updated_at FROM privateleague WHERE privateleague_id = :privateleague_id LIMIT 1');
        $stmt->execute([':privateleague_id' => $privateleagueId]);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if (($schema['teamranking.updated_at'] ?? false) && !empty($confirmedCompetitorIds)) {
        $inParts = [];
        $params = [':gw' => $currentGw];
        foreach ($confirmedCompetitorIds as $idx => $competitorId) {
            $key = ':cid' . $idx;
            $inParts[] = $key;
            $params[$key] = (int) $competitorId;
        }
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at)
             FROM teamranking
             WHERE gameweek = :gw
               AND competitor_id IN (' . implode(',', $inParts) . ')'
        );
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }
    if (($schema['teamresult.updated_at'] ?? false) && !empty($confirmedCompetitorIds)) {
        $inParts = [];
        $params = [':gw' => $currentGw];
        foreach ($confirmedCompetitorIds as $idx => $competitorId) {
            $key = ':cid' . $idx;
            $inParts[] = $key;
            $params[$key] = (int) $competitorId;
        }
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at)
             FROM teamresult
             WHERE gameweek <= :gw
               AND competitor_id IN (' . implode(',', $inParts) . ')'
        );
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            $ts = strtotime((string) $value);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    $lastUpdatedTs = !empty($timestamps) ? max($timestamps) : time();
    return [
        'etag' => 'W/"pl-' . $privateleagueId . '-l' . $leagueId . '-gw' . $currentGw . '-' . sha1(implode('|', $marker)) . '"',
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z', $lastUpdatedTs),
    ];
}

function pl_detail_if_none_match_matches(string $etag): bool
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

function pl_detail_authorization_header(): string
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

function pl_detail_require_auth_profile_id(): int
{
    $header = pl_detail_authorization_header();
    if ($header === '') {
        pl_detail_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = pl_detail_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function pl_detail_verify_jwt(string $token): array
{
    $secret = pl_detail_jwt_secret();
    if ($secret === '') {
        pl_detail_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) pl_detail_b64url_decode($h64), true);
    $payload = json_decode((string) pl_detail_b64url_decode($p64), true);
    $sig = pl_detail_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        pl_detail_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function pl_detail_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function pl_detail_jwt_secret(): string
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

function pl_detail_deadline_iso(string $deadlineDate): string
{
    $ts = strtotime($deadlineDate . ' 23:59:59 UTC');
    if ($ts === false) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function pl_detail_datetime_iso(string $value): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
