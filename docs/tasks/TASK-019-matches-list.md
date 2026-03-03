# TASK-019 — Implement GET /leagues/{league_id}/matches?gw= (Matches list) (v1)

**Goal:** Implement the match list payload for the Matches screen for a given league and gameweek.

Category: **A** (ETag + 304)

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.1 GET /leagues/{league_id}/matches**
- `docs/spec/api-schemas-updated.md` → **Matches → GET /leagues/{league_id}/matches**
- `docs/spec/phase-c-screens.md` → Matches screen behavior (GW picker)
- `docs/spec/api-errors-updated.md` → Matches errors (Bundle B)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **League + GW**
- `docs/spec/core-rules-updated.md` → match statuses

## Endpoint
### GET /leagues/{league_id}/matches?gw={gw?}
- Auth required (Bearer JWT)
- Category A headers: `Cache-Control: private, must-revalidate` + `ETag`
- `If-None-Match` → `304` (no body) when unchanged
- gw optional:
  - if omitted: use current_gw (same helper as /home)
  - invalid type: `400 BAD_REQUEST`
  - gw not found for league: follow api-errors/contracts (`404 GAMEWEEK_NOT_FOUND` preferred)

## Response
Must match schema, typically:
- `data.league_id`, `data.gw`
- `data.gw_picker` (available gws + selected)
- `data.items[]` newest/ordered per schema:
  - `match_id`, `gw`, `status`
  - `home_team` + `away_team` (team_id, short, name, logo_url)
  - `result` nullable if not finished

## Errors
- 401 AUTH_REQUIRED / AUTH_INVALID_TOKEN
- 404 LEAGUE_NOT_FOUND
- 404 GAMEWEEK_NOT_FOUND (if applicable)
- 500 INTERNAL_ERROR

## Mapping notes
- join `matches` with `team` for home/away display
- status strings limited to allowed values (scheduled/finished/postponed/cancelled)
- gw picker from `gameweeks` table

## ETag marker
Include league+gw match changes:
- count matches
- max(updated_at) if exists else max(match_id)
- sum of result fields as change signal
ETag: `W/"matches-l{league_id}-gw{gw}-{hash}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/matches$ -> leagues/matches/index.php?league_id=$1 [QSA,L]`
- handler: `leagues/matches/index.php`

## Smoke
Create `scripts/matches-list-smoke.ps1`:
1) login → token
2) GET matches (no gw) → 200 + ETag
3) If-None-Match → 304
4) invalid league → 404
5) no token → 401
