# Auth Model – Fantasy 9pin (Web + Mobile API)

This document defines the authentication and authorization model for the Fantasy 9pin API.
It is designed to support:
- the existing web application (PHP)
- the future mobile application
- shared API endpoints and consistent security rules

---

## 1. Goals

- Provide secure authentication for mobile clients.
- Keep the implementation simple for a small/medium user base.
- Ensure consistent behavior across web and mobile.
- Support OTP verification for signup (and optionally login/reset password).
- Avoid storing sensitive session state on the client beyond tokens.

---

## 2. Authentication Strategy

### 2.1 Mobile: Token-Based Auth (Recommended)

Mobile clients authenticate using **access tokens** and **refresh tokens**.

- **Access token**: short-lived, sent with each API request.
- **Refresh token**: longer-lived, used to obtain new access tokens.

#### Access Token
- Lifetime: **30 minutes** (recommended)
- Transport: `Authorization: Bearer <access_token>`

#### Refresh Token
- Lifetime: **30 days** (recommended)
- Stored securely on device (Keychain iOS / Keystore Android)
- Used only on `/auth/token/refresh`

### 2.2 Web: Keep Current Login, Add API Token Option Later

For the existing PHP web app:
- Keep current session-based login initially.
- Optionally, migrate web pages to also use the token endpoints later.

This allows phased migration without rewriting everything at once.

---

## 3. Token Format

### 3.1 JWT vs Opaque Tokens

**Option A: JWT access tokens**
- Server can validate without DB lookup (signature verification).
- Token includes claims (profile_id, expiry).
- Refresh token remains server-tracked.

**Option B: Opaque tokens**
- Access token is random string; server validates via DB lookup each request.
- Slightly simpler conceptually but adds DB load and state.

**Usage:** JWT for access token, opaque refresh token.

---

## 4. Token Claims (JWT Access Token)

The access token should include minimal claims:

- `sub`: profile_id
- `exp`: expiry timestamp
- `iat`: issued-at timestamp
- `ver`: token version (optional; helps invalidate old tokens if needed)

Example:
```json
{
  "sub": 1001,
  "exp": 1706901000,
  "iat": 1706899200,
  "ver": 1
}


5. Refresh Token Storage and Rotation
5.1 Server Storage

Refresh tokens should be stored server-side in a new table, e.g.:

auth_refresh_tokens
    refresh_token_id
    profile_id
    token_hash
    created_at
    expires_at
    revoked_at (nullable)
    device_name (optional)
    last_used_at (optional)
    ip_hash (optional)

Store hash(refresh_token), not the raw token.

5.2 Rotation (Recommended)

On each refresh:
    invalidate the old refresh token
    issue a new refresh token

This reduces risk if a refresh token leaks.

6. Endpoints
6.1 Register

POST /auth/register
Creates profile (unverified) and sends OTP.

6.2 OTP verify

POST /auth/otp/verify
If purpose=register:
    marks profile verified
    optionally returns access+refresh tokens immediately (recommended UX)

6.3 Login

POST /auth/login
    Requires correct password
    If profile not verified → return AUTH_EMAIL_NOT_VERIFIED
    Returns access+refresh tokens

6.4 Refresh

POST /auth/token/refresh
    Requires refresh token
    Returns new access token and (if rotating) a new refresh token

6.5 Logout

POST /auth/logout
    Revokes the refresh token

7. Authorization (Permissions)

Authorization is enforced server-side for every request.

7.1 Ownership rules

A user may only access/modify:
    their own profile
    their own competitors
    leagues they participate in (or public league data where allowed)

Violations:
    return LEAGUE_ACCESS_DENIED or AUTH_FORBIDDEN (403)

7.2 Admin rules (private leagues)

Only private league admins can:
    accept/deny membership requests
    invite/remove members (if defined)

Violations:
    return INVITE_NOT_ALLOWED (403) or AUTH_FORBIDDEN

8. Authentication Requirements by Endpoint Type
8.1 Public endpoints (no auth)

/rules (if you serve rules JSON publicly)
optional /news (if you want public news; otherwise keep user-auth)

8.2 Requires auth

Everything else requires authentication.

If missing/invalid token:
    return AUTH_REQUIRED or AUTH_INVALID_TOKEN (401)

9. Client-Side Behavior
9.1 Request flow

For each API request:
    send Authorization: Bearer <access_token>

If response is 401 with AUTH_INVALID_TOKEN:
    call /auth/token/refresh using refresh token
    retry the original request once
    if refresh fails → force login

9.2 Storage

Access token: memory (preferred) or short-lived storage
Refresh token: secure storage only (Keychain/Keystore)

10. OTP Policy Integration

OTP enforcement follows core-rules.md:
    expiry 10 minutes
    retry limit 5 attempts
    resend escalation 60s / 120s / 300s

OTP errors use:
    OTP_EXPIRED
    OTP_INVALID
    OTP_RETRY_LIMIT
    OTP_RESEND_COOLDOWN

11. Security Considerations (v1)

Always use HTTPS.
Hash stored refresh tokens.
Rate-limit:
    login attempts
    OTP send/verify
    contact messages
Use constant-time comparisons for secrets where possible.
Prefer short access token lifetime.

12.  Decisions 

Should the API allow multiple devices simultaneously? -- YES
