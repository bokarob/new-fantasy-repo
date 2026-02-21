<?php

declare(strict_types=1);

const ACCESS_TTL_SECONDS = 1800;
const REFRESH_TTL_SECONDS = 2592000;
const OTP_TTL_SECONDS = 600;
const OTP_RETRY_LIMIT = 5;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = auth_db();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/auth', PHP_URL_PATH);
    $route = trim($path, '/');
    $authPos = strpos($path, '/auth/');
    if ($authPos !== false) {
        $route = ltrim(substr($path, $authPos + 1), '/');
    } elseif (substr($path, -5) === '/auth' || $path === 'auth') {
        $route = 'auth';
    }

    if ($method !== 'POST') {
        auth_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    switch ($route) {
        case 'auth/register':
            auth_register($pdo, auth_json_input());
            break;
        case 'auth/otp/send':
            auth_otp_send($pdo, auth_json_input());
            break;
        case 'auth/otp/verify':
            auth_otp_verify($pdo, auth_json_input());
            break;
        case 'auth/login':
            auth_login($pdo, auth_json_input());
            break;
        case 'auth/token/refresh':
            auth_token_refresh($pdo, auth_json_input());
            break;
        case 'auth/logout':
            auth_logout($pdo, auth_json_input());
            break;
        case 'auth/password/forgot':
            auth_password_forgot($pdo, auth_json_input());
            break;
        case 'auth/password/reset':
            auth_password_reset($pdo, auth_json_input());
            break;
        default:
            auth_error(404, 'BAD_REQUEST', 'Endpoint not found.');
            break;
    }
} catch (Throwable $e) {
    auth_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function auth_db(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'fantasy_app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function auth_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        auth_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        auth_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    return $decoded;
}

function auth_success(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'meta' => ['server_time' => gmdate('Y-m-d\TH:i:s\Z')],
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function auth_error(int $status, string $code, string $message, ?string $rule = null, ?array $details = null): void
{
    http_response_code($status);

    $error = [
        'code' => $code,
        'message' => $message,
    ];
    if ($rule !== null) {
        $error['rule'] = $rule;
    }
    if ($details !== null) {
        $error['details'] = $details;
    }

    echo json_encode(['error' => $error], JSON_UNESCAPED_SLASHES);
    exit;
}

function auth_canonical_email(?string $email): string
{
    $value = strtolower(trim((string) $email));
    if ($value === '' || strpos($value, '@') === false) {
        auth_error(400, 'BAD_REQUEST', 'Invalid email.');
    }
    return $value;
}

function auth_required_string(array $input, string $field): string
{
    $value = trim((string) ($input[$field] ?? ''));
    if ($value === '') {
        auth_error(400, 'BAD_REQUEST', "Missing field: {$field}.");
    }
    return $value;
}

function auth_profile_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM profile WHERE UPPER(email) = UPPER(:email) LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function auth_lang_id(PDO $pdo, ?string $lang): int
{
    $candidate = strtolower(trim((string) $lang));
    if ($candidate !== '') {
        $stmt = $pdo->prepare('SELECT lang_id FROM languages WHERE short = :short LIMIT 1');
        $stmt->execute([':short' => $candidate]);
        $row = $stmt->fetch();
        if ($row && isset($row['lang_id'])) {
            return (int) $row['lang_id'];
        }
    }

    $stmt = $pdo->query('SELECT lang_id FROM languages ORDER BY lang_id ASC LIMIT 1');
    $row = $stmt->fetch();
    if ($row && isset($row['lang_id'])) {
        return (int) $row['lang_id'];
    }

    return 1;
}

function auth_password_hash_legacy(string $plain, string $storedEmail): string
{
    return md5($plain . $storedEmail);
}

function auth_otp_hash(string $otp): string
{
    return hash('sha256', $otp, true);
}

function auth_refresh_hash(string $token): string
{
    return hash('sha256', $token, true);
}

function auth_base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function auth_jwt(array $payload, string $secret): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $h = auth_base64url(json_encode($header, JSON_UNESCAPED_SLASHES));
    $p = auth_base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', "{$h}.{$p}", $secret, true);
    return "{$h}.{$p}." . auth_base64url($sig);
}

function auth_issue_tokens(PDO $pdo, int $profileId): array
{
    $secret = getenv('JWT_SECRET') ?: '';
    if ($secret === '') {
        auth_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $now = time();
    $access = auth_jwt([
        'sub' => (string) $profileId,
        'iat' => $now,
        'exp' => $now + ACCESS_TTL_SECONDS,
    ], $secret);

    $refresh = auth_base64url(random_bytes(32));
    $refreshHash = auth_refresh_hash($refresh);

    $stmt = $pdo->prepare(
        'INSERT INTO auth_refresh_tokens (profile_id, token_hash, expires_at, last_used_at) 
         VALUES (:profile_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY), UTC_TIMESTAMP())'
    );
    $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_INT);
    $stmt->bindValue(':token_hash', $refreshHash, PDO::PARAM_LOB);
    $stmt->execute();

    return [
        'access_token' => $access,
        'access_expires_in_seconds' => ACCESS_TTL_SECONDS,
        'refresh_token' => $refresh,
        'refresh_expires_in_seconds' => REFRESH_TTL_SECONDS,
    ];
}

function auth_cooldown_seconds(int $resendCount): int
{
    if ($resendCount <= 0) {
        return 60;
    }
    if ($resendCount === 1) {
        return 120;
    }
    return 300;
}

function auth_store_otp(PDO $pdo, int $profileId, string $purpose, bool $isResend): string
{
    $otp = null;
    $appEnv = strtolower((string) (getenv('APP_ENV') ?: ''));
    if ($appEnv === 'local') {
        $fixedOtp = getenv('AUTH_FIXED_OTP');
        if (is_string($fixedOtp) && preg_match('/^\d{6}$/', $fixedOtp)) {
            $otp = $fixedOtp;
        }
    }
    if ($otp === null) {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    $hash = auth_otp_hash($otp);

    $stmt = $pdo->prepare('SELECT otp_purpose, otp_last_sent_at, otp_resend_count FROM profile WHERE profile_id = :profile_id');
    $stmt->execute([':profile_id' => $profileId]);
    $state = $stmt->fetch() ?: ['otp_purpose' => null, 'otp_last_sent_at' => null, 'otp_resend_count' => 0];

    $currentPurpose = (string) ($state['otp_purpose'] ?? '');
    $lastSentAt = $state['otp_last_sent_at'] ?? null;
    $resendCount = (int) ($state['otp_resend_count'] ?? 0);

    if ($currentPurpose !== $purpose) {
        $resendCount = 0;
        $lastSentAt = null;
    }

    if ($isResend && $lastSentAt !== null) {
        $cooldown = auth_cooldown_seconds($resendCount);
        $elapsed = time() - strtotime((string) $lastSentAt);
        if ($elapsed < $cooldown) {
            auth_error(429, 'OTP_RESEND_COOLDOWN', 'OTP resend requested too soon.', 'R10.6', [
                'retry_after_seconds' => $cooldown - $elapsed,
            ]);
        }
        $resendCount++;
    } elseif (!$isResend) {
        $resendCount = 0;
    }

    $update = $pdo->prepare(
        'UPDATE profile
         SET otp_hash = :otp_hash,
             otp_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE),
             otp_attempts = 0,
             otp_last_sent_at = UTC_TIMESTAMP(),
             otp_resend_count = :otp_resend_count,
             otp_purpose = :otp_purpose
         WHERE profile_id = :profile_id'
    );
    $update->bindValue(':otp_hash', $hash, PDO::PARAM_LOB);
    $update->bindValue(':otp_resend_count', $resendCount, PDO::PARAM_INT);
    $update->bindValue(':otp_purpose', $purpose, PDO::PARAM_STR);
    $update->bindValue(':profile_id', $profileId, PDO::PARAM_INT);
    $update->execute();

    return $otp;
}

function auth_send_otp_email(string $email, string $purpose, string $otp): void
{
    $subject = $purpose === 'reset' ? 'Password reset code' : 'Verification code';
    $body = "Your {$purpose} OTP code is: {$otp}. It expires in 10 minutes.";
    @mail($email, $subject, $body);
}

function auth_clear_otp(PDO $pdo, int $profileId): void
{
    $stmt = $pdo->prepare(
        'UPDATE profile
         SET otp_hash = NULL,
             otp_expires_at = NULL,
             otp_attempts = 0,
             otp_last_sent_at = NULL,
             otp_resend_count = 0,
             otp_purpose = NULL
         WHERE profile_id = :profile_id'
    );
    $stmt->execute([':profile_id' => $profileId]);
}

function auth_validate_otp(PDO $pdo, array $profile, string $otpInput, string $purpose): void
{
    $attempts = (int) ($profile['otp_attempts'] ?? 0);
    if ($attempts >= OTP_RETRY_LIMIT) {
        auth_error(429, 'OTP_RETRY_LIMIT', 'Too many incorrect OTP attempts.', 'R10.5');
    }

    if (($profile['otp_purpose'] ?? null) !== $purpose || empty($profile['otp_hash'])) {
        auth_error(422, 'OTP_INVALID', 'OTP code is incorrect.');
    }

    $expiresAt = $profile['otp_expires_at'] ?? null;
    if ($expiresAt === null || strtotime((string) $expiresAt) <= time()) {
        auth_error(409, 'OTP_EXPIRED', 'OTP code has expired.', 'R10.4');
    }

    $candidate = auth_otp_hash($otpInput);
    $stored = (string) $profile['otp_hash'];
    if (!hash_equals($stored, $candidate)) {
        $inc = $pdo->prepare('UPDATE profile SET otp_attempts = otp_attempts + 1 WHERE profile_id = :profile_id');
        $inc->execute([':profile_id' => (int) $profile['profile_id']]);
        auth_error(422, 'OTP_INVALID', 'OTP code is incorrect.');
    }
}

function auth_register(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $password = auth_required_string($input, 'password');
    $alias = trim((string) ($input['alias'] ?? ''));
    $alias = $alias !== '' ? $alias : explode('@', $email)[0];
    $profilename = $alias;
    $langId = auth_lang_id($pdo, (string) ($input['lang'] ?? ''));

    if (auth_profile_by_email($pdo, $email) !== null) {
        auth_error(409, 'STATE_CONFLICT', 'Email is already registered.');
    }

    $passwordHash = auth_password_hash_legacy($password, $email);
    $stmt = $pdo->prepare(
        'INSERT INTO profile (email, password, profilename, alias, lang_id, picture_id, authorization, email_verified_at)
         VALUES (:email, :password, :profilename, :alias, :lang_id, 1, 1, NULL)'
    );
    $stmt->execute([
        ':email' => $email,
        ':password' => $passwordHash,
        ':profilename' => $profilename,
        ':alias' => $alias,
        ':lang_id' => $langId,
    ]);

    $profileId = (int) $pdo->lastInsertId();
    $otp = auth_store_otp($pdo, $profileId, 'register', false);
    auth_send_otp_email($email, 'register', $otp);

    auth_success([
        'status' => 'otp_sent',
        'email' => $email,
    ]);
}

function auth_otp_send(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $purpose = trim((string) ($input['purpose'] ?? ''));
    if (!in_array($purpose, ['register', 'reset'], true)) {
        auth_error(400, 'BAD_REQUEST', 'Invalid purpose.');
    }

    $profile = auth_profile_by_email($pdo, $email);
    if ($profile === null) {
        auth_error(422, 'OTP_INVALID', 'OTP cannot be sent.');
    }

    $otp = auth_store_otp($pdo, (int) $profile['profile_id'], $purpose, true);
    auth_send_otp_email((string) $profile['email'], $purpose, $otp);

    auth_success(['status' => 'otp_sent']);
}

function auth_otp_verify(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $otp = auth_required_string($input, 'otp');
    $purpose = trim((string) ($input['purpose'] ?? ''));
    if (!in_array($purpose, ['register', 'reset'], true)) {
        auth_error(400, 'BAD_REQUEST', 'Invalid purpose.');
    }

    $profile = auth_profile_by_email($pdo, $email);
    if ($profile === null) {
        auth_error(422, 'OTP_INVALID', 'OTP code is incorrect.');
    }

    auth_validate_otp($pdo, $profile, $otp, $purpose);

    if ($purpose === 'register') {
        $update = $pdo->prepare('UPDATE profile SET email_verified_at = UTC_TIMESTAMP() WHERE profile_id = :profile_id');
        $update->execute([':profile_id' => (int) $profile['profile_id']]);
    }

    auth_clear_otp($pdo, (int) $profile['profile_id']);
    $tokens = auth_issue_tokens($pdo, (int) $profile['profile_id']);

    auth_success([
        'status' => 'verified',
        'tokens' => $tokens,
    ]);
}

function auth_login(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $password = auth_required_string($input, 'password');

    $profile = auth_profile_by_email($pdo, $email);
    if ($profile === null) {
        auth_error(401, 'AUTH_INVALID_CREDENTIALS', 'Invalid email or password.');
    }

    $storedEmail = (string) $profile['email'];
    $candidate = auth_password_hash_legacy($password, $storedEmail);
    $storedHash = (string) ($profile['password'] ?? '');
    if (!hash_equals($storedHash, $candidate)) {
        auth_error(401, 'AUTH_INVALID_CREDENTIALS', 'Invalid email or password.');
    }

    if (empty($profile['email_verified_at'])) {
        auth_error(403, 'AUTH_EMAIL_NOT_VERIFIED', 'Email is not verified.', 'R10.1');
    }

    $tokens = auth_issue_tokens($pdo, (int) $profile['profile_id']);
    auth_success(['tokens' => $tokens]);
}

function auth_token_refresh(PDO $pdo, array $input): void
{
    $refreshToken = auth_required_string($input, 'refresh_token');
    $hash = auth_refresh_hash($refreshToken);

    try {
        $pdo->beginTransaction();

        $select = $pdo->prepare(
            'SELECT refresh_token_id, profile_id, revoked_at, expires_at
             FROM auth_refresh_tokens
             WHERE token_hash = :token_hash
             LIMIT 1
             FOR UPDATE'
        );
        $select->bindValue(':token_hash', $hash, PDO::PARAM_LOB);
        $select->execute();
        $row = $select->fetch();

        if (!$row || $row['revoked_at'] !== null || strtotime((string) $row['expires_at']) <= time()) {
            $pdo->rollBack();
            auth_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
        }

        $update = $pdo->prepare(
            'UPDATE auth_refresh_tokens
             SET revoked_at = UTC_TIMESTAMP(), last_used_at = UTC_TIMESTAMP()
             WHERE refresh_token_id = :id AND revoked_at IS NULL'
        );
        $update->execute([':id' => (int) $row['refresh_token_id']]);

        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            auth_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
        }

        $secret = getenv('JWT_SECRET') ?: '';
        if ($secret === '') {
            $pdo->rollBack();
            auth_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
        }

        $now = time();
        $access = auth_jwt([
            'sub' => (string) ((int) $row['profile_id']),
            'iat' => $now,
            'exp' => $now + ACCESS_TTL_SECONDS,
        ], $secret);

        $newRefresh = auth_base64url(random_bytes(32));
        $newHash = auth_refresh_hash($newRefresh);

        $insert = $pdo->prepare(
            'INSERT INTO auth_refresh_tokens (profile_id, token_hash, expires_at, last_used_at)
             VALUES (:profile_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY), UTC_TIMESTAMP())'
        );
        $insert->bindValue(':profile_id', (int) $row['profile_id'], PDO::PARAM_INT);
        $insert->bindValue(':token_hash', $newHash, PDO::PARAM_LOB);
        $insert->execute();

        $pdo->commit();

        auth_success([
            'tokens' => [
                'access_token' => $access,
                'access_expires_in_seconds' => ACCESS_TTL_SECONDS,
                'refresh_token' => $newRefresh,
                'refresh_expires_in_seconds' => REFRESH_TTL_SECONDS,
            ],
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        auth_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
    }
}

function auth_logout(PDO $pdo, array $input): void
{
    $refreshToken = auth_required_string($input, 'refresh_token');
    $hash = auth_refresh_hash($refreshToken);

    $stmt = $pdo->prepare(
        'UPDATE auth_refresh_tokens
         SET revoked_at = UTC_TIMESTAMP(), last_used_at = UTC_TIMESTAMP()
         WHERE token_hash = :token_hash
           AND revoked_at IS NULL
           AND expires_at > UTC_TIMESTAMP()'
    );
    $stmt->bindValue(':token_hash', $hash, PDO::PARAM_LOB);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        auth_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    auth_success(['status' => 'logged_out']);
}

function auth_password_forgot(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $profile = auth_profile_by_email($pdo, $email);
    if ($profile !== null) {
        $otp = auth_store_otp($pdo, (int) $profile['profile_id'], 'reset', true);
        auth_send_otp_email((string) $profile['email'], 'reset', $otp);
    }

    auth_success(['status' => 'otp_sent']);
}

function auth_password_reset(PDO $pdo, array $input): void
{
    $email = auth_canonical_email($input['email'] ?? null);
    $otp = auth_required_string($input, 'otp');
    $newPassword = auth_required_string($input, 'new_password');

    $profile = auth_profile_by_email($pdo, $email);
    if ($profile === null) {
        auth_error(422, 'OTP_INVALID', 'OTP code is incorrect.');
    }

    auth_validate_otp($pdo, $profile, $otp, 'reset');

    $newHash = auth_password_hash_legacy($newPassword, (string) $profile['email']);
    $updatePw = $pdo->prepare('UPDATE profile SET password = :password WHERE profile_id = :profile_id');
    $updatePw->execute([
        ':password' => $newHash,
        ':profile_id' => (int) $profile['profile_id'],
    ]);

    auth_clear_otp($pdo, (int) $profile['profile_id']);

    $revokeAll = $pdo->prepare(
        'UPDATE auth_refresh_tokens
         SET revoked_at = UTC_TIMESTAMP(), last_used_at = UTC_TIMESTAMP()
         WHERE profile_id = :profile_id
           AND revoked_at IS NULL
           AND expires_at > UTC_TIMESTAMP()'
    );
    $revokeAll->execute([':profile_id' => (int) $profile['profile_id']]);

    auth_success(['status' => 'password_reset']);
}
