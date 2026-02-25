# API Errors – Fantasy 9pin

This document defines the standard error response format, HTTP status conventions, and the error code catalog
for the Fantasy 9pin API.

These errors apply to both web and mobile clients. Error codes are stable identifiers; messages may change over time
(and may later be localized).

---

## 1 Standard Error Response Format

All error responses MUST follow this format:

```json
{
  "error": {
    "code": "GW_CLOSED",
    "message": "Transfers are not allowed after the deadline.",
    "rule": "R5.7",
    "details": {
      "league_id": 1,
      "gw": 12
    }
  }
}

```

Field rules

code (required): stable machine-readable identifier (ALL_CAPS)
message (required): short, human-readable text
rule (optional): rule reference from Core Rules Specification (e.g. R5.7)
details (optional): structured context (non-sensitive); do not include secrets

2) HTTP Status Conventions

Use HTTP status to convey the broad category:

400 Bad Request
Invalid input format, missing required fields, invalid types

401 Unauthorized
Missing/invalid auth token

403 Forbidden
Authenticated but not allowed (permission / ownership)

404 Not Found
Resource does not exist OR not visible to user (security-safe)

409 Conflict
Action conflicts with current state (GW closed, already used transfers, duplicate invite)

422 Unprocessable Entity
Input is syntactically valid but violates business rules (budget, max2/team, roster invalid)

429 Too Many Requests
Rate limiting (OTP resend, login attempts, contact spam)

500 Internal Server Error
Unexpected server failure

Recommended split (rule violations)

Use 409 for time/state conflicts (deadline, open/closed, already confirmed)
Use 422 for rule constraint violations (budget, max2/team, invalid selection)

3) Error Code Catalog
3.0 Common / Platform
| Code             | HTTP | When                                                               | Rule |
| ---------------- | ---: | ------------------------------------------------------------------ | ---- |
| BAD_REQUEST      |  400 | Missing/invalid payload or parameters                               | —    |
| VALIDATION_ERROR |  422 | Input validation failed (e.g. name too short/invalid characters)    | —    |
| STATE_CONFLICT   |  409 | Request conflicts with current resource state (already processed/stale) | — |
| RATE_LIMITED     |  429 | Too many requests (rate limit)                                      | —    |
| INTERNAL_ERROR   |  500 | Unexpected server error                                             | —    |

3.1 Auth & Identity
| Code                     | HTTP | When                                                | Rule  |
| ------------------------ | ---: | --------------------------------------------------- | ----- |
| AUTH_REQUIRED            |  401 | No auth token provided                              | —     |
| AUTH_INVALID_TOKEN       |  401 | Token invalid/expired                               | —     |
| AUTH_FORBIDDEN           |  403 | User lacks permission for resource/action           | —     |
| AUTH_EMAIL_NOT_VERIFIED  |  403 | User attempts restricted action before verification | R10.1 |
| AUTH_INVALID_CREDENTIALS |  401 | Wrong email/password                                | —     |
OTP-specific (R10.4–R10.6)
| Code                | HTTP | When                                                | Rule  |
| ------------------- | ---: | --------------------------------------------------- | ----- |
| OTP_EXPIRED         |  409 | OTP expired                                         | R10.4 |
| OTP_INVALID         |  422 | OTP code incorrect                                  | —     |
| OTP_RETRY_LIMIT     |  429 | Too many wrong attempts                             | R10.5 |
| OTP_RESEND_COOLDOWN |  429 | Resend requested too soon                           | R10.6 |
| OTP_SEND_LIMIT      |  429 | Too many OTP sends per time window (if implemented) | —     |

3.2 League & Gameweek (R3)
| Code                 | HTTP | When                                              | Rule      |
| -------------------- | ---: | ------------------------------------------------- | --------- |
| LEAGUE_NOT_FOUND     |  404 | league_id does not exist                          | —         |
| LEAGUE_ACCESS_DENIED |  403 | user not allowed to access league                 | —         |
| LEAGUE_FORBIDDEN      |  403 | user not allowed to access league (alias of LEAGUE_ACCESS_DENIED) | —         |
| GW_NOT_FOUND         |  404 | requested gw does not exist                       | —         |
| GW_CLOSED            |  409 | action attempted after deadline / closed gameweek | R3.6      |
| GW_NOT_OPEN          |  409 | action attempted when open_state != 1             | R3.5      |
| GW_MISMATCH          |  409 | client attempts action on non-current gameweek    | R3.3–R3.6 |

3.3 Team & Roster (R4, R6)
| Code                       | HTTP | When                                            | Rule        |
| -------------------------- | ---: | ----------------------------------------------- | ----------- |
| TEAM_NOT_FOUND             |  404 | competitor/team not found in league             | —           |
| ROSTER_NOT_FOUND           |  404 | roster missing (only if you do not auto-create) | R4.4–R4.5   |
| ROSTER_INVALID_SIZE        |  422 | roster not exactly 8 players                    | R4.1        |
| ROSTER_INVALID_POSITION    |  422 | position outside 1..8 or invalid reorder        | R4.1–R4.3   |
| MAX_PLAYERS_FROM_TEAM      |  422 | would exceed max 2 players from same team       | R4.6        |
| CAPTAIN_INVALID            |  422 | captain not in roster                           | R6.1        |
| CAPTAIN_NOT_STARTER        |  422 | captain selected from pos 7–8                   | R6.2        |
| CAPTAIN_CHANGE_NOT_ALLOWED |  409 | captain change after deadline/closed GW         | R6.3 / R3.6 |

3.4 Transfers (R5)
| Code                           | HTTP | When                                                | Rule |
| ------------------------------ | ---: | --------------------------------------------------- | ---- |
| TRANSFER_LIMIT_REACHED         |  409 | already used 2 transfers in this gameweek           | R5.1 |
| TRANSFER_INVALID_COUNT         |  422 | outgoing/incoming mismatch, or not 1–2              | R5.2 |
| TRANSFER_SAME_PLAYER           |  422 | same player appears outgoing and incoming           | R5.2 |
| TRANSFER_PLAYER_NOT_OWNED      |  422 | outgoing player not in roster                       | R5.2 |
| TRANSFER_PLAYER_ALREADY_OWNED  |  422 | incoming player already in roster                   | R5.3 |
| TRANSFER_BUDGET_INSUFFICIENT   |  422 | incoming total > credits after selling outgoing     | R5.6 |
| TRANSFER_NOT_ALLOWED_GW_CLOSED |  409 | confirm attempted after deadline                    | R5.7 |
| TRANSFER_PENDING_NOT_SUPPORTED |  422 | server refuses draft/pending persistence attempts   | R5.9 |
| TRANSFER_ATOMICITY_FAILED      |  500 | unexpected partial DB failure (should never happen) | R5.8 |

Note:

/transfers/quote should generally return 200 OK with is_valid=false rather than errors.

/transfers/confirm must return errors when enforcement fails.


3.5 Initial Team Creation (R11 + R4 + R5.5)
| Code                      | HTTP | When                                                 | Rule     |
| ------------------------- | ---: | ---------------------------------------------------- | -------- |
| TEAM_ALREADY_EXISTS       |  409 | competitor already exists for user in league         | R11.1    |
| TEAMNAME_INVALID          |  422 | invalid teamname (empty, too long, disallowed chars) | R11.4    |
| INITIAL_BUDGET_EXCEEDED   |  422 | initial 80.0 budget exceeded                         | R5.5     |
| TEAM_CREATION_NOT_ALLOWED |  409 | gameweek closed or creation not permitted            | R3 / R11 |
| FAVORITE_TEAM_INVALID     |  422 | invalid favorite team id                             | R9.2     |


3.6 Rankings & Standings (R8)
| Code                  | HTTP | When                                     | Rule |
| --------------------- | ---: | ---------------------------------------- | ---- |
| RANKING_NOT_AVAILABLE |  409 | ranking not computed yet (optional)      | —    |
| TABLE_NOT_AVAILABLE   |  409 | leaguetable not populated yet (optional) | —    |


3.7 Private Leagues (R9)
| Code                         | HTTP | When                                                 | Rule |
| ---------------------------- | ---: | ---------------------------------------------------- | ---- |
| PRIVATE_LEAGUE_NOT_FOUND     |  404 | privateleague_id not found                           | —    |
| PRIVATE_LEAGUE_ACCESS_DENIED |  403 | user cannot view/manage private league               | —    |
| PRIVATE_LEAGUE_NAME_INVALID  |  422 | invalid private league name                          | —    |
| PRIVATE_LEAGUE_CREATE_FAILED |  500 | insert failed unexpectedly                           | —    |
| INVITE_TARGET_NOT_FOUND      |  404 | target competitor not found in league                | R9.3 |
| INVITE_ALREADY_SENT          |  409 | invite already pending                               | —    |
| INVITE_ALREADY_MEMBER        |  409 | target already confirmed member                      | —    |
| INVITE_NOT_ALLOWED           |  403 | user lacks admin rights for invites                  | R9.4 |
| JOIN_REQUEST_ALREADY_EXISTS  |  409 | user already applied                                 | —    |
| MEMBER_NOT_FOUND             |  404 | member not in private league                         | —    |
| LEAVE_NOT_ALLOWED_ADMIN      |  409 | admin cannot leave without transfer admin (optional) | —    |
| PRIVATE_LEAGUE_FORBIDDEN     |  403 | user cannot view/manage private league (alias of PRIVATE_LEAGUE_ACCESS_DENIED) | —    |
| NOT_ADMIN                   |  403 | admin privileges required for action                                    | —    |
| ADMIN_CANNOT_LEAVE          |  409 | admin must transfer admin/delete league before leaving (alias of LEAVE_NOT_ALLOWED_ADMIN) | —    |
| CANNOT_REMOVE_SELF          |  409 | cannot remove self; use leave endpoint                                  | —    |
| ALREADY_INVITED             |  409 | invite already pending (alias of INVITE_ALREADY_SENT)                   | —    |
| ALREADY_MEMBER              |  409 | target already confirmed member (alias of INVITE_ALREADY_MEMBER)        | —    |
| INVITE_LIMIT_REACHED        |  409 | invite cap reached / league full (optional)                             | —    |
| INVITE_NOT_FOUND            |  404 | invite expired/invalid                                                  | —    |
| INVITE_NOT_PENDING          |  409 | invite not pending (already accepted/declined)                          | —    |
| PRIVATE_LEAGUE_LIMIT_REACHED|  409 | user exceeded private league creation limit (optional)                  | —    |
| NAME_ALREADY_USED           |  409 | private league name already used (optional)                             | —    |
| QUERY_TOO_SHORT             |  422 | invite search query too short (optional)                                | —    |


3.8 Notifications, News, Contact (R13)
| Code                   | HTTP | When                          | Rule |
| ---------------------- | ---: | ----------------------------- | ---- |
| NOTIFICATION_NOT_FOUND |  404 | invalid notification id       | —    |
| NOTIFICATION_FORBIDDEN |  403 | notification not owned by user | —    |
| CONTACT_RATE_LIMITED   |  429 | too many contact messages     | —    |
| NEWS_NOT_AVAILABLE     |  404 | optional if news id not found | —    |

3.9 Matches & Results (Bundle B)
| Code                  | HTTP | When                                                         | Rule |
| --------------------- | ---: | ------------------------------------------------------------ | ---- |
| MATCH_NOT_FOUND       |  404 | match_id not found (or not in this league / not visible)     | —    |
| MATCHES_NOT_AVAILABLE |  409 | fixtures/results not available yet for requested GW (optional) | —  |
| STATS_NOT_AVAILABLE   |  409 | stats not computed/available for requested GW (optional)     | —    |

3.10 Players & Market (Bundle B)
| Code                   | HTTP | When                                                         | Rule |
| ---------------------- | ---: | ------------------------------------------------------------ | ---- |
| PLAYER_NOT_FOUND       |  404 | player_id not found (or not in this league / not visible)     | —    |
| MARKET_CONTEXT_INVALID |  422 | outgoing_player_ids[] invalid (unknown/not owned/too many) (optional) | — |

4. Endpoint-Specific Expectations (v1)
4.1 /transfers/quote

Prefer returning 200 OK with:
rules_check.is_valid=false
violations[] containing code, rule, and message
Do not treat rule violations as hard errors here.

4.2 /transfers/confirm

Must enforce rules server-side and return hard errors.

Primary codes used:
TRANSFER_NOT_ALLOWED_GW_CLOSED (R5.7)
TRANSFER_LIMIT_REACHED (R5.1)
TRANSFER_BUDGET_INSUFFICIENT (R5.6)
MAX_PLAYERS_FROM_TEAM (R4.6)

4.3 /leagues/{league_id}/team (payload)

Should not error due to missing roster if auto-creation is implemented (R4.4–R4.5).
If auto-creation fails unexpectedly, return 500.

4.4 OTP endpoints

Rate limiting is critical:
OTP_RESEND_COOLDOWN (R10.6)
OTP_RETRY_LIMIT (R10.5)



5. Error Code Naming Rules

Use stable, machine-readable ALL_CAPS codes.
Prefer domain prefixes when ambiguous: AUTH_, OTP_, TRANSFER_, PRIVATE_LEAGUE_.
Never include variable data inside code (use details instead).
The meaning of a code must never change; only message text may evolve.
---

# Phase B Core Endpoints (v1)

This section standardizes error responses and client actions for the **Phase B core** endpoints:
- Home / League switch
- Team management (roster + transfers)
- Rankings

## Error response shape (common)

All non-2xx responses return:

```json
{
  "error": {
    "code": "SOME_CODE",
    "message": "Human readable message",
    "details": { "optional": "object" }
  }
}
```

Client action meanings used below:
- **Toast**: show message, do not change screen state automatically
- **Revalidate**: re-fetch the screen’s primary GET (ETag revalidate)
- **Force refresh**: re-fetch and also clear local optimistic state (e.g., pending transfer UI)
- **Navigate**: move user to a safe screen (e.g., Home / League picker)
- **Block**: disable the action until state changes (typically GW closed)

---

## 1) GET /home

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | `league_id` provided but user has no access | Toast + Navigate to League picker |
| 404 | LEAGUE_NOT_FOUND | `league_id` does not exist | Toast + Navigate to League picker |
| 429 | RATE_LIMITED | Too many requests | Toast (retry later) |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Notes:
- If `league_id` is omitted, league-related blocks may be null rather than erroring.

---

## 2) GET /leagues/{league_id}/team

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access to `league_id` | Toast + Navigate to League picker |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate to League picker |
| 409 | NO_COMPETITOR | User has no team in this league yet (if implemented as error) | Navigate to Team creation flow (or show “Create team” CTA) |
| 409 | GW_NOT_AVAILABLE | League GW not initialized / schedule missing | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast (retry later) |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Notes:
- If you choose the **null approach** instead of `NO_COMPETITOR`, then return `200` with `competitor=null` and `roster=null` and omit `NO_COMPETITOR`.

---

## 3) POST /leagues/{league_id}/team/captain

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access | Toast + Navigate |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | GW_CLOSED | Deadline passed / GW locked | Block + Revalidate team |
| 409 | GW_NOT_OPEN | Transfers/roster edits not open yet | Toast + Revalidate team |
| 422 | CAPTAIN_INVALID | Player not in roster | Toast + Revalidate team |
| 422 | CAPTAIN_NOT_STARTER | Player in roster but not eligible | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected error | Toast |

Client rule on **success**:
- Revalidate: `GET /leagues/{league_id}/team`

---

## 4) POST /leagues/{league_id}/team/substitute (optional v1)

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access | Toast + Navigate |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | GW_CLOSED | Deadline passed / locked | Block + Revalidate team |
| 409 | GW_NOT_OPEN | Not open yet | Toast + Revalidate team |
| 422 | ROSTER_INVALID_POSITION | Invalid position ids | Toast |
| 422 | ROSTER_SWAP_NOT_ALLOWED | Swap violates roster rules | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected error | Toast |

Client rule on **success**:
- Revalidate: `GET /leagues/{league_id}/team`

---

## 5) POST /leagues/{league_id}/transfers/quote

**Important:** In v1, most rule violations should return **200** with:
- `data.is_valid=false`
- `data.violations=[{code,message}]`

Use errors for auth, malformed requests, or non-recoverable state.

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access | Toast + Navigate |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | GW_CLOSED | Deadline passed / locked | Block + Force refresh (revalidate team + home) |
| 409 | NO_COMPETITOR | No team yet | Navigate to Team creation |
| 409 | MARKET_CLOSED | Transfers disabled by config | Block + Revalidate team |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected error | Toast |

Suggested `violations[].code` (non-exhaustive):
- `TRANSFER_BUDGET_INSUFFICIENT`
- `TRANSFER_LIMIT_REACHED`
- `MAX_PLAYERS_FROM_TEAM`
- `PLAYER_NOT_AVAILABLE`
- `DUPLICATE_PLAYER`
- `ROSTER_SIZE_INVALID`

---

## 6) POST /leagues/{league_id}/transfers/confirm

Unlike quote, confirm must enforce rules and typically returns **errors** for violations.

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access | Toast + Navigate |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | GW_CLOSED | Deadline passed / locked | Block + Force refresh (team + home) |
| 409 | MARKET_CLOSED | Transfers disabled by config | Block + Revalidate team |
| 409 | TRANSFER_LIMIT_REACHED | No remaining transfers | Toast + Revalidate team |
| 409 | TRANSFER_BUDGET_INSUFFICIENT | Not enough credits | Toast |
| 409 | MAX_PLAYERS_FROM_TEAM | Team constraint violated | Toast |
| 409 | PLAYER_NOT_AVAILABLE | Player became unavailable | Force refresh (team) |
| 409 | STATE_CONFLICT | Roster changed since quote | Force refresh (team) |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected error | Toast |

Client rules on **success**:
- Revalidate: `GET /leagues/{league_id}/team`
- Revalidate: `GET /home?league_id=...`
- Revalidate: `GET /leagues/{league_id}/fantasy` (rankings may change)

---

## 6.1) GET /leagues/{league_id}/transfers

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Invalid `gw` / `limit` / `offset` query params | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 401 | AUTH_INVALID_TOKEN | Invalid/expired token format/signature | Navigate to Login |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | NO_COMPETITOR | User has no team in this league | Navigate to Team creation |
| 409 | GW_NOT_AVAILABLE | No gameweek rows for league | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Notes:
- Supports conditional requests; if `If-None-Match` matches current ETag, return `304 Not Modified` (not an error).

## 7) GET /leagues/{league_id}/fantasy

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access | Toast + Navigate |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 409 | RANKING_NOT_AVAILABLE | Rankings not computed yet for the GW | Toast + Pull-to-refresh available |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected error | Toast |

Notes:
- If rankings are being computed, consider including `Retry-After` header with 409 to guide the client.
---

## Phase C — Notifications (v1)

This section standardizes errors for the Notifications screen endpoints.

### GET /notifications

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 429 | RATE_LIMITED | Too many requests | Toast (retry later) |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast (keep cached list if available) |

Notes:
- If conditional request uses `If-None-Match` and nothing changed, server returns `304 Not Modified` (not an error).

---

### POST /notifications/{notification_id}/read

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Invalid notification id format | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 404 | NOTIFICATION_NOT_FOUND | Notification does not exist | Toast + Revalidate `/notifications` |
| 403 | NOTIFICATION_FORBIDDEN | Notification not owned by user | Toast + Revalidate `/notifications` |
| 409 | STATE_CONFLICT | Already read / state changed (optional) | Revalidate `/notifications` |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate `GET /notifications`
- If Home shows unread badge/preview, revalidate `GET /home` (optional heuristic)

---

### POST /notifications/read-all (optional v1)

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate `GET /notifications`
- Revalidate Home badge/preview if displayed
---

## Phase C — Private Leagues (Bundle A)

This section standardizes errors for Private Leagues endpoints.

### GET /leagues/{league_id}/private-leagues

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | No access to `league_id` | Toast + Navigate back |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate back |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast (keep cached list if available) |

---

### POST /leagues/{league_id}/private-leagues (Create)

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | No league access | Toast + Navigate back |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate back |
| 409 | PRIVATE_LEAGUE_LIMIT_REACHED | User exceeded creation limit (optional) | Toast |
| 409 | NAME_ALREADY_USED | Name already used (optional) | Toast |
| 422 | VALIDATION_ERROR | Name too short/long/invalid chars | Inline error |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate `GET /leagues/{league_id}/private-leagues`

---

### GET /leagues/{league_id}/private-leagues/{privateleague_id}

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | PRIVATE_LEAGUE_FORBIDDEN | Not a member / no access | Toast + Navigate to private league list |
| 404 | PRIVATE_LEAGUE_NOT_FOUND | Does not exist | Toast + Navigate to private league list |
| 409 | RANKING_NOT_AVAILABLE | Standings not computed yet (optional) | Toast + Pull-to-refresh |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

---

### POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | PRIVATE_LEAGUE_FORBIDDEN | Not a member | Toast + Navigate to list |
| 404 | PRIVATE_LEAGUE_NOT_FOUND | Does not exist | Toast + Navigate to list |
| 409 | ADMIN_CANNOT_LEAVE | Admin must transfer/delete first (optional) | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate private leagues list
- Navigate back

---

### POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | NOT_ADMIN | Caller not admin | Toast |
| 404 | MEMBER_NOT_FOUND | Competitor not a member | Toast + Revalidate detail |
| 409 | CANNOT_REMOVE_SELF | Use leave endpoint | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate private league detail + list

---

### GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | NOT_ADMIN | Caller not admin | Toast + Navigate back |
| 404 | PRIVATE_LEAGUE_NOT_FOUND | Does not exist | Toast + Navigate back |
| 422 | QUERY_TOO_SHORT | q too short (optional) | Inline hint |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

---

### POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | NOT_ADMIN | Caller not admin | Toast |
| 404 | PRIVATE_LEAGUE_NOT_FOUND | Does not exist | Toast |
| 409 | ALREADY_MEMBER | Target already member | Toast + Revalidate detail |
| 409 | ALREADY_INVITED | Invite already pending | Toast + Revalidate detail |
| 409 | INVITE_LIMIT_REACHED | League full / invite cap (optional) | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate private league detail + list
- Revalidate `/notifications` (invite sent triggers invitee notification)

---

### Manage Invites (optional)

#### GET /leagues/{league_id}/private-leagues/invites

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | No access to league | Toast + Navigate |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

#### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 404 | INVITE_NOT_FOUND | Invite expired/invalid | Toast + Revalidate list |
| 409 | INVITE_NOT_PENDING | Already accepted/declined | Toast + Revalidate list |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate private leagues list + private league detail
- Revalidate `/notifications` (admin receives accept notification)

#### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 404 | INVITE_NOT_FOUND | Invite expired/invalid | Toast + Revalidate list |
| 409 | INVITE_NOT_PENDING | Already accepted/declined | Toast + Revalidate list |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate private leagues list
- Revalidate `/notifications` (admin receives decline notification)

---

### Rename/Delete (optional v1)

#### POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | NOT_ADMIN | Caller not admin | Toast |
| 422 | VALIDATION_ERROR | Invalid name | Inline error |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

#### POST /leagues/{league_id}/private-leagues/{privateleague_id}/delete

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | NOT_ADMIN | Caller not admin | Toast |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

4.5 Matches & Results (Bundle B)

GET /leagues/{league_id}/matches
- SHOULD generally return 200 with matches[] (may be empty).
- If gw does not exist for the league → GW_NOT_FOUND (404).
- If fixtures/results are not imported yet for the requested GW, prefer 200 with matches[] empty
  (best UX), OR return MATCHES_NOT_AVAILABLE (409) if you want to make the state explicit.

GET /leagues/{league_id}/matches/{match_id}
- If match_id does not exist, or exists but is not in the requested league (or not visible) → MATCH_NOT_FOUND (404).


4.6 Table & Stats (Bundle B)

GET /leagues/{league_id}/table
- If leaguetable is not populated yet, return TABLE_NOT_AVAILABLE (409) (optional; see R8 section).

GET /leagues/{league_id}/stats/players
- If gw does not exist for the league → GW_NOT_FOUND (404).
- If stats are not available/computed for the requested GW, prefer 200 with items[] empty OR return STATS_NOT_AVAILABLE (409) if you want to make the state explicit.


4.7 Players & Market (Bundle B)

GET /leagues/{league_id}/market/players
- Invalid paging (limit/offset), unknown sort, invalid team_id types → BAD_REQUEST (400) or VALIDATION_ERROR (422).
- If outgoing_player_ids[] is provided and you enforce strict validation, you may return MARKET_CONTEXT_INVALID (422)
  when the IDs are unknown / not owned by the user / exceed server limits.

GET /leagues/{league_id}/players/{player_id}
- If player_id does not exist, or exists but is not in the requested league (or not visible) → PLAYER_NOT_FOUND (404).

---

## Phase C — More / Account (Bundle C)

This section standardizes errors for the More/Account endpoints:
- Profile (`/me`)
- Settings (`/me` patch)
- Your Teams (`/me/teams`)
- Rules (`/leagues/{league_id}/rules`)
- Contact / Support (`/contact`)
- Deletions (`DELETE /leagues/{league_id}/team`, `DELETE /me`)

### GET /me

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 429 | RATE_LIMITED | Too many requests | Toast (retry later) |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast (keep cached profile if available) |

Notes:
- If conditional request uses `If-None-Match` and nothing changed, server returns `304 Not Modified` (not an error).

---

### PATCH /me

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload (e.g. empty PATCH) | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 422 | VALIDATION_ERROR | Invalid alias/lang (length, chars, unsupported code) | Inline error |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Revalidate `GET /me`
- Revalidate `GET /home` if alias is displayed there (optional heuristic)

---

### GET /me/teams

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 429 | RATE_LIMITED | Too many requests | Toast (retry later) |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast (keep cached list if available) |

Notes:
- If conditional request uses `If-None-Match` and nothing changed, server returns `304 Not Modified` (not an error).

---

### DELETE /leagues/{league_id}/team

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access to `league_id` | Toast + Navigate (to League picker or More) |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate |
| 404 | TEAM_NOT_FOUND | User has no team in this league | Toast + Revalidate `GET /me/teams` |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rules on success:
- Revalidate `GET /me/teams`
- Revalidate `GET /home` (team snapshot / eligibility may change)
- Revalidate `GET /leagues/{league_id}/fantasy` (rankings eligibility may change)
- Optional: Revalidate `GET /leagues/{league_id}/team` (should now return `NO_COMPETITOR` or `competitor=null`)

---

### DELETE /me

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rules on success:
- Clear local auth tokens and cached user-scoped data
- Navigate to Login (or Welcome)

Idempotency guideline:
- Repeating the delete may return `200 { ok: true }` OR `404` if you prefer strict semantics; pick one and keep it consistent.

---

### GET /leagues/{league_id}/rules

| HTTP | code | When | Client action |
|---:|---|---|---|
| 401 | AUTH_REQUIRED | Missing/expired access token | Navigate to Login |
| 403 | LEAGUE_FORBIDDEN | User has no access to `league_id` | Toast + Navigate back |
| 404 | LEAGUE_NOT_FOUND | League not found | Toast + Navigate back |
| 429 | RATE_LIMITED | Too many requests | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast (keep cached rules if available) |

Notes:
- If conditional request uses `If-None-Match` and nothing changed, server returns `304 Not Modified` (not an error).

---

### POST /contact

| HTTP | code | When | Client action |
|---:|---|---|---|
| 400 | BAD_REQUEST | Missing/invalid payload | Toast |
| 401 | AUTH_REQUIRED | Missing/expired access token (if contact requires auth) | Navigate to Login |
| 422 | VALIDATION_ERROR | Message empty/too long, invalid context fields | Inline error |
| 429 | CONTACT_RATE_LIMITED | Too many contact messages | Toast |
| 500 | INTERNAL_ERROR | Unexpected server error | Toast |

Client rule on success:
- Toast “Sent”
- Clear form fields (subject/message)
