# TASK-010 — Implement GET /leagues/{league_id}/fantasy (Rankings payload) (v1)

**Goal:** Implement the Rankings screen payload: overall fantasy rankings + optional fan-league slice + list of your private leagues (entry points).

This is a **Category A** payload endpoint with ETag/304.

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **4. Fantasy Rankings → GET /leagues/{league_id}/fantasy**
- `docs/spec/phase-b-api-contracts.md` → **3.1 GET /leagues/{league_id}/fantasy**
- `docs/spec/phase-b-screens.md` → **#3) Rankings**
- `docs/spec/api-errors-updated.md` → **7) GET /leagues/{league_id}/fantasy**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope & marker guidance
- `docs/spec/core-rules-updated.md` → rankings ordering + postponed-match recalculation + private league status display

No schema/contract changes are allowed in this task.

---

## 1) Endpoint

### GET /leagues/{league_id}/fantasy
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** **League + Current GW**  
  Include **private league membership updates** in the marker because `private_leagues.items[]` is embedded.

---

## 2) Response shape (must match schema exactly)

See `api-schemas-updated.md` for the canonical JSON.

Minimum required blocks per `phase-b-screens.md`:
- `gameweek` (gw, has_postponed_matches, last_update_at)
- `overall.items[]` (rank, previous_rank, rank_change, competitor_id, teamname, alias, total_points, weekly_points)
- `overall.you` (your competitor_id, rank) — **nullable** when user has no competitor yet
- `fan_league` (enabled boolean; favorite_team_id + favorite_team + items when enabled)
- `private_leagues.items[]` (nice to have, but implement)

**No competitor yet behavior:**
- still return overall rankings
- set `overall.you = null`
- set `fan_league.enabled=false` and `fan_league.items=[]` (or omit fan_league only if schema allows omission; prefer keeping it with enabled=false)
- `private_leagues.items=[]`

---

## 3) Errors (standard error envelope)

From `api-errors-updated.md`:
- `401 AUTH_REQUIRED`
- `403 LEAGUE_FORBIDDEN` (only if you enforce league ACL; otherwise omit)
- `404 LEAGUE_NOT_FOUND`
- `409 RANKING_NOT_AVAILABLE` — rankings not computed yet for the GW (recommended)
- `429 RATE_LIMITED` (optional if you have rate limit middleware)
- `500 INTERNAL_ERROR`

Additional common:
- `409 GW_NOT_AVAILABLE` if no gameweeks exist (consistent with other endpoints)

---

## 4) Data mapping (DB)

### 4.1 Resolve current GW
Reuse the same helper as `/home` and `/team`.

If no GW rows → `409 GW_NOT_AVAILABLE`.

### 4.2 Validate league exists
`SELECT 1 FROM leagues WHERE league_id=?` else `404 LEAGUE_NOT_FOUND`.

### 4.3 Determine whether rankings exist (RANKING_NOT_AVAILABLE)
If there are **no** ranking rows for the league at `current_gw`, return `409 RANKING_NOT_AVAILABLE`.

Implementation: count teamranking rows joined to competitors in the league:
- `SELECT COUNT(*) FROM teamranking tr JOIN competitor c ON c.competitor_id=tr.competitor_id WHERE c.league_id=? AND tr.gameweek=?`

If count == 0 → ranking not available.

### 4.4 Overall rankings list
For each competitor in the league:
- current rank: `teamranking.rank` at current_gw
- previous rank: `teamranking.rank` at (current_gw - 1) if exists, else set equal to current rank
- rank_change = previous_rank - current_rank (positive means climbed)
- weekly_points: `teamresult.weeklypoints` at current_gw (0.0 if missing)
- total_points: SUM(teamresult.weeklypoints) for gw <= current_gw (0.0 if none)
- teamname: `competitor.teamname`
- alias: `profile.alias` via competitor.profile_id

Ordering: ascending by `rank`.

**Performance note:** avoid N+1.
Use derived tables:
- `weekly_points_by_competitor` (current_gw)
- `total_points_by_competitor` (<= current_gw)
- `prev_rank_by_competitor` (current_gw-1)

Then join to the current ranking rows.

### 4.5 overall.you
If the user has a competitor in this league:
- look up `competitor_id` for (profile_id, league_id)
- find its current rank in teamranking for current_gw
Return `{ competitor_id, rank }`.
If competitor missing → `null`.

### 4.6 Fan league section
Enabled only when the user has a competitor **and** `competitor.favorite_team_id` is not null.

When enabled:
- favorite_team = team table (name, short, logo_url)
- fan league items = overall-like items filtered to competitors where `favorite_team_id = your favorite_team_id`
- ranking within fan league should be computed by ordering by `total_points DESC`, tie-breaking by competitor_id ASC (deterministic).
  - previous_rank can be computed similarly using prior GW totals, or fallback to current rank if too complex.

If you cannot compute fan ranks cleanly with current tables, it is acceptable in v1 to:
- use overall ranks for `rank/previous_rank/rank_change` while filtering the list
…but prefer computing fan-slice ranks if feasible.

### 4.7 Private leagues list (entry points)
Return the list of private leagues in this league where the user is a member or invited.

Fields:
- `privateleague_id`, `leaguename`
- `admin_alias` from profile (privateleague.admin)
- `member_count` (recommended: count of confirmed members)
- `your_status`
  - if `privateleaguemembers.status` column exists, use it
  - else fallback: confirmed=1 → `member_confirmed`, else `pending`

If no private leagues or feature not enabled yet, return `private_leagues.items=[]`.

---

## 5) gameweek block fields

- `gw`: current_gw
- `has_postponed_matches`:
  - if matches table has a `status` column, set true when any match in (league_id, current_gw) has status='postponed'
  - else return `false`
- `last_update_at`:
  - if you have an updated_at marker for rankings/leaguetable job, use it
  - else set to `meta.last_updated` (or `meta.server_time`)

---

## 6) ETag + last_updated (Category A)

Per caching docs:
- marker should include ranking result changes and private league membership updates.

If your ranking tables have no updated_at, use stable aggregates:
- count of ranking rows for league+gw
- max rank value
- sum of weeklypoints at current_gw across league competitors
- sum of total points across league competitors
- max privateleague_id for league
- count of membership rows for league (or for the user if only user-scoped list is included)

Example marker string:
`"{gw}|rcnt:{rank_cnt}|rmax:{rank_max}|w:{sum_week}|t:{sum_total}|pl:{pl_cnt}:{pl_max}|mem:{mem_cnt_user}"`

ETag format:
- `W/"fantasy-{league_id}-{current_gw}-{hash}"`

`meta.last_updated`:
- if you can derive a max timestamp from relevant updated_at columns, use it
- else set to `meta.server_time`

Implement 304 if `If-None-Match` matches computed ETag (still include `ETag` + `Cache-Control`).

---

## 7) Routing

Add to root `.htaccess`:
- `^leagues/([0-9]+)/fantasy$ -> leagues/fantasy/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/fantasy/index.php`

Reuse existing helpers for:
- JWT auth verification
- envelope building
- current GW resolver
- Category A header handling + 304

---

## 8) Smoke tests (minimum)

Create `scripts/fantasy-smoke.ps1` (curl-based):

1) Login → token
2) Choose league_id where competitor exists (from /home); else use 1
3) Call `GET /leagues/{league_id}/fantasy`:
   - if rankings exist: expect 200 + Cache-Control private,must-revalidate + ETag
   - validate `data.overall.items` is array
4) Revalidate with If-None-Match → expect 304
5) Invalid league → 404 LEAGUE_NOT_FOUND
6) No token → 401 AUTH_REQUIRED
7) (Optional) if your seeded DB has no rankings computed, expect 409 RANKING_NOT_AVAILABLE

---

## 9) Acceptance criteria

- Endpoint reachable: GET /leagues/{league_id}/fantasy
- Response matches schema keys and types
- Category A caching works (ETag + 304)
- Correct errors (404 league not found; 409 ranking not available; auth errors)
- Includes private leagues list (even if empty) and fan league block (enabled false when not applicable)
- Smoke script committed and passes on local seeded DB (or handles 409 gracefully)
