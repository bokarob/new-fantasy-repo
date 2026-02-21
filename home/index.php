<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method !== 'GET') {
        home_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $pdo = home_db();
    $profileId = home_require_auth_profile_id($pdo);

    $leagueId = null;
    if (array_key_exists('league_id', $_GET)) {
        $leagueRaw = trim((string) $_GET['league_id']);
        if ($leagueRaw === '' || !ctype_digit($leagueRaw)) {
            home_error(400, 'BAD_REQUEST', 'Invalid league_id.');
        }
        $leagueId = (int) $leagueRaw;
        if ($leagueId <= 0) {
            home_error(400, 'BAD_REQUEST', 'Invalid league_id.');
        }
    }

    $schema = home_schema_info($pdo);
    $profile = home_profile($pdo, $profileId);
    if ($profile === null) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    if ($leagueId !== null && !home_league_exists($pdo, $leagueId)) {
        home_error(404, 'LEAGUE_NOT_FOUND', 'League not found.');
    }

    $serverTime = gmdate('Y-m-d\TH:i:s\Z');
    $leagueRows = home_league_selector_rows($pdo, $profileId);
    $gwByLeague = home_current_gameweeks($pdo);
    $selectedGw = $leagueId !== null ? ($gwByLeague[$leagueId] ?? null) : null;

    $leagueSelector = home_build_league_selector($leagueRows, $gwByLeague, $leagueId);
    $notifications = home_notifications_preview($pdo, (int) $profileId, (int) ($profile['lang_id'] ?? 1), $schema);
    $news = home_news_preview($pdo, (int) ($profile['lang_id'] ?? 1), $leagueId !== null);
    $leagueContext = $leagueId !== null ? home_league_context($pdo, $profileId, $leagueId, $selectedGw, $schema) : null;

    $etagParts = home_etag_marker_parts($pdo, $profileId, (int) ($profile['lang_id'] ?? 1), $leagueId, $selectedGw, $schema);
    $lastUpdated = home_marker_to_iso($etagParts['last_updated']);
    $marker = sha1(implode('|', $etagParts['parts']));
    $currentGwForMeta = $selectedGw !== null ? (int) $selectedGw['gw'] : null;
    $etag = 'W/"home-u' . $profileId . '-' . ($leagueId ?? 0) . '-' . ($currentGwForMeta ?? 0) . '-' . $marker . '"';

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etag);

    if (home_if_none_match_matches($etag)) {
        http_response_code(304);
        exit;
    }

    $response = [
        'meta' => [
            'server_time' => $serverTime,
            'league_id' => $leagueId,
            'current_gw' => $currentGwForMeta,
            'last_updated' => $lastUpdated,
            'etag' => $etag,
        ],
        'data' => [
            'league_selector' => $leagueSelector,
            'league_context' => $leagueContext,
            'notifications_preview' => $notifications,
            'news_preview' => $news,
            'highlights' => null,
        ],
    ];

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    home_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function home_db(): PDO
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

function home_error(int $status, string $code, string $message): void
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

function home_profile(PDO $pdo, int $profileId): ?array
{
    $stmt = $pdo->prepare('SELECT profile_id, lang_id FROM profile WHERE profile_id = :id LIMIT 1');
    $stmt->execute([':id' => $profileId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function home_league_exists(PDO $pdo, int $leagueId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM leagues WHERE league_id = :id LIMIT 1');
    $stmt->execute([':id' => $leagueId]);
    return (bool) $stmt->fetchColumn();
}

function home_league_selector_rows(PDO $pdo, int $profileId): array
{
    $sql = 'SELECT l.league_id, l.`league name` AS name, c.competitor_id, c.teamname
            FROM leagues l
            LEFT JOIN competitor c ON c.league_id = l.league_id AND c.profile_id = :profile_id
            ORDER BY l.league_id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':profile_id' => $profileId]);
    return $stmt->fetchAll() ?: [];
}

function home_current_gameweeks(PDO $pdo): array
{
    $sql = 'SELECT g.league_id, g.gameweek, g.deadline, g.gamedate, g.open
            FROM gameweeks g
            INNER JOIN (
                SELECT league_id, COALESCE(MAX(CASE WHEN `open` = 1 THEN gameweek END), MAX(gameweek)) AS current_gw
                FROM gameweeks
                GROUP BY league_id
            ) pick ON pick.league_id = g.league_id AND pick.current_gw = g.gameweek';
    $rows = $pdo->query($sql)->fetchAll() ?: [];

    $out = [];
    foreach ($rows as $row) {
        $deadlineIso = home_date_eod_iso((string) $row['deadline']);
        $deadlineTs = strtotime((string) $row['deadline'] . ' 23:59:59 UTC');
        $isOpen = ((int) $row['open'] === 1) && $deadlineTs !== false && time() <= $deadlineTs;
        $out[(int) $row['league_id']] = [
            'gw' => (int) $row['gameweek'],
            'deadline' => $deadlineIso,
            'is_open' => $isOpen,
            'gamedate' => (string) $row['gamedate'],
        ];
    }
    return $out;
}

function home_build_league_selector(array $rows, array $gwByLeague, ?int $selectedLeagueId): array
{
    $leagues = [];
    foreach ($rows as $row) {
        $leagueId = (int) $row['league_id'];
        $gw = $gwByLeague[$leagueId] ?? null;
        $leagues[] = [
            'league_id' => $leagueId,
            'name' => (string) $row['name'],
            'logo_url' => '',
            'status' => [
                'current_gw' => $gw['gw'] ?? null,
                'deadline' => $gw['deadline'] ?? null,
                'is_open' => $gw['is_open'] ?? false,
            ],
            'competitor' => $row['competitor_id'] !== null ? [
                'competitor_id' => (int) $row['competitor_id'],
                'teamname' => (string) $row['teamname'],
            ] : null,
        ];
    }

    return [
        'selected_league_id' => $selectedLeagueId,
        'leagues' => $leagues,
    ];
}

function home_notifications_preview(PDO $pdo, int $profileId, int $langId, array $schema): array
{
    $hasReadAt = $schema['notification.read_at'] ?? false;
    $hasCreatedAt = $schema['notification.created_at'] ?? false;
    $hasUpdatedAt = $schema['notification.updated_at'] ?? false;

    $unreadCondition = $hasReadAt ? 'n.read_at IS NULL' : 'n.mark_read = 0';
    $orderExpr = $hasCreatedAt ? 'n.created_at' : ($hasUpdatedAt ? 'n.updated_at' : 'n.notification_id');
    $createdExpr = $hasCreatedAt ? 'n.created_at' : ($hasUpdatedAt ? 'n.updated_at' : 'UTC_TIMESTAMP()');

    $countSql = "SELECT COUNT(*) FROM notification n WHERE n.profile_id = :profile_id AND {$unreadCondition}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':profile_id' => $profileId]);
    $unreadCount = (int) $countStmt->fetchColumn();

    $itemsSql = "SELECT n.notification_id, n.notification_type, COALESCE(nt.text, n.notification_type) AS title, {$createdExpr} AS created_at
                 FROM notification n
                 LEFT JOIN notificationtext nt ON nt.notification_type = n.notification_type AND nt.lang_id = :lang_id
                 WHERE n.profile_id = :profile_id
                 ORDER BY {$orderExpr} DESC, n.notification_id DESC
                 LIMIT 3";
    $itemsStmt = $pdo->prepare($itemsSql);
    $itemsStmt->execute([
        ':profile_id' => $profileId,
        ':lang_id' => $langId,
    ]);
    $rows = $itemsStmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'notification_id' => (int) $row['notification_id'],
            'type' => (string) $row['notification_type'],
            'title' => (string) $row['title'],
            'created_at' => home_datetime_iso((string) $row['created_at']),
        ];
    }

    return [
        'unread_count' => $unreadCount,
        'items' => $items,
    ];
}

function home_news_preview(PDO $pdo, int $langId, bool $leagueMode): array
{
    $stmt = $pdo->prepare(
        'SELECT news_id, newstitle, published_on, image
         FROM news
         WHERE live = 1 AND lang_id = :lang_id
         ORDER BY published_on DESC, news_id DESC
         LIMIT 3'
    );
    $stmt->execute([':lang_id' => $langId]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'news_id' => (int) $row['news_id'],
            'title' => (string) $row['newstitle'],
            'published_on' => home_date_start_iso((string) $row['published_on']),
            'image_url' => (string) $row['image'],
        ];
    }

    return [
        'mode' => $leagueMode ? 'league' : 'global',
        'items' => $items,
    ];
}

function home_league_context(PDO $pdo, int $profileId, int $leagueId, ?array $selectedGw, array $schema): array
{
    $context = [
        'league_id' => $leagueId,
        'gameweek' => [
            'gw' => $selectedGw['gw'] ?? null,
            'deadline' => $selectedGw['deadline'] ?? null,
            'is_open' => $selectedGw['is_open'] ?? false,
            'gamedate' => $selectedGw['gamedate'] ?? null,
        ],
        'your_team' => null,
    ];

    $stmt = $pdo->prepare(
        'SELECT competitor_id, teamname
         FROM competitor
         WHERE profile_id = :profile_id AND league_id = :league_id
         LIMIT 1'
    );
    $stmt->execute([
        ':profile_id' => $profileId,
        ':league_id' => $leagueId,
    ]);
    $competitor = $stmt->fetch();
    if (!$competitor) {
        return $context;
    }

    $competitorId = (int) $competitor['competitor_id'];
    $currentGw = $selectedGw !== null ? (int) $selectedGw['gw'] : null;

    $rank = null;
    $previousRank = null;
    $weeklyPoints = null;
    $totalPoints = null;
    if ($currentGw !== null) {
        $rank = home_scalar_int($pdo, 'SELECT `rank` FROM teamranking WHERE competitor_id = :id AND gameweek = :gw LIMIT 1', [
            ':id' => $competitorId,
            ':gw' => $currentGw,
        ]);
        $previousRank = home_scalar_int($pdo, 'SELECT `rank` FROM teamranking WHERE competitor_id = :id AND gameweek = :gw LIMIT 1', [
            ':id' => $competitorId,
            ':gw' => $currentGw - 1,
        ]);
        $weeklyPoints = home_scalar_float($pdo, 'SELECT weeklypoints FROM teamresult WHERE competitor_id = :id AND gameweek = :gw LIMIT 1', [
            ':id' => $competitorId,
            ':gw' => $currentGw,
        ]);
        $totalPoints = home_scalar_float($pdo, 'SELECT SUM(weeklypoints) FROM teamresult WHERE competitor_id = :id AND gameweek <= :gw', [
            ':id' => $competitorId,
            ':gw' => $currentGw,
        ]);
    }

    $context['your_team'] = [
        'competitor_id' => $competitorId,
        'teamname' => (string) $competitor['teamname'],
        'rank' => $rank,
        'previous_rank' => $previousRank,
        'rank_change' => ($rank !== null && $previousRank !== null) ? ($previousRank - $rank) : null,
        'total_points' => $totalPoints,
        'weekly_points' => $weeklyPoints,
    ];

    return $context;
}

function home_etag_marker_parts(PDO $pdo, int $profileId, int $langId, ?int $leagueId, ?array $selectedGw, array $schema): array
{
    $parts = [
        'home-v1',
        'u:' . $profileId,
        'l:' . ($leagueId ?? 0),
        'gw:' . (($selectedGw['gw'] ?? 0)),
    ];

    $timestamps = [];
    $notificationTs = home_max_timestamp($pdo, 'notification', 'profile_id', $profileId, ['updated_at', 'created_at'], $schema);
    $competitorTs = home_max_timestamp($pdo, 'competitor', 'profile_id', $profileId, ['updated_at'], $schema);
    $newsTs = home_max_timestamp($pdo, 'news', 'lang_id', $langId, ['updated_at', 'published_on'], $schema);
    $gameweekTs = home_global_gameweek_marker($pdo, $schema);

    $parts[] = 'n:' . ($notificationTs['marker'] ?? '0');
    $parts[] = 'c:' . ($competitorTs['marker'] ?? '0');
    $parts[] = 'news:' . ($newsTs['marker'] ?? '0');
    $parts[] = 'gwsrc:' . ($gameweekTs['marker'] ?? '0');

    foreach ([$notificationTs, $competitorTs, $newsTs, $gameweekTs] as $ts) {
        if (!empty($ts['timestamp'])) {
            $timestamps[] = (int) $ts['timestamp'];
        }
    }

    if ($leagueId !== null) {
        $competitorStmt = $pdo->prepare('SELECT competitor_id FROM competitor WHERE profile_id = :profile_id AND league_id = :league_id LIMIT 1');
        $competitorStmt->execute([':profile_id' => $profileId, ':league_id' => $leagueId]);
        $competitorId = $competitorStmt->fetchColumn();
        if ($competitorId !== false) {
            $rankingTs = home_max_timestamp($pdo, 'teamranking', 'competitor_id', (int) $competitorId, ['updated_at'], $schema);
            $resultTs = home_max_timestamp($pdo, 'teamresult', 'competitor_id', (int) $competitorId, ['updated_at'], $schema);
            $parts[] = 'rk:' . ($rankingTs['marker'] ?? '0');
            $parts[] = 'res:' . ($resultTs['marker'] ?? '0');
            foreach ([$rankingTs, $resultTs] as $ts) {
                if (!empty($ts['timestamp'])) {
                    $timestamps[] = (int) $ts['timestamp'];
                }
            }
        } else {
            $parts[] = 'rk:0';
            $parts[] = 'res:0';
        }
    }

    $lastUpdated = !empty($timestamps) ? max($timestamps) : time();
    return [
        'parts' => $parts,
        'last_updated' => $lastUpdated,
    ];
}

function home_max_timestamp(PDO $pdo, string $table, string $whereColumn, int $whereValue, array $candidateColumns, array $schema): array
{
    foreach ($candidateColumns as $column) {
        $key = $table . '.' . $column;
        if (!($schema[$key] ?? false)) {
            continue;
        }
        $sql = "SELECT MAX({$column}) AS marker FROM {$table} WHERE {$whereColumn} = :v";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':v' => $whereValue]);
        $marker = $stmt->fetchColumn();
        if ($marker !== false && $marker !== null) {
            $ts = strtotime((string) $marker);
            return ['marker' => (string) $marker, 'timestamp' => $ts ?: null];
        }
    }

    if ($table === 'teamranking') {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(gameweek), 0) FROM teamranking WHERE competitor_id = :v');
        $stmt->execute([':v' => $whereValue]);
        $gw = (int) $stmt->fetchColumn();
        return ['marker' => 'gw:' . $gw, 'timestamp' => null];
    }
    if ($table === 'teamresult') {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(gameweek), 0), COALESCE(SUM(weeklypoints), 0) FROM teamresult WHERE competitor_id = :v');
        $stmt->execute([':v' => $whereValue]);
        $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
        return ['marker' => 'gw:' . ((int) $row[0]) . '|sum:' . ((string) $row[1]), 'timestamp' => null];
    }
    if ($table === 'competitor') {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(competitor_id), 0) FROM competitor WHERE profile_id = :v');
        $stmt->execute([':v' => $whereValue]);
        return ['marker' => 'cid:' . ((int) $stmt->fetchColumn()), 'timestamp' => null];
    }
    if ($table === 'notification') {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(notification_id), 0) FROM notification WHERE profile_id = :v');
        $stmt->execute([':v' => $whereValue]);
        return ['marker' => 'nid:' . ((int) $stmt->fetchColumn()), 'timestamp' => null];
    }
    if ($table === 'news') {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(news_id), 0), COALESCE(MAX(published_on), "1970-01-01") FROM news WHERE lang_id = :v AND live = 1');
        $stmt->execute([':v' => $whereValue]);
        $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, '1970-01-01'];
        $ts = strtotime((string) $row[1] . ' 00:00:00 UTC');
        return ['marker' => 'nid:' . ((int) $row[0]) . '|p:' . ((string) $row[1]), 'timestamp' => $ts ?: null];
    }

    return ['marker' => '0', 'timestamp' => null];
}

function home_global_gameweek_marker(PDO $pdo, array $schema): array
{
    if ($schema['gameweeks.updated_at'] ?? false) {
        $marker = $pdo->query('SELECT MAX(updated_at) FROM gameweeks')->fetchColumn();
        if ($marker !== false && $marker !== null) {
            $ts = strtotime((string) $marker);
            return ['marker' => (string) $marker, 'timestamp' => $ts ?: null];
        }
    }

    $row = $pdo->query('SELECT COALESCE(MAX(gameweek), 0) AS gw, COALESCE(MAX(deadline), "1970-01-01") AS dl, COALESCE(SUM(`open`), 0) AS opens FROM gameweeks')
        ->fetch(PDO::FETCH_ASSOC);
    $gw = (int) ($row['gw'] ?? 0);
    $dl = (string) ($row['dl'] ?? '1970-01-01');
    $opens = (int) ($row['opens'] ?? 0);
    $ts = strtotime($dl . ' 00:00:00 UTC');
    return ['marker' => 'gw:' . $gw . '|dl:' . $dl . '|o:' . $opens, 'timestamp' => $ts ?: null];
}

function home_schema_info(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $dbName = getenv('DB_NAME') ?: 'fantasy_app';
    $stmt = $pdo->prepare(
        'SELECT table_name, column_name
         FROM information_schema.columns
         WHERE table_schema = :db AND table_name IN ("notification","competitor","news","gameweeks","teamranking","teamresult")'
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

function home_scalar_int(PDO $pdo, string $sql, array $params): ?int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null ? (int) $value : null;
}

function home_scalar_float(PDO $pdo, string $sql, array $params): ?float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null ? (float) $value : null;
}

function home_marker_to_iso(int $ts): string
{
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function home_date_start_iso(string $date): string
{
    $ts = strtotime($date . ' 00:00:00 UTC');
    if ($ts === false) {
        return '1970-01-01T00:00:00Z';
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function home_date_eod_iso(string $date): string
{
    $ts = strtotime($date . ' 23:59:59 UTC');
    if ($ts === false) {
        return '1970-01-01T23:59:59Z';
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function home_datetime_iso(string $value): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function home_if_none_match_matches(string $etag): bool
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

function home_require_auth_profile_id(PDO $pdo): int
{
    $header = home_authorization_header();
    if ($header === '') {
        home_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $token = trim($m[1]);
    $payload = home_verify_jwt($token);
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub)) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $profileId = (int) $sub;
    if ($profileId <= 0) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $profileId;
}

function home_authorization_header(): string
{
    $keys = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
    ];
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

function home_verify_jwt(string $token): array
{
    $secret = home_jwt_secret();
    if ($secret === '') {
        home_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    [$h64, $p64, $s64] = $parts;
    $header = json_decode((string) home_b64url_decode($h64), true);
    $payload = json_decode((string) home_b64url_decode($p64), true);
    $signature = home_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $signature === null) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $signature)) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $now = time();
    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < $now) {
        home_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function home_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function home_jwt_secret(): string
{
    $secret = trim((string) (getenv('JWT_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $configPath = dirname(__DIR__) . '/config/app.php';
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
