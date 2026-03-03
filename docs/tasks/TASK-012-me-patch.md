# TASK-012 — Implement PATCH /me (Account profile update) (v1)

**Goal:** Update user profile fields: `alias`, `lang`.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **5.2 PATCH /me**
- `docs/spec/api-schemas-updated.md` → **PATCH /me — Request/Response**
- `docs/spec/api-errors-updated.md` → **Account (Bundle C) → PATCH /me**
- `docs/spec/core-rules-updated.md` → R10.9–R10.10 (alias/lang validation)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C, revalidate `/me`

## Endpoint
### PATCH /me
- Auth required (Bearer JWT)
- Category C:
  - `Cache-Control: no-store`
  - `meta.etag = null`

## Request
Partial:
```json
{ "alias": "NewAlias", "lang": "hu" }
```
Rules:
- at least one supported field present, else `400 BAD_REQUEST`
- alias validation per R10.9
- lang must exist in `languages.short`

## Response (success)
`data.ok=true` with meta fields and `etag:null`.

## Errors
- `400 BAD_REQUEST` (empty/invalid payload)
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `422 VALIDATION_ERROR` (alias/lang invalid)
- `500 INTERNAL_ERROR`

## Persistence
- update `profile.alias` if provided
- update `profile.lang_id` via languages lookup if provided
- ensure `profile.updated_at` changes

## Revalidation expectation
Client revalidates `GET /me` (and optionally `GET /home` if alias displayed).

## Implementation placement
Extend `me/index.php` from TASK-011 via method dispatch:
- GET existing
- PATCH new handler

## Smoke test
Create `scripts/me-patch-smoke.ps1`:
1) login → token
2) GET /me → capture alias + ETag
3) PATCH /me alias → expect 200 ok:true + no-store
4) GET /me → alias changed + ETag differs
5) PATCH {} → 400
6) no token → 401

## Acceptance
- PATCH updates alias/lang with validation
- Category C no-store + etag null
- /me ETag changes after update
- Smoke passes
