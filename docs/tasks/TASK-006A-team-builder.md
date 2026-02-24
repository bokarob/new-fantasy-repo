# TASK-006A — Implement GET /leagues/{league_id}/team/builder (v1)

**Goal:** Implement the Initial Team Creation **builder** payload. This is the screen that lets a user pick an initial squad.

It returns:
- fixed roster rules (size, starters/subs, max-from-team, initial budget)
- a list of available players in the league with current prices and minimal stats

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **Initial Team Creation → GET /leagues/{league_id}/team/builder**
- `docs/spec/core-rules-updated.md` → R3.* (GW), R4.* (roster constraints), R5.6 (initial budget 80), R11.* (team creation)
- `docs/spec/api-errors-updated.md` → **3.5 Initial Team Creation** + generic PLAYER_NOT_FOUND
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → treat this as **Category A** payload (ETag + must-revalidate)

> Note: `phase-b-api-contracts.md` does not currently include this endpoint; follow `api-schemas-updated.md` and common envelope rules.

---

## 1) Endpoint

### GET /leagues/{league_id}/team/builder
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged

---

## 2) Response contract (common envelope)

Return:
```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 1,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"builder-u123-l1-1-...\""
  },
  "data": {
    "rules": {
      "roster_size": 8,
      "starters": 6,
      "subs": 2,
      "budget": 80.0,
      "max_from_same_team": 2
    },
    "players": [
      {
        "player_id": 123,
        "name": "John Example",
        "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
        "price": 12.5,
        "stats": { "avg_points": 48.1 }
      }
    ]
  }
}
```

Rules constants (v1):
- roster_size = 8
- starters = 6
- subs = 2
- max_from_same_team = 2
- budget = 80.0

`players[]` must include all players in the league (or at least all selectable players).

`logo_url`:
- if you only have `team.logo` filename, map to a URL or return `""` (empty string) consistently.

`stats.avg_points`:
- if you cannot compute reliably, return `0.0`.

---

## 3) Hard errors (error envelope)

- `404 LEAGUE_NOT_FOUND` — league_id doesn’t exist
- `409 GW_NOT_AVAILABLE` — no gameweeks for this league (cannot determine current GW)
- `409 TEAM_ALREADY_EXISTS` — competitor already exists for this user in this league (builder should not be used)
- `409 TEAM_CREATION_NOT_ALLOWED` — GW not open / after deadline (R3 + R11) (prefer this single code)
- `500 INTERNAL_ERROR`

Auth errors:
- `401 AUTH_REQUIRED`
- `401 AUTH_INVALID_TOKEN`

---

## 4) Implementation details

### 4.1 Resolve current GW
Reuse the same helper logic as `/home` and `/team`:
- prefer highest GW where `open=1`, else max available
- compute `is_open` using deadline end-of-day
If no GW rows → `409 GW_NOT_AVAILABLE`.

### 4.2 Block if team already exists
If competitor exists for (`profile_id`, `league_id`) → `409 TEAM_ALREADY_EXISTS`.

### 4.3 Block if GW closed / not open
If `is_open` is false → `409 TEAM_CREATION_NOT_ALLOWED`.

### 4.4 Player list query
Return players with:
- player_id, playername
- team (team_id, short, logo)
- price for current_gw:
  - first try exact `playertrade.gameweek=current_gw`
  - else fallback to latest <= current_gw

Compute avg_points:
- simplest acceptable: average of weekly points sums across available gws in `playerresult` (or return 0.0 if no table/data).

**Performance:** avoid N+1; use joins and grouped queries.

---

## 5) ETag + last_updated (Category A)

Scope: **User + League + Current GW** (because the endpoint is blocked if the user already has a team).

Recommended marker includes:
- `current_gw`
- max(playertrade.gameweek/price) for league
- max(playerresult values) for league (or omit if too heavy)
- gameweeks open/deadline fields for the league

ETag format example:
- `W/"builder-u{profile_id}-l{league_id}-{current_gw}-{hash}"`

`meta.last_updated` should reflect the latest timestamp used (or fall back to `meta.server_time` if needed).

Implement 304 if `If-None-Match` matches computed ETag.

---

## 6) Routing

Add to root `.htaccess`:
- `^leagues/([0-9]+)/team/builder$ -> leagues/team/builder/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/team/builder/index.php`

---

## 7) Smoke tests

Create `scripts/team-builder-smoke.ps1` (curl-based):

1) Login → token
2) Call builder:
   - `GET /leagues/1/team/builder`
   - expect 200 + Cache-Control private,must-revalidate + ETag present
   - expect `data.rules.roster_size=8`
   - expect `data.players` length > 0
3) Repeat with `If-None-Match` → expect 304
4) If you run with an account that already has a team in league 1:
   - expect 409 TEAM_ALREADY_EXISTS

---

## 8) Acceptance criteria

- Endpoint reachable: `GET /leagues/{league_id}/team/builder`
- Category A headers + ETag + 304 behavior works
- Returns rules + players list in schema shape
- Correct hard errors for missing league / missing GW / already has team / creation not allowed
- Smoke script committed and passes on seeded DB
