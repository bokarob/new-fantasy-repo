# TASK-013 — Implement GET /me/teams (Your teams list) (v1)

**Goal:** List the user’s teams across leagues (Profile → Your Teams).

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **5.3 GET /me/teams**
- `docs/spec/api-schemas-updated.md` → **GET /me/teams — Response** + `MeTeamItem`
- `docs/spec/api-errors-updated.md` → **Account (Bundle C) → GET /me/teams**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **User**

## Endpoint
### GET /me/teams
- Auth required
- Category A:
  - `Cache-Control: private, must-revalidate`
  - `ETag` and `If-None-Match` → 304

## Response
`data.teams[]` items include:
- `league` (league_id, name, logo_url)
- `competitor` (competitor_id, teamname, created_at)

League name mapping:
- if DB uses ``leagues`.`league name`` alias it to `name`.

created_at:
- if `competitor.created_at` exists, use it
- else add DB column (recommended below)

## Errors
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `500 INTERNAL_ERROR`

## DB prerequisite (recommended)
If `competitor.created_at` does not exist:
```sql
ALTER TABLE competitor
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
```

## ETag marker
Prefer:
- marker uses `MAX(competitor.updated_at)` for the user + `COUNT(*)`
Fallback:
- hash of concatenated competitor ids/teamnames.

## Routing
- Root rule: `^me/teams$ -> me/teams/index.php [QSA,L]`
- Handler: `me/teams/index.php`

## Smoke test
`scripts/me-teams-smoke.ps1`:
1) login → token
2) GET /me/teams → 200 + ETag
3) If-None-Match → 304
4) no token → 401

## Acceptance
- Endpoint reachable
- Category A caching works
- Schema-compliant response
- Smoke passes
