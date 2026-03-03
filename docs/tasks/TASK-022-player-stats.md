# TASK-022 — Implement GET /leagues/{league_id}/stats/players (Player stats list) (v1)

Category: **A** (ETag + 304)

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.4 GET /leagues/{league_id}/stats/players**
- `docs/spec/api-schemas-updated.md` → **Stats → Players list**
- `docs/spec/api-errors-updated.md` → stats errors
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A
- `docs/spec/core-rules-updated.md` → latest GW points note

## Endpoint
### GET /leagues/{league_id}/stats/players
- Auth required
- Category A: private,must-revalidate + ETag + 304
- Supports sort/team_id/limit/offset per schema; invalid → 400 BAD_REQUEST

## Response
Per schema:
- league_id
- items[] each includes player+team + total_points + avg_points + last_gw_points
- total, limit, offset

Empty stats is OK: return items=[] total=0.

## Mapping
- latest_gw = MAX(gameweek) from gameweeks
- aggregate playerresult by player_id:
  - total_points = SUM(points)
  - avg_points per schema
  - last_gw_points = SUM(points) where gameweek=latest_gw
Join player + team for names/logos.

## ETag
Marker includes count + max id/ts + query params string
ETag: `W/"pstats-l{league_id}-{hash}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/stats/players$ -> leagues/stats/players/index.php?league_id=$1 [QSA,L]`
- handler: `leagues/stats/players/index.php`

## Smoke
`scripts/player-stats-smoke.ps1`:
1) login → token
2) GET stats → 200 + ETag; If-None-Match → 304
3) invalid sort → 400
4) invalid league → 404
5) no token → 401
