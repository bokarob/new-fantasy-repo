# TASK-007 — Implement GET /leagues/{league_id}/transfers (history + usage) (v1)

**Goal:** Implement the Transfers **history** payload for the authenticated user’s team in the given league.
This endpoint complements the existing transfer flow endpoints:
- `POST /leagues/{league_id}/transfers/quote`
- `POST /leagues/{league_id}/transfers/confirm`

It is used by:
- Transfer history screen (My Transfers)
- Team management “Transfers used” drilldown (optional)

> Note: This endpoint is **not yet documented** in the current spec bundle. This task includes a small **spec sync** step
(add schema + caching/matrix entries) so the repo stays consistent for future coding tasks.

---

## 0) Source of truth (must follow)

Reuse existing conventions from:
- `docs/spec/api-overview.md` (common envelope + conventions)
- `docs/spec/core-rules-updated.md` (R3, R5 transfers; R5.13 free transfer GW)
- `docs/spec/api-errors-updated.md` (auth + league + competitor error patterns)
- `docs/spec/caching-updated.md` (Category A semantics)
- `docs/spec/endpoint-matrix-updated.md` (how to document ETag scope + revalidation triggers)

**Spec sync deliverables (required):**
- Append a new section to `docs/spec/api-schemas-updated.md`: **GET /leagues/{league_id}/transfers**
- Add a row to `docs/spec/endpoint-matrix-updated.md` under Core Payload Endpoints
- Add a row to `docs/spec/caching-updated.md` Category A table
- Add a short endpoint-specific subsection to `docs/spec/api-errors-updated.md` (or confirm it uses existing generic errors)

No other spec changes.

---

## 1) Endpoint

### GET /leagues/{league_id}/transfers
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** **User + League (+ query params)**

---

## 2) Query params

- `gw` (int, optional) — filter transfers to a single gameweek
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)

If params are invalid types → `400 BAD_REQUEST`.

---

## 3) Response contract (define + sync into api-schemas-updated.md)

**Response (common envelope):**
```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"transfers-u123-l1-gw0-...\""
  },
  "data": {
    "league_id": 1,
    "competitor_id": 9001,
    "current_gw": 12,

    "transfers_allowed": 2,
    "transfers_used": 1,
    "is_free_gw": false,
    "free_transfer_gw": 10,

    "filter": { "gw": null },

    "items": [
      {
        "transfer_id": 70001,
        "gw": 12,
        "is_free": false,
        "outgoing": {
          "player": { "player_id": 123, "name": "John Example" },
          "team": { "team_id": 34, "short": "SKC", "logo_url": "..." }
        },
        "incoming": {
          "player": { "player_id": 456, "name": "Jane Example" },
          "team": { "team_id": 12, "short": "ABC", "logo_url": "..." }
        }
      }
    ],
    "total": 12,
    "limit": 50,
    "offset": 0
  }
}
```

Notes:
- `items` are ordered newest-first (by `transfer_id DESC`).
- `is_free` should be `true` when `transfers.normal == 0`.
- If the user has no transfers yet, return `items: []`, `total: 0`.

---

## 4) Hard errors (standard error envelope)

- `404 LEAGUE_NOT_FOUND` — league_id doesn’t exist
- `409 NO_COMPETITOR` — user has no competitor in this league
- `409 GW_NOT_AVAILABLE` — no gameweeks for this league (cannot determine current_gw)
- `400 BAD_REQUEST` — invalid query params (gw/limit/offset)
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

### 5.3 Resolve competitor
`SELECT competitor_id FROM competitor WHERE profile_id=? AND league_id=?`
If none → `409 NO_COMPETITOR`.

### 5.4 Transfers used / allowance (current gw)
- `transfers_allowed = 2` (R5.1)
- `transfers_used = COUNT(*) FROM transfers WHERE competitor_id=? AND gameweek=current_gw AND normal=1`
- `free_transfer_gw = leagues.free_transfer_gw` (nullable)
- `is_free_gw = (free_transfer_gw IS NOT NULL AND free_transfer_gw = current_gw)` (R5.13)

### 5.5 Transfers list
Query transfers (paged):
- base:
  - `FROM transfers t WHERE t.competitor_id=?`
- optional filter:
  - if `gw` provided: `AND t.gameweek = ?`
- order:
  - `ORDER BY t.transfer_id DESC`
- pagination:
  - `LIMIT ? OFFSET ?`

Total:
- `SELECT COUNT(*)` with the same filters (without limit/offset).

### 5.6 Player/team details (no N+1)
Join both outgoing and incoming:
- outgoing: `player po` + `team to`
- incoming: `player pi` + `team ti`

Mapping:
- name: `player.playername` (or your canonical column)
- team short/logo: `team.short`, `team.logo`

If logo is a filename, map to URL or return `""` consistently.

---

## 6) ETag + last_updated (Category A)

Transfers table may not have timestamps. Use a stable marker based on ids and counts:

Marker inputs (suggested):
- `max_transfer_id` for this competitor (and filter gw if provided)
- `total_count` for this competitor (and filter gw)
- `current_gw`
- `transfers_used` (current_gw)
- `free_transfer_gw` value

Example marker string:
`"{max_id}|{total}|gw:{current_gw}|used:{used}|free:{free_transfer_gw}|f:{filter_gw}|p:{limit}:{offset}"`

Compute hash (sha1/sha256) and build weak ETag:
`W/"transfers-u{profile_id}-l{league_id}-gw{filter_gw_or_0}-{hash}"`

`meta.last_updated`:
- if you have updated_at columns, use max(updated_at)
- else set to `meta.server_time`

304 handling:
- if `If-None-Match` matches computed ETag → return 304, no body, include `ETag` + `Cache-Control`.

---

## 7) Routing / file placement

Add to root `.htaccess`:
- `^leagues/([0-9]+)/transfers$ -> leagues/transfers/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/transfers/index.php`

Use the same envelope helpers and JWT verification helpers used in prior tasks.

---

## 8) Smoke tests (minimum)

Create `scripts/transfers-list-smoke.ps1` (curl-based):

1) Login → token
2) Determine league_id where competitor exists:
   - from `/home` pick first league with competitor_id not null
3) Call `GET /leagues/{league_id}/transfers`:
   - expect 200
   - headers include `Cache-Control: private, must-revalidate` and `ETag`
   - store ETag, store total
4) Call again with `If-None-Match` → expect 304
5) If possible, create one transfer via existing endpoints:
   - fetch `/leagues/{league_id}/team` and choose outgoing/incoming
   - call `POST /leagues/{league_id}/transfers/confirm`
6) Call transfers list again:
   - expect 200 and ETag differs from step 3
   - expect total >= previous total

Also test:
- invalid league → 404 LEAGUE_NOT_FOUND
- no token → 401 AUTH_REQUIRED

---

## 9) Acceptance criteria

- Endpoint reachable: `GET /leagues/{league_id}/transfers`
- Category A caching works: ETag + 304 path
- Returns correct usage fields (allowed/used/is_free_gw/free_transfer_gw)
- Returns transfers list with player/team details, paged, newest-first
- Correct hard errors for league missing / no competitor / missing GW / bad params
- Spec sync steps completed (schemas + caching + endpoint matrix + errors doc section)
- Smoke script passes in local environment (seeded DB)

