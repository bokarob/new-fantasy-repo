<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'PATCH') {
        me_handle_patch();
    }
    if ($method === 'DELETE') {
        me_handle_delete();
    }
    if ($method !== 'GET') {
        me_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $pdo = me_db();
    $profileId = me_require_auth_profile_id();
    $schema = me_schema_info($pdo);

    $profile = me_profile_row($pdo, $profileId, $schema);
    if ($profile === null) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $etagBuild = me_etag_and_last_updated($profile, $schema);

    header('Cache-Control: private, must-revalidate');
    header('ETag: ' . $etagBuild['etag']);

    $ifNoneMatch = me_if_none_match_raw();
    if (me_if_none_match_matches($etagBuild['etag'], $ifNoneMatch)) {
        http_response_code(304);
        exit;
    }

    echo json_encode([
        'meta' => [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $etagBuild['last_updated'],
            'etag' => $etagBuild['etag'],
        ],
        'data' => [
            'me' => [
                'profile_id' => (int) $profile['profile_id'],
                'alias' => (string) $profile['alias'],
                'email' => (string) $profile['email'],
                'lang' => me_normalize_lang_short((string) ($profile['lang_short'] ?? '')),
                'created_at' => me_datetime_iso((string) ($profile['created_at'] ?? '')),
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    me_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function me_handle_patch(): void
{
    header('Cache-Control: no-store');

    $pdo = me_db();
    $profileId = me_require_auth_profile_id();
    $schema = me_schema_info($pdo);
    $profile = me_profile_row($pdo, $profileId, $schema);
    if ($profile === null) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $input = me_patch_json_input();
    $hasAlias = array_key_exists('alias', $input);
    $hasLang = array_key_exists('lang', $input);
    if (!$hasAlias && !$hasLang) {
        me_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $sets = [];
    $params = [':profile_id' => $profileId];

    if ($hasAlias) {
        $sets[] = 'alias = :alias';
        $params[':alias'] = me_validate_alias($input['alias']);
    }

    if ($hasLang) {
        $sets[] = 'lang_id = :lang_id';
        $params[':lang_id'] = me_validate_and_resolve_lang_id($pdo, $input['lang']);
    }

    if ($schema['profile.updated_at'] ?? false) {
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare(
        'UPDATE profile
         SET ' . implode(', ', $sets) . '
         WHERE profile_id = :profile_id
         LIMIT 1'
    );
    $stmt->execute($params);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    echo json_encode([
        'meta' => [
            'server_time' => $now,
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $now,
            'etag' => null,
        ],
        'data' => [
            'ok' => true,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function me_handle_delete(): void
{
    header('Cache-Control: no-store');

    $pdo = me_db();
    $profileId = me_require_auth_profile_id();
    $schema = me_schema_info($pdo);
    $profile = me_profile_row($pdo, $profileId, $schema);

    if ($profile === null) {
        me_delete_success();
    }

    try {
        $pdo->beginTransaction();

        $competitorRows = me_profile_competitors($pdo, $profileId);
        $competitorIds = [];
        foreach ($competitorRows as $row) {
            $competitorId = (int) ($row['competitor_id'] ?? 0);
            if ($competitorId > 0) {
                $competitorIds[] = $competitorId;
            }
        }
        $competitorIds = array_values(array_unique($competitorIds));

        $adminPrivateleagueIds = me_admin_privateleague_ids($pdo, $schema, $profileId);
        if (!empty($adminPrivateleagueIds)) {
            me_delete_rows_by_ids($pdo, 'privateleaguemembers', 'privateleague_id', $adminPrivateleagueIds);
            me_delete_rows_by_ids($pdo, 'privateleague', 'privateleague_id', $adminPrivateleagueIds);
        }

        if (!empty($competitorIds)) {
            me_delete_rows_by_ids($pdo, 'privateleaguemembers', 'competitor_id', $competitorIds);
            me_delete_rows_by_ids($pdo, 'votes', 'competitor_id', $competitorIds);
            me_delete_rows_by_ids($pdo, 'roster', 'competitor_id', $competitorIds);
            me_delete_rows_by_ids($pdo, 'transfers', 'competitor_id', $competitorIds);
            me_delete_rows_by_ids($pdo, 'teamresult', 'competitor_id', $competitorIds);
            me_delete_rows_by_ids($pdo, 'teamranking', 'competitor_id', $competitorIds);
        }

        me_delete_rows_by_profile_id($pdo, $schema, 'notification', $profileId);
        me_delete_rows_by_profile_id($pdo, $schema, 'auth_refresh_tokens', $profileId);
        me_delete_rows_by_profile_id($pdo, $schema, 'extrapictures', $profileId);
        me_delete_rows_by_profile_id($pdo, $schema, 'trollanswers', $profileId);
        me_delete_rows_by_profile_id($pdo, $schema, 'trollpoints', $profileId);

        if (!empty($competitorIds)) {
            me_delete_rows_by_ids($pdo, 'competitor', 'competitor_id', $competitorIds);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM profile
             WHERE profile_id = :profile_id
             LIMIT 1'
        );
        $stmt->execute([':profile_id' => $profileId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        me_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }

    me_delete_success();
}

function me_db(): PDO
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

function me_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function me_patch_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        me_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        me_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    return $decoded;
}

function me_validate_alias($value): string
{
    if (!is_string($value)) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid alias.');
    }

    $alias = trim($value);
    $len = me_unicode_length($alias);
    if ($alias === '' || $len < 3 || $len > 30) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid alias.');
    }
    if (!preg_match('/^[\p{L}\p{N} ._-]+$/u', $alias)) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid alias.');
    }

    return $alias;
}

function me_validate_and_resolve_lang_id(PDO $pdo, $value): int
{
    if (!is_string($value)) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid lang.');
    }

    $short = strtolower(trim($value));
    if (!preg_match('/^[a-z]{2}$/', $short)) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid lang.');
    }

    $stmt = $pdo->prepare(
        'SELECT lang_id
         FROM languages
         WHERE short = :short
         LIMIT 1'
    );
    $stmt->execute([':short' => $short]);
    $langId = $stmt->fetchColumn();
    if ($langId === false || $langId === null) {
        me_error(422, 'VALIDATION_ERROR', 'Invalid lang.');
    }

    return (int) $langId;
}

function me_unicode_length(string $value): int
{
    if ($value === '') {
        return 0;
    }
    $count = preg_match_all('/./u', $value, $m);
    if ($count !== false) {
        return $count;
    }
    return strlen($value);
}

function me_schema_info(PDO $pdo): array
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
           AND table_name IN ("profile","languages","competitor","privateleague","privateleaguemembers","notification","auth_refresh_tokens","extrapictures","trollanswers","trollpoints","votes","roster","transfers","teamresult","teamranking")'
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

function me_profile_competitors(PDO $pdo, int $profileId): array
{
    $stmt = $pdo->prepare(
        'SELECT competitor_id
         FROM competitor
         WHERE profile_id = :profile_id
         ORDER BY competitor_id ASC'
    );
    $stmt->execute([':profile_id' => $profileId]);
    return $stmt->fetchAll() ?: [];
}

function me_admin_privateleague_ids(PDO $pdo, array $schema, int $profileId): array
{
    $adminColumn = ($schema['privateleague.admin_profile_id'] ?? false) ? 'admin_profile_id' : 'admin';
    $stmt = $pdo->prepare(
        'SELECT privateleague_id
         FROM privateleague
         WHERE ' . $adminColumn . ' = :profile_id
         ORDER BY privateleague_id ASC'
    );
    $stmt->execute([':profile_id' => $profileId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $ids = [];
    foreach ($rows as $row) {
        $id = (int) $row;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function me_delete_rows_by_profile_id(PDO $pdo, array $schema, string $table, int $profileId): void
{
    if (!($schema[$table . '.profile_id'] ?? false)) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM ' . $table . '
         WHERE profile_id = :profile_id'
    );
    $stmt->execute([':profile_id' => $profileId]);
}

function me_delete_rows_by_ids(PDO $pdo, string $table, string $column, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) {
        return;
    }

    $bind = [];
    $params = [];
    foreach ($ids as $idx => $id) {
        if ($id <= 0) {
            continue;
        }
        $key = ':id' . $idx;
        $bind[] = $key;
        $params[$key] = $id;
    }

    if (empty($bind)) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM ' . $table . '
         WHERE ' . $column . ' IN (' . implode(',', $bind) . ')'
    );
    $stmt->execute($params);
}

function me_profile_row(PDO $pdo, int $profileId, array $schema): ?array
{
    $selectUpdated = ($schema['profile.updated_at'] ?? false) ? 'p.updated_at' : 'NULL AS updated_at';
    $selectCreated = ($schema['profile.created_at'] ?? false) ? 'p.created_at' : 'NULL AS created_at';
    $selectEmailVerified = ($schema['profile.email_verified_at'] ?? false)
        ? 'p.email_verified_at'
        : 'NULL AS email_verified_at';

    $stmt = $pdo->prepare(
        'SELECT
            p.profile_id,
            p.alias,
            p.email,
            p.lang_id,
            COALESCE(l.short, \'\') AS lang_short,
            ' . $selectCreated . ',
            ' . $selectUpdated . ',
            ' . $selectEmailVerified . '
         FROM profile p
         LEFT JOIN languages l ON l.lang_id = p.lang_id
         WHERE p.profile_id = :profile_id
         LIMIT 1'
    );
    $stmt->execute([':profile_id' => $profileId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if (($row['created_at'] ?? null) === null) {
        $row['created_at'] = gmdate('Y-m-d H:i:s');
    }

    return $row;
}

function me_etag_and_last_updated(array $profile, array $schema): array
{
    $profileId = (int) ($profile['profile_id'] ?? 0);

    if (($schema['profile.updated_at'] ?? false) && !empty($profile['updated_at'])) {
        $updatedRaw = (string) $profile['updated_at'];
        $ts = strtotime($updatedRaw);
        if ($ts !== false) {
            $marker = (string) $ts;
            $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $ts);
        } else {
            $marker = sha1($updatedRaw);
            $lastUpdated = gmdate('Y-m-d\TH:i:s\Z');
        }
    } else {
        $fallback = [
            (string) ($profile['alias'] ?? ''),
            (string) ($profile['email'] ?? ''),
            (string) ($profile['lang_id'] ?? ''),
            (string) ($profile['email_verified_at'] ?? ''),
        ];
        $marker = sha1(implode('|', $fallback));
        $ts = strtotime((string) ($profile['created_at'] ?? ''));
        $lastUpdated = gmdate('Y-m-d\TH:i:s\Z', $ts !== false ? $ts : time());
    }

    return [
        'etag' => 'W/"me-u' . $profileId . '-' . $marker . '"',
        'last_updated' => $lastUpdated,
    ];
}

function me_normalize_lang_short(string $value): string
{
    return strtolower(trim($value));
}

function me_delete_success(): void
{
    $now = gmdate('Y-m-d\TH:i:s\Z');
    echo json_encode([
        'meta' => [
            'server_time' => $now,
            'league_id' => null,
            'current_gw' => null,
            'last_updated' => $now,
            'etag' => null,
        ],
        'data' => [
            'ok' => true,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function me_if_none_match_raw(): string
{
    foreach (['HTTP_IF_NONE_MATCH', 'REDIRECT_HTTP_IF_NONE_MATCH', 'If-None-Match'] as $key) {
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string) $name) === 'if-none-match') {
                return trim((string) $value);
            }
        }
    }
    return '';
}

function me_if_none_match_matches(string $etag, string $ifNoneMatchRaw): bool
{
    if ($ifNoneMatchRaw === '') {
        return false;
    }

    $etagRaw = trim($etag);
    $etagWeak = preg_replace('/^W\//', '', $etagRaw) ?? $etagRaw;
    $etagNorm = trim($etagWeak, "\"' \t\r\n");

    foreach (array_map('trim', explode(',', $ifNoneMatchRaw)) as $candidate) {
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

    return false;
}

function me_authorization_header(): string
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

function me_require_auth_profile_id(): int
{
    $header = me_authorization_header();
    if ($header === '') {
        me_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = me_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function me_verify_jwt(string $token): array
{
    $secret = me_jwt_secret();
    if ($secret === '') {
        me_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) me_b64url_decode($h64), true);
    $payload = json_decode((string) me_b64url_decode($p64), true);
    $sig = me_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        me_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function me_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function me_jwt_secret(): string
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

function me_datetime_iso(string $value): string
{
    $ts = strtotime($value);
    if ($ts === false) {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}
