# TASK-008 — Implement GET /leagues/{league_id}/market/players (Market list) (v1)

**Goal:** Implement the Transfer Market **players list** endpoint used by the Transfers UI to pick incoming players.

This endpoint must support:
- filtering (name search, team filter)
- sorting
- pagination
- optional **contextual availability** when `outgoing_player_ids[]` are provided

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **6. Market List — GET /leagues/{league_id}/market/players**
- `docs/spec/endpoint-matrix-updated.md` → market/players row (cache=A, ETag scope guidance, invalidation triggers)
- `docs/spec/caching-updated.md` → Category A rules (ETag + must-revalidate + 304)
- `docs/spec/api-errors-updated.md` → **4.7 Players & Market (Bundle B)**
- `docs/spec/core-rules-updated.md` → R3.* (GW open/closed), R4.6 (max from same team), R5.* (transfer constraints)

No schema/contract changes are allowed in this task.

---

## 1) Endpoint

### GET /leagues/{league_id}/market/players
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** `league + currentGW + query` (+ **user** when contextual)

---

## 2) Query params (per schema)

Optional query params (v1):
- `q` (string, optional) — name search
- `team_id` (int, optional)
- `sort` (string, optional) — `price_asc | price_desc | avg_points_desc | form_points_desc`
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)
- `outgoing_player_ids[]` (int[], optional) — contextual availability vs user roster + credits

Invalid paging (limit/offset), unknown sort, invalid team_id types → `400 BAD_REQUEST`.

If `outgoing_player_ids[]` is provided and strict validation fails (unknown IDs / not owned / duplicates / too many),
return `422 MARKET_CONTEXT_INVALID`.

---

## 3) Response contract (must match schema exactly)

Return the common envelope and fields as defined in `api-schemas-updated.md`:

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"market-l1-gw12-u123-q-1707311970\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,

    "context": {
      "outgoing_player_ids": [111],
      "available_credits": 14.0
    },

    "items": [
      {
        "player_id": 123,
        "name": "John Example",
        "team": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
        "price": 12.5,
        "stats": { "avg_points": 51.2, "form_points": 256.0 },
        "availability": {
          "can_select": false,
          "disabled_reasons": ["ALREADY_OWNED"]
        }
      }
    ],

    "total": 800,
    "limit": 50,
    "offset": 0
  }
}
```

Field notes (from schema):
- If the response is **not contextual** (no `outgoing_player_ids[]`), the server may omit `context`.
- When `availability.can_select = true`, `disabled_reasons` must be an empty array.

---

## 4) Hard errors (standard error envelope)

- `404 LEAGUE_NOT_FOUND` — league_id doesn’t exist
- `409 GW_NOT_AVAILABLE` — no gameweeks for league (cannot determine current GW)
- `409 NO_COMPETITOR` — user has no team in this league (market is tied to transfers)
- `400 BAD_REQUEST` — invalid query params (types/ranges/unknown sort)
- `422 MARKET_CONTEXT_INVALID` — invalid outgoing_player_ids context (optional strict mode)
- `500 INTERNAL_ERROR`

Auth:
- `401 AUTH_REQUIRED`
- `401 AUTH_INVALID_TOKEN`

---

## 5) Data requirements & mapping (DB)

### 5.1 Resolve current GW (R3)
Reuse the same helper as `/home` and `/team`.

If no GW rows → `409 GW_NOT_AVAILABLE`.

### 5.2 Validate league exists
`SELECT 1 FROM leagues WHERE league_id=?` else `404 LEAGUE_NOT_FOUND`.

### 5.3 Resolve competitor + roster + credits (user context)
Market is transfers-related, so require a competitor:
- `competitor` by (`profile_id`, `league_id`) else `409 NO_COMPETITOR`.

Load current GW roster for that competitor:
- if missing, reuse your existing “auto-create roster from latest prior” helper (same as TASK-003),
  because market availability needs the roster baseline.

Get:
- `credits_before` from `competitor.credits`
- roster player ids (8) and their team_ids (via join `player.team_id`)

### 5.4 Contextual outgoing_player_ids[]
If `outgoing_player_ids[]` is provided:
- validate count is 1..2
- validate all are integers, unique
- validate all are present in the current roster
- compute `available_credits` = `credits_before + SUM(price(outgoing))`
  - outgoing price is from `playertrade` at current_gw (fallback latest <= current_gw)
- build “effective roster” for team-count checks:
  - roster_after_removal = roster players minus outgoing_player_ids

If not provided:
- omit `data.context`
- set `available_credits = credits_before`
- roster_after_removal = current roster

### 5.5 Items query (filters + joins + pagination)
Base set: all `player` rows in the league.

Filters:
- if `q` provided: `playername LIKE %q%` (case-insensitive)
- if `team_id` provided: `player.team_id = ?`

Join team:
- `team.short`, `team.name`, `team.logo`

Price:
- prefer `playertrade` at current_gw
- fallback to latest <= current_gw
(Implement without N+1; use a derived table selecting max(gameweek) per player <= current_gw.)

Stats:
- `avg_points`: season-to-date average of weekly points (0.0 if unavailable)
- `form_points`: sum of last N weeks (recommended N=5; 0.0 if unavailable)
(Compute in grouped queries for the page’s player_ids.)

Total count:
- `COUNT(*)` with same filters (without limit/offset)

Sorting:
- `price_asc/desc` sorts by price
- `avg_points_desc` sorts by avg_points
- `form_points_desc` sorts by form_points
If stats are 0.0 due to missing data, sorting still works deterministically.

---

## 6) Availability computation (actions.disabled_reasons in schema notes)

For each item, compute:

Disabled reasons (array of strings):
- `ALREADY_OWNED` if player_id is in roster_after_removal (still owned)
- `BUDGET_INSUFFICIENT` if price > available_credits
- `MAX_PLAYERS_FROM_TEAM` if adding this player would exceed max (2) from that team:
  - count team_id in roster_after_removal
  - if count(team_id) >= 2 and outgoing list did not remove enough from that team → disabled

`can_select = (disabled_reasons is empty)`

Even when not contextual, still compute `ALREADY_OWNED` vs current roster; other checks use `available_credits=credits_before`.

---

## 7) ETag + last_updated (Category A)

Per endpoint matrix, ETag should reflect:
- league+currentGW+query (q/team_id/sort/limit/offset)
- if contextual: user roster/credits + outgoing_player_ids context
- underlying market data changes: playertrade (prices GW) and playerresult (if stats shown)

Suggested marker inputs:
- `current_gw`
- max price update signal for league at GW (max playertrade.gameweek + max price value hash)
- max playerresult signal for league (optional; if too heavy, omit)
- query params string
- if contextual: `credits_before`, outgoing ids, roster signature (player ids)

ETag format:
- `W/"market-l{league_id}-gw{current_gw}-u{profile_id or 0}-q{hash}"`

Implement 304 when `If-None-Match` matches computed ETag (still send `ETag` + `Cache-Control`).

---

## 8) Routing / file placement

Add to root `.htaccess` (same style as other endpoints):
- `^leagues/([0-9]+)/market/players$ -> leagues/market/players/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/market/players/index.php`

Reuse:
- JWT verification helper
- common envelope helper
- current GW resolver helper
- roster auto-create helper

---

## 9) Smoke tests (minimum)

Create `scripts/market-players-smoke.ps1` (curl-based):

1) Login → token
2) Pick league_id where competitor exists (from `/home`)
3) Call market list without context:
   - `GET /leagues/{league_id}/market/players?limit=10`
   - expect 200, headers include Cache-Control private,must-revalidate and ETag
   - expect items length > 0
4) Revalidate with If-None-Match → 304
5) Contextual test:
   - fetch `/leagues/{league_id}/team`, pick outgoing_player_id from roster pos 1
   - call market list with `outgoing_player_ids[]=<id>`
   - expect `data.context.available_credits` present
   - ensure at least one item has `availability.disabled_reasons` includes ALREADY_OWNED for a roster player
6) Invalid sort → 400 BAD_REQUEST
7) No token → 401 AUTH_REQUIRED
8) Invalid league → 404 LEAGUE_NOT_FOUND

Optional: after a transfer confirm, market list ETag should change because ownership/availability changed.

---

## 10) Acceptance criteria

- Endpoint reachable: `GET /leagues/{league_id}/market/players`
- Response matches schema keys and types exactly
- Category A caching works (ETag + 304)
- Filters/sorting/pagination behave as specified
- Availability computation works (ALREADY_OWNED, BUDGET_INSUFFICIENT, MAX_PLAYERS_FROM_TEAM)
- Contextual mode works with outgoing_player_ids and computes available_credits correctly
- Smoke script committed and passes on seeded DB
