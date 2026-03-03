# TASK-020 — Implement GET /leagues/{league_id}/matches/{match_id} (Match detail) (v1)

Category: **A** (ETag + 304)

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.2 GET /leagues/{league_id}/matches/{match_id}**
- `docs/spec/api-schemas-updated.md` → **Matches → Match detail**
- `docs/spec/api-errors-updated.md` → match detail errors
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **Match**

## Endpoint
### GET /leagues/{league_id}/matches/{match_id}
- Auth required
- Category A: private,must-revalidate + ETag + 304
- Validate match belongs to league_id (else `404 MATCH_NOT_FOUND`)

## Response
Per schema (header + result breakdown, optional per-player lines)
If per-player lines are not in DB, return empty arrays if schema allows.

## Errors
- 401 auth errors
- 404 LEAGUE_NOT_FOUND
- 404 MATCH_NOT_FOUND
- 500 internal

## ETag
Marker based on status + result fields (+ updated_at if present)
ETag: `W/"match-{league_id}-{match_id}-{hash}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/matches/([0-9]+)$ -> leagues/matches/detail/index.php?league_id=$1&match_id=$2 [QSA,L]`
- handler: `leagues/matches/detail/index.php`

## Smoke
`scripts/match-detail-smoke.ps1`:
1) login → token
2) list matches, pick first match_id; if none SKIP
3) GET detail → 200 + ETag, then If-None-Match → 304
4) invalid match → 404
5) no token → 401
