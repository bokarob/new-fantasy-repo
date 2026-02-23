# TASK-004A — Implement POST /leagues/{league_id}/transfers/quote (v1)

**Goal:** Implement the transfer **validation** endpoint. It must validate a candidate transfer for the **current gameweek**
and return `200 OK` with `data.is_valid=false` + `violations[]` for **rule violations** (not hard errors), per spec.

This endpoint must NOT persist any transfer (no pending state). (Core Rules R5.11)

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **Transfers → POST /leagues/{league_id}/transfers/quote**
- `docs/spec/phase-b-api-contracts.md` → **2.4 POST /leagues/{league_id}/transfers/quote**
- `docs/spec/api-errors-updated.md` → Transfers codes + note about quote returning 200
- `docs/spec/core-rules-updated.md` → R3.* (GW), R4.* (roster validity), R5.* (transfers), R6.* (captain constraint)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C (no-store) + revalidation notes

---

## 1) Endpoint

### POST /leagues/{league_id}/transfers/quote
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
{
  "outgoing_player_ids": [123],
  "incoming_player_ids": [456]
}
```

Constraints:
- outgoing count = incoming count
- count must be **1 or 2**
- IDs must be integers
- no duplicates within each list
- no overlap between outgoing and incoming

### Response (always 200 if request is syntactically valid)
Valid:
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": {
    "is_valid": true,
    "summary": { "credits_before": 4.5, "credits_after": 2.0, "transfers_used_after": 1 },
    "violations": []
  }
}
```

Invalid (still 200):
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": {
    "is_valid": false,
    "summary": { "credits_before": 4.5, "credits_after": -1.0, "transfers_used_after": 1 },
    "violations": [
      { "code": "TRANSFER_BUDGET_INSUFFICIENT", "message": "Not enough credits." }
    ]
  }
}
```

---

## 3) When to return hard errors (error envelope)

Only use error envelope for:
- invalid JSON / missing required fields / wrong types → `400 BAD_REQUEST`
- league_id does not exist → `404 LEAGUE_NOT_FOUND`
- user has no competitor in this league → `409 NO_COMPETITOR`
- no current GW can be determined (no gameweeks) → `409 GW_NOT_AVAILABLE` (if used elsewhere)
- auth errors (401)

**Everything else** (rule violations) should be returned as **200 with is_valid=false**.

---

## 4) Validation rules to implement (violations[] codes)

Add violations in this order (deterministic output helps tests):

### 4.1 Transfer structure (R5.3)
- If count not 1–2, mismatch, overlap, duplicates:
  - `TRANSFER_INVALID_COUNT` (message: “Invalid outgoing/incoming count.”)
  - `TRANSFER_SAME_PLAYER` when overlap exists

### 4.2 Roster ownership
- outgoing must be in current roster:
  - `TRANSFER_PLAYER_NOT_OWNED`
- incoming must NOT already be in roster:
  - `TRANSFER_PLAYER_ALREADY_OWNED`

### 4.3 Timing (R5.8, R3.5–R3.6)
Quote should still report timing issues as violations (not errors):
- if current GW is not open / after deadline:
  - `TRANSFER_NOT_ALLOWED_GW_CLOSED` (or `GW_CLOSED` if your codebase uses that; prefer the transfer-specific code)

### 4.4 Transfer allowance (R5.1, R5.13)
- Determine transfers already used:
  - count rows in `transfers` for (competitor_id, current_gw) where `normal=1`
- Determine free-transfer GW:
  - if `leagues.free_transfer_gw` equals current_gw, ignore limit
- If NOT free gw and used + requested_count > 2:
  - add `TRANSFER_LIMIT_REACHED`

### 4.5 Budget check (R5.7)
Compute prices from `playertrade`:
- use price at `gameweek=current_gw` if present
- else fallback to latest <= current_gw per player

Credits calculation:
- `credits_after = credits_before + sum(outgoing_prices) - sum(incoming_prices)`
- if `credits_after < 0` → `TRANSFER_BUDGET_INSUFFICIENT`

**Rounding:** store/display with 1 decimal (credits column is decimal(3,1)).

### 4.6 Max players from same team (R4.6)
Simulate the roster after transfer and count players per `team_id`:
- if any team count would exceed 2:
  - add `MAX_PLAYERS_FROM_TEAM`

### 4.7 Captain integrity (R6.1–R6.2)
Quote should not block captain transfers; confirm will auto-fix captain if needed.
So: do NOT add a violation here.

---

## 5) Summary fields

Even when invalid, return `summary`:
- `credits_before`: competitor.credits
- `credits_after`: computed even if negative
- `transfers_used_after`: current_used_normal + requested_count
  - even in free gw, still increment (may exceed 2; OK)

`meta.last_updated`:
- can be `meta.server_time` for Category C actions.

---

## 6) Implementation notes (DB + helpers)

- Use the same JWT verification helper as Task001/002/003.
- Use the same current-GW resolver as `/home` and `/team` (R3).
- Quote endpoint **must not write** to DB.
- Keep response envelope identical style as other endpoints.
- Add/update root `.htaccess` rewrite similarly to `/team`:
  - `^leagues/([0-9]+)/transfers/quote$ -> leagues/transfers/quote/index.php?league_id=$1`

---

## 7) Smoke tests (minimum)

Create `scripts/transfer-quote-smoke.ps1` (curl-based, like your updated scripts):

1) Login → access token
2) Choose league_id:
   - from `/home` pick first league where competitor exists, else use `1`
3) Fetch `/leagues/{league_id}/team` to get roster player ids
4) Pick:
   - outgoing = first roster player_id
   - incoming = find a player_id not in roster by trying a few known ids (seed uses 101..112) or by probing 1..300 until quote returns either valid or “already owned”
5) Call quote:
   - Expect 200 + `Cache-Control: no-store`
   - Expect `data.is_valid` true OR false with violations (both acceptable if your incoming choice hits constraints)
6) Force invalid:
   - set incoming = outgoing (overlap) → expect 200 + `is_valid=false` + `TRANSFER_SAME_PLAYER` violation

---

## 8) Acceptance criteria

- Endpoint reachable at `POST /leagues/{league_id}/transfers/quote`
- Uses Category C no-store, meta.etag null
- Rule violations returned as `200` with `is_valid=false` (no error envelope)
- Hard errors only for auth / invalid JSON/types / league missing / no competitor / no GW
- No DB writes performed
- Smoke script added and passes on seeded DB
