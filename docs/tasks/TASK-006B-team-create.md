# TASK-006B — Implement POST /leagues/{league_id}/team (initial team creation) (v1)

**Goal:** Implement initial team creation:
- create competitor for the user in the league
- create roster for current GW with 8 players + captain
- set favorite team
- set remaining credits after initial spend (80.0 - total player cost)

This is a **Category C** action (no-store).

---

## 0) Source of truth (must follow)

- `docs/spec/api-schemas-updated.md` → **Initial Team Creation → POST /leagues/{league_id}/team**
- `docs/spec/core-rules-updated.md` → R3.* (GW), R4.* (roster), R5.6 (initial budget), R11.* (team creation), R6.* (captain)
- `docs/spec/api-errors-updated.md` → **3.5 Initial Team Creation** + roster/captain/max-from-team codes
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C and post-write revalidation (team + home)

> Note: This endpoint shares the path with `GET /leagues/{league_id}/team` (TASK-003). Implement method dispatch without breaking GET.

---

## 1) Endpoint

### POST /leagues/{league_id}/team
- **Auth:** required (Bearer JWT)
- **Caching:** Category C
  - `Cache-Control: no-store`
  - `meta.etag = null`

---

## 2) Request / Response (must match schema)

### Request
```json
{
  "teamname": "PinKings",
  "player_ids": [123,124,125,126,127,128,129,130],
  "captain_player_id": 123,
  "favorite_team_id": 34
}
```

Interpretation:
- `player_ids` order defines roster positions:
  - pos 1..6 = starters
  - pos 7..8 = subs

### Success response
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "competitor_id": 9001, "teamname": "PinKings", "credits": 4.5 }
}
```

---

## 3) Hard errors (error envelope) — required codes

- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `404 LEAGUE_NOT_FOUND`
- `409 GW_NOT_AVAILABLE`
- `409 TEAM_ALREADY_EXISTS` — competitor already exists for user in league (R11.1)
- `409 TEAM_CREATION_NOT_ALLOWED` — GW closed / not open (R3 + R11)
- `422 TEAMNAME_INVALID` — empty/too long (>50) / invalid chars (R11.3–R11.4)
- `422 ROSTER_INVALID_SIZE` — player_ids not exactly 8 (R4.1)
- `422 ROSTER_INVALID_POSITION` — duplicates/invalid ordering inputs (use for duplicates)
- `404 PLAYER_NOT_FOUND` — any player_id not found or not in this league
- `422 MAX_PLAYERS_FROM_TEAM` — would exceed 2 players from same team (R4.6)
- `422 CAPTAIN_INVALID` — captain not in player_ids (R6.1)
- `422 CAPTAIN_NOT_STARTER` — captain is in pos 7–8 (R6.2)
- `422 INITIAL_BUDGET_EXCEEDED` — total initial cost > 80.0 (R5.6)
- `422 FAVORITE_TEAM_INVALID` — favorite_team_id invalid for this league
- `500 INTERNAL_ERROR`

---

## 4) Validation rules & algorithm (deterministic order)

Validate in this order to keep outputs stable:

1) league exists
2) resolve current_gw (else GW_NOT_AVAILABLE)
3) check GW open (else TEAM_CREATION_NOT_ALLOWED)
4) check competitor exists (else TEAM_ALREADY_EXISTS)
5) validate request types + required fields (else 400 BAD_REQUEST)
6) validate teamname:
   - trim, non-empty, length <= 50, allowed chars
7) validate player_ids:
   - is array length 8 (ROSTER_INVALID_SIZE)
   - all integers
   - no duplicates (ROSTER_INVALID_POSITION with details {reason:"duplicates"})
   - all exist in this league (PLAYER_NOT_FOUND)
8) validate favorite_team_id exists in team table and league (FAVORITE_TEAM_INVALID)
9) validate captain:
   - is in player_ids (CAPTAIN_INVALID)
   - index <= 5 (pos 1..6) (CAPTAIN_NOT_STARTER)
10) validate max-from-team:
   - count team_id for each player; if any > 2 → MAX_PLAYERS_FROM_TEAM
11) validate initial budget:
   - sum prices at current_gw for all players (fallback latest <= current_gw)
   - if sum > 80.0 → INITIAL_BUDGET_EXCEEDED
   - credits_after = 80.0 - sum (round to 1 decimal)

---

## 5) Persistence (transaction required)

Perform in a DB transaction:

1) INSERT into `competitor`:
   - profile_id, league_id
   - teamname
   - credits = credits_after
   - favorite_team_id
   - favorite_team_changed = 0 (if field exists)
2) INSERT into `roster` for current_gw:
   - competitor_id, gameweek=current_gw
   - player1..player8 from player_ids[0..7]
   - captain = captain_player_id
3) COMMIT

On any failure: ROLLBACK and return error (500 or typed code).

---

## 6) Post-write expectations

After success, client must refresh:
- `GET /leagues/{league_id}/team` (now exists; ETag will change)
- `GET /home?league_id={league_id}` (league_context/selector snapshot changes)

No caching headers beyond no-store are required here.

---

## 7) Routing / file placement

This shares the same path as `GET /leagues/{league_id}/team`.

Preferred implementation:
- extend existing handler `leagues/team/index.php` to dispatch by method:
  - GET → existing Team payload (TASK-003)
  - POST → initial team creation (this task)

No additional rewrite rule needed if you already route `^leagues/([0-9]+)/team$` to `leagues/team/index.php?league_id=$1`.

---

## 8) Smoke tests

Create `scripts/team-create-smoke.ps1` (curl-based):

1) Register + OTP verify (use APP_ENV=local + AUTH_FIXED_OTP) to get tokens **OR** login with a unique pre-seeded user
2) Call builder:
   - `GET /leagues/1/team/builder`
   - pick first 8 players from returned list
   - pick captain = first player (starter), favorite_team_id = that player’s team_id
3) POST create team:
   - `POST /leagues/1/team` with teamname + player_ids + captain_player_id + favorite_team_id
   - expect 200 with competitor_id and credits
4) GET /leagues/1/team:
   - expect 200 and competitor present
5) Re-call builder:
   - expect 409 TEAM_ALREADY_EXISTS
6) Attempt creating team again:
   - expect 409 TEAM_ALREADY_EXISTS
7) Negative: budget exceeded
   - choose 8 highest-priced players (if available) and expect 422 INITIAL_BUDGET_EXCEEDED

---

## 9) Acceptance criteria

- POST /leagues/{league_id}/team creates competitor + roster atomically
- Enforces all required rules and returns correct error codes
- Category C no-store + meta.etag null
- Does not break GET /leagues/{league_id}/team
- Smoke script committed and passes on seeded DB
