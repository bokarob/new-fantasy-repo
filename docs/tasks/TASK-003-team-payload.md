# TASK-003 — Implement GET /leagues/{league_id}/team payload (v1)

**Goal:** Implement the Phase B Team Management payload endpoint (`GET /leagues/{league_id}/team`) exactly per spec docs.
This endpoint returns the **full Team tab state for the current gameweek** (competitor, GW state, roster positions, prices, minimal stats, and config).

---

## 0) Source of truth (must follow)

Use these docs as authoritative; do **not** add new fields/endpoints unless you update specs first:
- `docs/spec/api-schemas-updated.md` → section **GET /leagues/{league_id}/team**
- `docs/spec/phase-b-api-contracts.md` → **2.1 GET /leagues/{league_id}/team**
- `docs/spec/phase-b-screens.md` → screen **2) Team management (roster + transfers)**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A + ETag scope and marker guidance
- `docs/spec/api-errors-updated.md` → section **GET /leagues/{league_id}/team**
- `docs/spec/core-rules-updated.md` → R3.* (GW open/closed), R4.* (roster rules), R5.* (transfer allowance), R6.* (captain rules), B2 (team existence approach)

---

## 1) Endpoint

### GET /leagues/{league_id}/team
- **Auth:** required (Bearer JWT access token from TASK-001)
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** **User + League + Current GW**

---

## 2) Response contract (must match schemas)

Return the standard envelope:

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"team-u123-l1-12-1707211140\""
  },
  "data": {
    "competitor": {
      "competitor_id": 9001,
      "teamname": "PinKings",
      "credits": 4.5,
      "favorite_team_id": 34
    },
    "gameweek": {
      "gw": 12,
      "deadline": "ISO-8601",
      "is_open": true,
      "transfers_allowed": 2,
      "transfers_used": 0
    },
    "roster": {
      "captain_player_id": 123,
      "positions": [
        {
          "pos": 1,
          "player": { "player_id": 123, "name": "John Example" },
          "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
          "price": 12.5,
          "stats": { "avg_points": 51.2, "form_points": 256.0, "weekly_points": 78.5 },
          "next_fixture": {
            "gw": 12,
            "opponent": { "team_id": 12, "short": "ABC", "logo_url": "..." },
            "home_away": "H"
          }
        }
      ]
    },
    "config": {
      "max_from_same_team": 2,
      "starters_count": 6,
      "subs_count": 2
    }
  }
}
```

### Optional fields / “nice to have”
Per `phase-b-screens.md`, `next_fixture` and some stats are “nice to have”. If you cannot reliably compute:
- keep the field present but set to `null` (preferred), OR
- provide neutral defaults (e.g. 0.0 for stats)
Do **not** remove keys that the schema shows unless the schema explicitly allows omission.

---

## 3) Errors (must match api-errors)

Use the standard error envelope (`{ "error": { code, message, rule?, details? } }`).

Minimum required behavior:
- **401 AUTH_REQUIRED** — missing token
- **401 AUTH_INVALID_TOKEN** — invalid/expired token
- **404 LEAGUE_NOT_FOUND** — league_id does not exist
- **409 NO_COMPETITOR** — user has no competitor in this league (**preferred approach**, per core rules B2 + api-errors notes)
- **409 GW_NOT_AVAILABLE** — league gameweeks not initialized / schedule missing (no current gw determinable)
- **500 INTERNAL_ERROR** — unexpected errors

If your system has “league access” restrictions beyond existence:
- implement **403 LEAGUE_FORBIDDEN** consistently (otherwise omit and treat as not applicable).

---

## 4) Data mapping / queries (MySQL; adapt to your schema)

### 4.1 Resolve user and league
- `profile_id` from JWT `sub`
- Validate league exists (`SELECT 1 FROM leagues WHERE league_id=?`)

### 4.2 Determine current GW (R3)
Use the same helper logic as TASK-002:
- prefer highest `gameweeks.gameweek` where `open=1`
- if none open → max available
- `deadline` is DATE; interpret as **end-of-day server time** (`23:59:59`) and convert to ISO-8601 UTC string
- `is_open = (open==1) AND (now <= deadline_end_of_day)`

If no gameweeks row exists → 409 GW_NOT_AVAILABLE.

### 4.3 Get competitor (team) for user+league
From `competitor` by (`profile_id`, `league_id`).
If not found → 409 NO_COMPETITOR.

Return:
- competitor_id, teamname, credits, favorite_team_id

### 4.4 Ensure roster exists for current GW (R4.4–R4.5)
Fetch roster row:
- `SELECT * FROM roster WHERE competitor_id=? AND gameweek=?`

If missing:
- auto-create roster for current GW:
  - copy the latest previous roster (`ORDER BY gameweek DESC LIMIT 1`)
  - insert row for current gw with player1..player8 and captain copied
If no previous roster exists (unexpected once team exists):
- return 409 ROSTER_NOT_FOUND (if supported) or 500 INTERNAL_ERROR (but prefer typed error if in api-errors).

### 4.5 Build roster.positions[]
Roster table provides player ids for pos 1..8 and captain id.
For each pos:
- join `player` for name + team_id
- join `team` for short + logo
Map:
- `player.player_id` = player_id
- `player.name` = player.playername (or your canonical field)
- `team.logo_url` = team.logo (or your URL builder)

### 4.6 Price per player (gameweek-specific)
From `playertrade`:
- first try exact `playertrade.gameweek = current_gw`
- if missing (common when price updates lag), fall back to latest <= current_gw:
  - `ORDER BY gameweek DESC LIMIT 1`

### 4.7 Stats (minimal acceptable)
Provide `stats` object for each player:
- `weekly_points`: sum of `playerresult.points` for (player_id, current_gw); if none → 0.0
- `avg_points`: average of weekly sums across all gws up to current_gw (or a season-to-date average); if none → 0.0
- `form_points`: sum of weekly sums across last N gws (recommended N=5); if none → 0.0

Implementation hint: do not run 8*3 queries if possible:
- compute weekly sums for the roster player_ids in one grouped query per metric (weekly / season / last-5).

### 4.8 Next fixture (nice-to-have)
If you can:
- from `matches` for (league_id, gameweek=current_gw) find match where player’s team_id is hometeam or awayteam
- `home_away = "H"` if team is hometeam else `"A"`
- opponent = other team; include team_id/short/logo_url

If not found, set `next_fixture = null` for that position.

### 4.9 Transfers allowed / used (R5)
- `transfers_allowed`: fixed 2 (R5.1), unless your system supports league-specific override; do **not** add `is_free_gw` here (it belongs to `/rules`)
- `transfers_used`: count transfers rows for (competitor_id, current_gw)
  - if you track “free” transfers via `transfers.normal`, count only `normal=1` for “used”; document your choice in code comments

**Important:** In the free transfer gameweek (R5.13), `transfers_used` may exceed `transfers_allowed` — this is acceptable; the unlimited status is exposed via `/leagues/{league_id}/rules` (`is_free_gw`).

### 4.10 Config block (constants)
Return:
- max_from_same_team = 2
- starters_count = 6
- subs_count = 2

---

## 5) ETag + last_updated (Category A)

Per `endpoint-matrix-updated.md`, the team ETag should reflect:
- roster state for current_gw (players1..8, captain)
- competitor fields shown in payload (teamname, credits, favorite_team_id)
- transfer usage for current_gw

### Recommended marker strategy
Compute a stable marker string, then hash it (sha1/sha256), e.g.:

- `roster_sig = "{p1},{p2},...,{p8},cap:{captain}"`
- `competitor_sig = "name:{teamname}|credits:{credits}|fav:{favorite_team_id}"`
- `transfers_sig = "tcount:{transfers_used}"`

If you have `updated_at` columns available (preferred):
- include `max(roster.updated_at)`, `competitor.updated_at`, `max(transfers.updated_at if exists)`
Else:
- rely on the `*_sig` strings above.

**ETag format (weak):**
- `W/"team-u{profile_id}-l{league_id}-{current_gw}-{marker_hash}"`

`meta.last_updated`:
- use the max timestamp you used for the marker (ISO-8601 UTC)
- if you do not use timestamps, set it to `meta.server_time` (still deterministic enough paired with ETag).

### 304 handling
If `If-None-Match` equals computed ETag:
- return **304** with no body
- still include `ETag` and `Cache-Control`

---

## 6) Smoke tests (minimum)

Create `scripts/team-smoke.ps1` (similar style to home/auth smoke scripts):

1) Acquire access token (reuse login in script)
2) Determine a league_id to test:
   - call `GET /home` and pick:
     - first league where competitor_id exists for this user (preferred)
     - else pick first league_id and expect 409 NO_COMPETITOR for /team
3) `GET /leagues/{league_id}/team`:
   - if competitor exists → expect 200 with:
     - `data.competitor` not null
     - `data.roster.positions` length 8
     - `Cache-Control` contains `private` and `must-revalidate`
     - `ETag` header present
   - store ETag
4) Repeat with `If-None-Match` → expect 304
5) Invalid league_id → expect 404 LEAGUE_NOT_FOUND
6) No token → expect 401 AUTH_REQUIRED

---

## 7) Deliverables / acceptance criteria

- Endpoint implemented and reachable: `GET /leagues/{league_id}/team`
- Response keys match schema exactly (meta/data blocks + required sub-objects)
- Category A caching works (ETag + 304)
- Missing competitor handled as **409 NO_COMPETITOR** (preferred approach)
- Minimal diffs; no refactors beyond what’s needed
- Smoke script committed and passes in local environment

---

## 8) Notes for implementers

- Do not enforce “captain must be starter” in GET; only **return** current captain id.
- Always treat GW open/closed as server-authoritative; client uses `gameweek.is_open`.
- Keep all timestamps in UTC in responses (`...Z`), even if DB stores DATE.

