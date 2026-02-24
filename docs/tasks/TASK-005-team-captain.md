# TASK-005 — Implement POST /leagues/{league_id}/team/captain (v1)

**Goal:** Implement the Team Management action endpoint to set the **captain** for the **current GW roster**.

This is a **Category C** action (no-store) and must trigger client revalidation of:
- `GET /leagues/{league_id}/team` (required)

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **POST /leagues/{league_id}/team/captain**
- `docs/spec/phase-b-api-contracts.md` → **2.2 POST /leagues/{league_id}/team/captain**
- `docs/spec/api-errors-updated.md` → captain + GW error codes
- `docs/spec/core-rules-updated.md` → R3.* (GW open/closed), R4.4–R4.5 (roster auto-create), R6.* (captain rules)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C + revalidation expectations

---

## 1) Endpoint

### POST /leagues/{league_id}/team/captain
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category C
  - `Cache-Control: no-store`
  - meta.etag must be `null`

---

## 2) Request / Response (must match schema)

### Request
```json
{ "captain_player_id": 123 }
```

### Response (success)
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true }
}
```

---

## 3) Hard errors (must use error envelope)

Return the standard error envelope:
```json
{ "error": { "code": "...", "message": "...", "rule": "R..", "details": { ... } } }
```

### 3.1 Request validation (400)
- Missing body / invalid JSON / wrong types / missing `captain_player_id` → `400 BAD_REQUEST`

### 3.2 League / GW (404 / 409)
- League does not exist → `404 LEAGUE_NOT_FOUND`
- No current GW determinable (no gameweeks) → `409 GW_NOT_AVAILABLE` (if used elsewhere in your API)
- GW not open (`open != 1`) → `409 GW_NOT_OPEN`
- GW closed by deadline → `409 CAPTAIN_CHANGE_NOT_ALLOWED` (preferred)  
  - If your codebase uses `GW_CLOSED` consistently, you may return that instead, but be consistent.

### 3.3 Team / roster state (409 / 404)
- User has no competitor in league → `409 NO_COMPETITOR`
- Roster missing for current GW:
  - **Preferred:** auto-create from latest previous roster (R4.4–R4.5)
  - Only if auto-create is impossible, return:
    - `404 ROSTER_NOT_FOUND`

### 3.4 Captain rules (422)
- captain_player_id not in roster → `422 CAPTAIN_INVALID` (R6.1)
- captain_player_id is in roster but in pos 7–8 → `422 CAPTAIN_NOT_STARTER` (R6.2)

---

## 4) Implementation requirements

### 4.1 Resolve current GW (R3)
Use the same helper as `/home` and `/team`:
- prefer highest GW where `open=1`, else max available
- compute `deadline_end_of_day` and `is_open`

### 4.2 Load competitor & roster
- `competitor` by (`profile_id`, `league_id`), else `NO_COMPETITOR`
- roster by (`competitor_id`, `current_gw`)
  - if missing: auto-create by copying latest previous roster (same logic as TASK-003)

### 4.3 Validate requested captain
Find `captain_player_id` among roster positions player1..player8:
- if not found → CAPTAIN_INVALID
- if found at pos 7 or 8 → CAPTAIN_NOT_STARTER

### 4.4 Persist
Update roster.captain for (competitor_id, current_gw).

**Transaction:** not strictly required (single update), but OK to use if your code pattern prefers it.

### 4.5 Post-write expectations
Even though this endpoint is Category C (no-store), it must cause the Team payload ETag to change:
- `/leagues/{league_id}/team` uses roster state for its ETag marker (captain affects marker)

---

## 5) Routing
Follow the established rewrite style (like `/team` and transfers):

Add to root `.htaccess`:
- `^leagues/([0-9]+)/team/captain$ -> leagues/team/captain/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/team/captain/index.php`

---

## 6) Smoke tests (minimum)

Create `scripts/captain-smoke.ps1` (curl-based):

1) Login → access token
2) Pick a league where competitor exists:
   - use `/home?league_id=...` list or scan `/home` leagues list
   - fallback to league_id=1
3) Fetch `/leagues/{league_id}/team`:
   - extract roster positions and `captain_player_id`
   - store ETag
4) Negative case:
   - pick bench player (pos 7 or 8) and POST captain → expect 422 `CAPTAIN_NOT_STARTER`
5) Positive case:
   - pick starter player (pos 1..6) different from current captain and POST captain → expect 200 `ok:true`
6) Fetch `/leagues/{league_id}/team` again:
   - expect `captain_player_id` changed
   - expect ETag differs from step 3
7) No token:
   - POST captain without Authorization → 401 `AUTH_REQUIRED`
8) Invalid league:
   - POST to league_id=999999 → 404 `LEAGUE_NOT_FOUND`

---

## 7) Acceptance criteria

- Endpoint reachable at `POST /leagues/{league_id}/team/captain`
- Category C `Cache-Control: no-store`, meta.etag null
- Enforces captain rules (invalid / bench)
- Enforces GW open rules (not open / closed)
- Updates roster.captain and `/team` ETag changes afterwards
- Smoke script committed and passes on seeded DB

