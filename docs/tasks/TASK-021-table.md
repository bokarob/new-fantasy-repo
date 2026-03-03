# TASK-021 — Implement GET /leagues/{league_id}/table (League table) (v1)

Category: **A** (ETag + 304 when available)

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.3 GET /leagues/{league_id}/table**
- `docs/spec/api-schemas-updated.md` → **Table → GET /leagues/{league_id}/table**
- `docs/spec/api-errors-updated.md` → table errors
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A
- `docs/spec/core-rules-updated.md` → points logic refs

## Endpoint
### GET /leagues/{league_id}/table?gw={gw?}
- Auth required
- Category A: private,must-revalidate + ETag + 304
- gw optional; default current_gw
- If no table computed, return `409 TABLE_NOT_AVAILABLE` (preferred)

## Response
Per schema:
- league_id, gw
- items[] standings rows (team_id, short, name, played, win/draw/loss, team_points, match_points, set_points)
- last_update_at if schema includes

## Mapping
Prefer snapshot table `leaguetable` (league_id, gameweek, team_id, win/draw/loss, points...).
If empty:
- return 409 TABLE_NOT_AVAILABLE (do not attempt heavy recompute in v1 unless already implemented).

## Errors
- 401 auth errors
- 404 LEAGUE_NOT_FOUND
- 404 GAMEWEEK_NOT_FOUND (if gw not present)
- 409 TABLE_NOT_AVAILABLE
- 500 internal

## ETag
From leaguetable rows: count + max(updated_at) if exists else hash of sums
ETag: `W/"table-l{league_id}-gw{gw}-{hash}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/table$ -> leagues/table/index.php?league_id=$1 [QSA,L]`
- handler: `leagues/table/index.php`

## Smoke
`scripts/table-smoke.ps1`:
1) login → token
2) GET table → if 200 validate ETag; if 409 accept PASS-with-note
3) If-None-Match when 200 → 304
4) invalid league → 404
5) no token → 401
