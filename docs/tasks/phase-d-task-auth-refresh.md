# Phase D Coding Task Packet — Auth + Refresh Token Flow (v1)

**Goal:** Implement the v1 auth endpoints with **JWT access tokens** + **rotating opaque refresh tokens** stored in `auth_refresh_tokens`.

This task unlocks all authenticated Phase B/C endpoints.

---

## 0) Inputs (source of truth)

Use these project docs (do not invent behavior beyond them):
- `auth-model.md` — token strategy, rotation, endpoint list, client retry behavior
- `api-schemas-updated.md` — exact request/response shapes for auth endpoints + “Cache-Control: no-store”
- `api-errors-updated.md` — auth/OTP error codes and HTTP status conventions
- `core-rules-updated.md` — OTP policy (expiry 10m, retry limit 5, resend cooldown escalation 60/120/300)

DB tables/columns are already migrated:
- `auth_refresh_tokens` (hash stored, never raw token)
- `profile` has: `email_verified_at`, `otp_hash`, `otp_expires_at`, `otp_attempts`, `otp_last_sent_at`, `otp_resend_count`, `otp_purpose`

---

## 1) Endpoints to implement (v1)

All endpoints must return:
- `Cache-Control: no-store`
- JSON body with `{ meta, data }` on success
- standard `{ error: { code, message, rule?, details? } }` on failure

### Auth endpoints (from schemas)
- `POST /auth/register`
- `POST /auth/otp/send`
- `POST /auth/otp/verify`
- `POST /auth/login`
- `POST /auth/token/refresh`
- `POST /auth/logout`
- `POST /auth/password/forgot`
- `POST /auth/password/reset`

---

## 2) Token rules (implementation decisions consistent with docs)

### Access token (JWT)
- Lifetime: **1800 seconds (30 minutes)**
- Claims (minimal):
  - `sub` = profile_id
  - `iat`, `exp`
  - optional `ver` (token version) if you want future invalidation

**Signing:**
- Use an environment secret (e.g. `JWT_SECRET`)
- HS256 is fine for v1 simplicity

### Refresh token (opaque)
- Lifetime: **30 days**
- Generate a cryptographically secure random token (e.g. 32+ bytes), encode base64url
- Store **SHA-256 hash** only:
  - `token_hash` = 32-byte hash
- Rotation (required):
  - every refresh invalidates the old refresh token and issues a new one

### Logout
- Revokes the presented refresh token (sets `revoked_at`)

---

## 3) OTP rules (from Core Rules)

- OTP expiry: **10 minutes**
- Retry limit: **5 wrong attempts**
- Resend cooldown escalation based on `otp_resend_count`:
  - first resend: 60s
  - second: 120s
  - third+: 300s

**Storage:**
- Store OTP as `otp_hash` (SHA-256 of the numeric code, optionally salted)
- Store `otp_purpose` (`register` or `reset`), `otp_expires_at`, and counters

**Security note:** avoid leaking whether an email exists on “forgot password” (recommended).

---

## 4) Error mapping (must match api-errors-updated.md)

### Login
- Wrong credentials → **401 AUTH_INVALID_CREDENTIALS**
- Email not verified → **403 AUTH_EMAIL_NOT_VERIFIED**

### Refresh / Logout
- Missing/unknown/expired/revoked refresh token → **401 AUTH_INVALID_TOKEN**

### OTP
- Expired → **409 OTP_EXPIRED**
- Incorrect → **422 OTP_INVALID** (and increment attempts)
- Attempts exceeded → **429 OTP_RETRY_LIMIT**
- Resend too soon → **429 OTP_RESEND_COOLDOWN**

---

## 5) DB operations (reference implementation)

### Helper: hash refresh token
- `token_hash = SHA256(raw_refresh_token_bytes)` → 32 bytes

### Helper: create refresh token row
Insert:
- `profile_id`
- `token_hash`
- `expires_at = NOW() + INTERVAL 30 DAY`
- optional: `device_name`, `ip_hash`
- optional: `last_used_at = NOW()`

### POST /auth/token/refresh (rotation)
**Algorithm (transaction):**
1) Hash provided refresh token
2) Select row by `token_hash`
   - must exist
   - must have `revoked_at IS NULL`
   - must have `expires_at > NOW()`
3) In a single transaction:
   - Update old row: set `revoked_at = NOW()`, `last_used_at = NOW()`
     - condition must include `revoked_at IS NULL` so it’s single-use even under concurrency
   - Insert new refresh token row for same `profile_id`
4) Return new access token + new refresh token

If update affected rows != 1 → treat as invalid (401 AUTH_INVALID_TOKEN)

### POST /auth/logout
- Hash refresh token
- `UPDATE auth_refresh_tokens SET revoked_at=NOW() WHERE token_hash=? AND revoked_at IS NULL`
- If no row updated → 401 AUTH_INVALID_TOKEN
- Else → `{ status: "logged_out" }`

### Password reset best practice (recommended)
When password changes, revoke all active refresh tokens for that profile:
- `UPDATE auth_refresh_tokens SET revoked_at=NOW() WHERE profile_id=? AND revoked_at IS NULL`

---

## 6) Endpoint-specific behavior

### POST /auth/register
- Create profile if email not already used
- Store password hash (bcrypt/argon2)
- Set `email_verified_at = NULL`
- Generate OTP with purpose `register`, store OTP fields, send email
- Response: `{ status: "otp_sent", email }`

### POST /auth/otp/send
- Purpose is `register` or `reset`
- Enforce resend cooldown using `otp_last_sent_at` and `otp_resend_count`
- Generate and store new OTP, increment `otp_resend_count`
- Response: `{ status: "otp_sent" }`

### POST /auth/otp/verify
- Validate OTP purpose + expiry + attempts
- On success:
  - set `email_verified_at = NOW()` (for register)
  - clear OTP fields (hash/expires/purpose), reset counters
  - **return tokens** (recommended by schema)

### POST /auth/login
- Validate password
- Enforce verified requirement
- Issue tokens + store refresh token row

### POST /auth/password/forgot
- Generate reset OTP (purpose `reset`)
- **Always return** `{ status: "otp_sent" }` even if email not found (recommended)

### POST /auth/password/reset
- Verify OTP purpose `reset`
- Update password hash
- Clear OTP fields
- Revoke all refresh tokens for profile (recommended)

---

## 7) Acceptance criteria

- Responses exactly match `api-schemas-updated.md` auth schemas:
  - `meta.server_time`
  - `data.tokens.access_token`, `access_expires_in_seconds` = 1800
  - `refresh_expires_in_seconds` = 2592000
- All auth endpoints set `Cache-Control: no-store`
- Refresh token rotation is atomic and old tokens become unusable immediately
- Error codes/statuses match `api-errors-updated.md`
- OTP policy matches Core Rules (expiry, retry, cooldown escalation)

---

## 8) Smoke tests (minimum)

1) Register → otp_sent
2) OTP verify (register) → verified + tokens
3) Login → tokens
4) Refresh → tokens (new refresh differs; old refresh now invalid)
5) Logout → logged_out; refresh thereafter fails
6) OTP invalid increments attempts; attempt #6 returns OTP_RETRY_LIMIT
7) OTP resend cooldown enforced 60/120/300 seconds

---

## 9) Notes for implementers

- Never log raw refresh tokens or OTP codes
- Use TLS only (prod)
- Prefer rate limiting login attempts (optional)
- Keep signing secrets in env vars (never commit)

