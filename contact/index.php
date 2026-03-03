<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        contact_error(404, 'BAD_REQUEST', 'Endpoint not found.');
    }

    $profileId = contact_require_auth_profile_id();
    $input = contact_json_input();

    $subject = contact_validate_subject($input);
    $message = contact_validate_message($input);
    $context = contact_validate_context($input);

    $logPayload = [
        'utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'profile_id' => $profileId,
        'subject' => $subject,
        'message' => $message,
        'context' => $context,
    ];
    error_log('CONTACT_MESSAGE ' . json_encode($logPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

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
} catch (Throwable $e) {
    contact_error(500, 'INTERNAL_ERROR', 'Unexpected server error.');
}

function contact_error(int $status, string $code, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES);
    exit;
}

function contact_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        contact_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        contact_error(400, 'BAD_REQUEST', 'Invalid payload.');
    }

    return $decoded;
}

function contact_validate_subject(array $input): string
{
    if (!array_key_exists('subject', $input)) {
        return '';
    }
    if (!is_string($input['subject'])) {
        contact_error(422, 'VALIDATION_ERROR', 'Invalid subject.');
    }

    $subject = trim($input['subject']);
    if (contact_unicode_length($subject) > 200) {
        contact_error(422, 'VALIDATION_ERROR', 'Invalid subject.');
    }
    return $subject;
}

function contact_validate_message(array $input): string
{
    if (!array_key_exists('message', $input) || !is_string($input['message'])) {
        contact_error(422, 'VALIDATION_ERROR', 'Invalid message.');
    }

    $message = trim($input['message']);
    $len = contact_unicode_length($message);
    if ($message === '' || $len > 4000) {
        contact_error(422, 'VALIDATION_ERROR', 'Invalid message.');
    }
    return $message;
}

function contact_validate_context(array $input): array
{
    if (!array_key_exists('context', $input)) {
        return [];
    }
    if (!is_array($input['context']) || contact_array_is_list($input['context'])) {
        contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
    }

    $safe = [];
    foreach ($input['context'] as $key => $value) {
        $k = trim((string) $key);
        if ($k === '') {
            contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
        }
        if (is_array($value) || is_object($value) || !is_scalar($value)) {
            contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
        }

        $kLower = strtolower($k);
        if (preg_match('/pass|pwd|password|otp|token|secret|auth/', $kLower)) {
            contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
        }

        if (is_string($value)) {
            $v = trim($value);
            if (preg_match('/bearer\s+/i', $v)) {
                contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
            }
            if (contact_unicode_length($v) > 200) {
                contact_error(422, 'VALIDATION_ERROR', 'Invalid context.');
            }
            $safe[$k] = $v;
            continue;
        }

        $safe[$k] = $value;
    }

    return $safe;
}

function contact_array_is_list(array $arr): bool
{
    $expected = 0;
    foreach ($arr as $k => $_) {
        if ($k !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

function contact_unicode_length(string $value): int
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

function contact_authorization_header(): string
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

function contact_require_auth_profile_id(): int
{
    $header = contact_authorization_header();
    if ($header === '') {
        contact_error(401, 'AUTH_REQUIRED', 'Authorization required.');
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $payload = contact_verify_jwt(trim($m[1]));
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub === '' || !ctype_digit($sub) || (int) $sub <= 0) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    return (int) $sub;
}

function contact_verify_jwt(string $token): array
{
    $secret = contact_jwt_secret();
    if ($secret === '') {
        contact_error(500, 'INTERNAL_ERROR', 'JWT secret is not configured.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    [$h64, $p64, $s64] = $parts;

    $header = json_decode((string) contact_b64url_decode($h64), true);
    $payload = json_decode((string) contact_b64url_decode($p64), true);
    $sig = contact_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $sig === null) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $expected = hash_hmac('sha256', "{$h64}.{$p64}", $secret, true);
    if (!hash_equals($expected, $sig)) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    $exp = $payload['exp'] ?? null;
    if (!is_int($exp) && !(is_string($exp) && ctype_digit($exp))) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }
    if ((int) $exp < time()) {
        contact_error(401, 'AUTH_INVALID_TOKEN', 'Invalid token.');
    }

    return $payload;
}

function contact_b64url_decode(string $input): ?string
{
    $pad = strlen($input) % 4;
    if ($pad > 0) {
        $input .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function contact_jwt_secret(): string
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
