# TASK-009 — Implement POST /leagues/{league_id}/team/substitute (swap starters/subs) (v1)

**Goal:** Implement the Team Management action endpoint for swapping roster positions (starter/sub).
This enables the classic “substitute” interaction on the Team screen.

This is a **Category C** action (no-store) and must trigger client revalidation of:
- `GET /leagues/{league_id}/team` (required)

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **POST /leagues/{league_id}/team/substitute**
- `docs/spec/phase-b-api-contracts.md` → **2.3 POST /leagues/{league_id}/team/substitute**
- `docs/spec/api-errors-updated.md` → substitute / roster errors
- `docs/spec/core-rules-updated.md` → R3.* (GW open/closed), R4.* (roster structure), R4.7 (substitution), R6.* (captain)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C + revalidation expectations

---

## 1) Endpoint

### POST /leagues/{league_id}/team/substitute
- **Auth:** required (Bearer JWT)
- **Caching:** Category C
  - `Cache-Control: no-store`
  - `meta.etag = null`

---

## 2) Request / Response (must match schema)

### Request
```json
{
  "pos_a": 2,
  "pos_b": 7
}
```

Positions are 1..8.

### Success response
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true }
}
```

---

## 3) Hard errors (standard error envelope)

### 3.1 Auth (401)
- `AUTH_REQUIRED`
- `AUTH_INVALID_TOKEN`

### 3.2 League / GW (404 / 409)
- `404 LEAGUE_NOT_FOUND`
- `409 GW_NOT_AVAILABLE`
- `409 GW_NOT_OPEN` when `open != 1`
- `409 SUBSTITUTION_NOT_ALLOWED` when deadline passed / GW closed (preferred; else GW_CLOSED if that’s your convention)

### 3.3 Team / roster state
- `409 NO_COMPETITOR`
- `404 ROSTER_NOT_FOUND` (only if roster missing and auto-create impossible)

### 3.4 Request validation (400 / 422)
- invalid JSON / missing fields / wrong types → `400 BAD_REQUEST`
- positions not in 1..8 or same position → `422 SUBSTITUTION_INVALID_POS`

### 3.5 Roster rules (422)
- substitution that would violate roster constraints → `422 SUBSTITUTION_NOT_ALLOWED`
  - (In v1, substitution is only swapping two positions, so this usually means “captain would end up on bench” if you enforce it here.)

### 3.6 Captain rule handling (R6)
Two acceptable approaches (pick ONE and implement consistently):

**Preferred (simpler, user-friendly):**
- Allow the swap even if captain is moved to pos 7/8, but then auto-fix captain to pos 1 after swap (same logic as transfer confirm).
- This keeps state always valid.

**Alternative (stricter):**
- Disallow swaps that would place the captain in pos 7/8:
  - return `422 CAPTAIN_NOT_STARTER`

Choose the **preferred auto-fix** approach unless the contracts/schemas explicitly require strict mode.

---

## 4) Implementation requirements

### 4.1 Resolve current GW (R3)
Reuse existing GW resolver helper.

### 4.2 Load competitor & roster
- competitor by (profile_id, league_id), else `NO_COMPETITOR`
- roster by (competitor_id, current_gw)
  - if missing: auto-create from latest prior roster (R4.4–R4.5) (same helper as TASK-003)

### 4.3 Validate positions
- pos_a, pos_b integers in 1..8
- pos_a != pos_b

### 4.4 Persist swap
Swap the two roster player columns:
- player{pos_a} <-> player{pos_b}

Also handle captain:
- If using auto-fix approach:
  - after swap, if captain is now in pos 7/8, set captain to player in pos 1 (starter).
  - if captain id is no longer present (should not happen), set to player1.

Perform update(s) in a transaction (optional but safe).

### 4.5 Category C response
- `Cache-Control: no-store`
- meta.etag null
- return ok:true

---

## 5) Routing

Add to root `.htaccess`:
- `^leagues/([0-9]+)/team/substitute$ -> leagues/team/substitute/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/team/substitute/index.php`

Use same envelope + auth helpers as other actions.

---

## 6) Smoke tests

Create `scripts/substitute-smoke.ps1` (curl-based):

1) Login → token
2) Pick league_id where competitor exists (from /home)
3) GET /leagues/{league_id}/team:
   - capture current roster ids and captain_player_id and ETag
4) Pick positions:
   - pos_a = 1 (starter)
   - pos_b = 7 (bench)
5) POST substitute:
   - expect 200 ok:true + Cache-Control no-store
6) GET /leagues/{league_id}/team again:
   - expect ETag differs
   - expect player at pos1 and pos7 swapped
   - if captain would have moved to bench, confirm captain auto-fixed to pos1 (preferred approach)
7) Invalid pos:
   - pos_a=0 pos_b=9 → expect 422 SUBSTITUTION_INVALID_POS
8) No token:
   - expect 401 AUTH_REQUIRED
9) Invalid league:
   - expect 404 LEAGUE_NOT_FOUND

---

## 7) Acceptance criteria

- Endpoint reachable: POST /leagues/{league_id}/team/substitute
- Category C: no-store + meta.etag null
- Swaps roster positions and /team ETag changes
- Correct errors for auth/league/gw/no competitor/invalid positions
- Captain handling consistent (preferred auto-fix) and keeps roster state valid
- Smoke script committed and passes on seeded DB
