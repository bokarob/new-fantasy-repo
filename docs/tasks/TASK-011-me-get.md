# TASK-011 — Implement GET /me (Account profile payload) (v1)

**Goal:** Implement the authenticated user profile payload for the Account hub / Profile / Settings screens.

## Source of truth (must follow)
- `docs/spec/phase-c-api-contracts.md` → **5.1 GET /me**
- `docs/spec/api-schemas-updated.md` → **GET /me — Response** + `Me` schema
- `docs/spec/api-errors-updated.md` → **Account (Bundle C) → GET /me**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **User**
- `docs/spec/core-rules-updated.md` → R10.* (profile fields)

No schema/contract changes in this task.

## Endpoint
### GET /me
- Auth: required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- Caching: Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → `304 Not Modified` (no body)
- ETag scope: **User**

## Response shape (must match schema)
- `meta.server_time` ISO-8601 UTC
- `meta.league_id = null`, `meta.current_gw = null`
- `meta.last_updated` ISO-8601 UTC
- `meta.etag` equals ETag header value
- `data.me` fields per schema (`profile_id`, `alias`, `email`, `lang`, `created_at`)
- `lang` maps `profile.lang_id -> languages.short`

`created_at`:
- Use `profile.created_at` if exists.
- If not present, add DB column (recommended below).

## Errors (standard error envelope)
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `500 INTERNAL_ERROR`

## DB prerequisite (recommended)
If `profile.created_at` does not exist:
```sql
ALTER TABLE profile
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
```

## ETag marker (Category A)
Preferred:
- marker uses `profile.updated_at` (if exists)
Fallback:
- hash of `alias|email|lang_id|email_verified_at`
ETag example: `W/"me-u{profile_id}-{marker}"`

## Routing / file placement
- Root `.htaccess` rule: `^me$ -> me/index.php [QSA,L]` (+ `DirectorySlash Off` if needed)
- Handler: `me/index.php` (GET only in this task)

## Smoke test
Create `scripts/me-smoke.ps1` (curl-based):
1) login → token
2) GET /me → expect 200 + Cache-Control + ETag
3) repeat with If-None-Match → 304
4) no token → 401 AUTH_REQUIRED

## Acceptance criteria
- Endpoint reachable: GET /me
- Category A caching works (ETag + 304)
- Response matches schema keys/types
- Smoke script passes locally
