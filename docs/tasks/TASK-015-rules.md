# TASK-015 — Implement GET /leagues/{league_id}/rules (Rules payload) (v1)

**Goal:** Implement the league rules payload used by the **Rules** screen and as an authoritative source of gameplay constraints for the client.

This is a **Category A** payload endpoint with ETag/304.

---

## 0) Source of truth (must follow)

- `docs/spec/phase-c-api-contracts.md` → **5.6 GET /leagues/{league_id}/rules**
- `docs/spec/api-schemas-updated.md` → **9. Rules / Configuration → GET /leagues/{league_id}/rules**
- `docs/spec/api-errors-updated.md` → rules-related errors (or generic league/auth errors if not specialized)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **League**
- `docs/spec/core-rules-updated.md` → authoritative values (roster size, starters/subs, max-from-team, transfers allowed, free transfer GW, initial budget, season lock)

No schema/contract changes in this task.

---

## 1) Endpoint

### GET /leagues/{league_id}/rules
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** **League** (include season lock state if you implement it)

---

## 2) Response shape (must match schema exactly)

Per `api-schemas-updated.md` the response must include:

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"rules-l1-1707559080\""
  },
  "data": {
    "league_id": 1,
    "season": { "is_locked": false },
    "rules": {
      "roster_size": 8,
      "starters": 6,
      "subs": 2,
      "max_from_same_team": 2,
      "transfers_allowed_per_gw": 2,
      "free_transfer_gw": 10,
      "is_free_gw": false,
      "initial_budget": 80.0
    },
    "links": {
      "full_rules_url": "https://example.com/rules"
    }
  }
}
```

Notes:
- `links.full_rules_url` is optional. If you don’t have it, either omit `links` (only if schema allows) or set it to `""` consistently.
- `is_free_gw` must be computed from `free_transfer_gw == current_gw` (when free_transfer_gw is not null).

---

## 3) Errors (standard error envelope)

From contracts:
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `404 LEAGUE_NOT_FOUND`
- `403 LEAGUE_FORBIDDEN` (only if you enforce ACL; otherwise omit)
- `429 RATE_LIMITED` (optional if you have rate limiter)
- `500 INTERNAL_ERROR`

Additionally, consistent with other endpoints:
- `409 GW_NOT_AVAILABLE` if the league has no gameweeks and you cannot determine `current_gw`.

---

## 4) Data mapping (DB)

### 4.1 Validate league exists
`SELECT 1 FROM leagues WHERE league_id=?` else `404 LEAGUE_NOT_FOUND`.

### 4.2 Resolve current_gw (R3)
Reuse the same helper as `/home` and `/team`:
- prefer highest GW where `open=1`, else max available
- if no GW rows → `409 GW_NOT_AVAILABLE`

### 4.3 Rules values
Use constants from Core Rules unless your DB contains overrides:

- roster_size = 8
- starters = 6
- subs = 2
- max_from_same_team = 2
- transfers_allowed_per_gw = 2
- initial_budget = 80.0

Free transfer GW:
- use `leagues.free_transfer_gw` if column exists (Phase D migration)
- else return `null` (or 0) only if schema allows; prefer `null` if allowed
- compute `is_free_gw = (free_transfer_gw != null AND free_transfer_gw == current_gw)`

Season lock:
- If you have an explicit column/flag, use it.
- Otherwise return `false` for v1 (acceptable).

Rules URL:
- If you have a config entry / env var, use it; else omit or empty string as allowed.

---

## 5) ETag + last_updated (Category A)

ETag should change if any of the following changes:
- current_gw for the league
- free_transfer_gw value
- season lock flag (if implemented)
- rule constants (only if you change them in code later)

Recommended marker string:
`"gw:{current_gw}|free:{free_transfer_gw}|locked:{is_locked}|v:1"`

If you have timestamps:
- include `leagues.updated_at` (if present) and/or a gameweek marker

Weak ETag:
`W/"rules-l{league_id}-{hash}"`

`meta.last_updated`:
- if you have a timestamp source, use it
- else set to `meta.server_time`

Implement 304:
- read `HTTP_IF_NONE_MATCH`
- support comma-separated values
- if matches computed ETag → 304 with no body and still include ETag + Cache-Control

---

## 6) Routing / file placement

Add to root `.htaccess`:
- `^leagues/([0-9]+)/rules$ -> leagues/rules/index.php?league_id=$1 [QSA,L]`

Create handler:
- `leagues/rules/index.php`

Reuse existing helpers:
- JWT verification
- envelope builder
- GW resolver
- Category A ETag/304 handler

---

## 7) Smoke tests (minimum)

Create `scripts/rules-smoke.ps1` (curl-based):

1) Login → token
2) GET `/leagues/{league_id}/rules`:
   - expect 200
   - headers include `Cache-Control: private, must-revalidate` and `ETag`
   - validate `data.rules.roster_size == 8` and `initial_budget == 80.0`
3) Repeat with `If-None-Match` → expect 304
4) Invalid league → expect 404 LEAGUE_NOT_FOUND
5) No token → 401 AUTH_REQUIRED

---

## 8) Acceptance criteria

- Endpoint reachable: GET /leagues/{league_id}/rules
- Response matches schema keys and types exactly
- Category A caching works (ETag + 304)
- Correct errors for missing/invalid auth and invalid league
- Smoke script passes locally
