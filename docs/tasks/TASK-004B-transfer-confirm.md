# TASK-004B — Implement POST /leagues/{league_id}/transfers/confirm (v1)

**Goal:** Implement the transfer **persistence** endpoint. It must re-validate all rules server-side and, if valid,
apply the transfer atomically: update roster, update competitor credits, insert transfer rows.

Unlike quote, confirm returns **error envelope** on enforcement failures.

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **Transfers → POST /leagues/{league_id}/transfers/confirm**
- `docs/spec/phase-b-api-contracts.md` → **2.5 POST /leagues/{league_id}/transfers/confirm**
- `docs/spec/api-errors-updated.md` → Transfer errors + HTTP codes
- `docs/spec/core-rules-updated.md` → R3.*, R4.*, R5.*, R6.*
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C + post-write revalidation triggers

---

## 1) Endpoint

### POST /leagues/{league_id}/transfers/confirm
- **Auth:** required (Bearer JWT)
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

### Response (success)
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true, "transfer_id": 70001 }
}
```

Notes:
- If 2 players are transferred, you still return **one** `transfer_id`.
  - Use the first inserted transfer row’s id (do not change schema).
- Client should revalidate:
  - `GET /leagues/{league_id}/team` (required)
  - optionally `GET /home?league_id=...`

---

## 3) Errors (must use error envelope)

Return error envelope for any rule/enforcement failure, with codes from `api-errors-updated.md`.

### 3.1 Structural validation (422)
- `TRANSFER_INVALID_COUNT`
- `TRANSFER_SAME_PLAYER`

### 3.2 Ownership (422)
- `TRANSFER_PLAYER_NOT_OWNED`
- `TRANSFER_PLAYER_ALREADY_OWNED`

### 3.3 Timing (409)
- `TRANSFER_NOT_ALLOWED_GW_CLOSED` (preferred) or `GW_CLOSED` if used elsewhere

### 3.4 Transfer allowance (409 unless free GW)
- `TRANSFER_LIMIT_REACHED` if NOT free gw and used + requested_count > 2

### 3.5 Budget (422)
- `TRANSFER_BUDGET_INSUFFICIENT`

### 3.6 Max per team (422)
- `MAX_PLAYERS_FROM_TEAM`

### 3.7 Atomicity failure (500)
If DB transaction cannot complete safely:
- `TRANSFER_ATOMICITY_FAILED`

Also:
- `404 LEAGUE_NOT_FOUND`
- `409 NO_COMPETITOR`
- `409 GW_NOT_AVAILABLE` (if no gameweeks)

---

## 4) Atomic apply logic (transaction required) — R5.10

Implement with a DB transaction (PDO begin/commit/rollback):

1) Resolve current_gw (same helper as /home and /team)
2) Validate league exists
3) Load competitor (profile_id, league_id) else `NO_COMPETITOR`
4) Load current roster for (competitor_id, current_gw)
   - If missing, auto-create by copying the latest prior roster (same logic as Task003)
5) Re-validate all rules (same as quote, but as hard errors)
6) Apply changes:
   - For each outgoing[i], find its roster position and replace with incoming[i]
   - Update competitor.credits to computed credits_after
   - Insert transfer row(s):
     - For each pair: `(competitor_id, current_gw, playerout, playerin, normal)`
     - **normal**: set `1` always; free gw is enforced by skipping limit check.
7) Captain fix-up (R6.1–R6.2):
   - If roster.captain is no longer in the roster OR captain is now in pos 7–8:
     - set captain to the first starter position (pos 1..6) after the transfer
     - if somehow no starter exists, set to player1
8) Commit transaction

If any step fails, rollback and return the appropriate error.

---

## 5) Free transfer GW (R5.13)

Check:
- `SELECT free_transfer_gw FROM leagues WHERE league_id=?`
If `current_gw == free_transfer_gw`:
- ignore `TRANSFER_LIMIT_REACHED` enforcement (allow unlimited)
- still insert transfers rows and update roster/credits normally

---

## 6) Post-write revalidation expectations

After a successful confirm, the following payloads must change ETag (because roster/credits/transfers changed):
- `GET /leagues/{league_id}/team` (required)
- optionally `GET /home?league_id=...`

No cache headers needed here (Category C no-store), but the downstream payload ETags must change.

---

## 7) Routing note

Follow the same rewrite style used for `/team`:
- root `.htaccess`: `^leagues/([0-9]+)/transfers/confirm$ -> leagues/transfers/confirm/index.php?league_id=$1`

---

## 8) Smoke tests (minimum)

Create `scripts/transfer-confirm-smoke.ps1`:

1) Login → token
2) Pick league_id:
   - from `/home` choose a league where competitor exists; else use `1`
3) Fetch `/leagues/{league_id}/team` (store ETag + roster ids + credits)
4) Choose outgoing/incoming:
   - outgoing: first roster player_id
   - incoming: pick a player_id not in roster (seed suggests 109..112 if roster is 101..108)
5) Call **quote** first (optional but useful) and ensure it returns 200
6) Call **confirm**:
   - expect 200 with `data.ok=true`
7) Fetch `/leagues/{league_id}/team` again:
   - expect 200
   - expect ETag differs from the previous one
   - ensure the outgoing player_id is no longer present and incoming is present
8) Transfer limit test (non-free gw):
   - perform 2 confirms total, then a 3rd confirm should return 409 `TRANSFER_LIMIT_REACHED`
   - (If your chosen league is free_gw, skip this test)
9) No token confirm → 401 `AUTH_REQUIRED`
10) Invalid league confirm → 404 `LEAGUE_NOT_FOUND`

---

## 9) Acceptance criteria

- Endpoint reachable at `POST /leagues/{league_id}/transfers/confirm`
- Uses Category C no-store, meta.etag null
- Valid confirm updates roster + competitor credits + inserts transfer rows atomically
- Enforcement failures return correct error envelope + HTTP codes
- Captain is always valid after confirm (auto-fix if transferred out)
- Smoke script added and passes on seeded DB
