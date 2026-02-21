# Phase C API Contracts (v1)

This document defines API contracts for Phase C screens, following the same conventions as Phase B.
It complements:
- `phase-c-screens.md`
- `api-overview.md`
- `api-schemas.md`
- `api-errors.md`
- `caching.md`

Common conventions (timestamps, envelope, ETags) follow Phase B.

---

## 0) Common conventions (recap)

### 0.1 Response envelope

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"resource-version\""
  },
  "data": {}
}
```

Notes:
- Phase C endpoints may be **user-scoped** (omit `league_id` / `current_gw`) or league-scoped (include them in `meta`).
- Cacheable GETs must provide a valid `ETag` header matching `meta.etag`.

### 0.2 Caching summary (recap)

- Category A: `private, must-revalidate` + ETag + 304 support
- Category C: `no-store` (writes)

---

# 1) Notifications (Inbox)

## 1.1 GET /notifications

Returns a paginated list of notifications for the authenticated user, plus a global unread counter.

### Request

**Method:** GET  
**Path:** `/notifications`  
**Query params:**
- `filter` (optional): `all` | `unread` (default `all`)
- `cursor` (optional): opaque paging cursor
- `limit` (optional): default 20, max 50

**Caching:**
- Category A
- ETag scope: **User**
- Conditional requests: `If-None-Match` supported → `304 Not Modified`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T10:00:00Z",
    "last_updated": "2026-02-07T09:59:00Z",
    "etag": "W/\"notif-u123-1707299940\""
  },
  "data": {
    "unread_count": 3,
    "items": [
      {
        "notification_id": 501,
        "type": "invite_received",
        "title": "Private league invite",
        "body": "You were invited to Friends League.",
        "created_at": "2026-02-07T08:55:00Z",
        "is_read": false,
        "target": {
          "kind": "private_league_invite",
          "league_id": 1,
          "params": { "privateleague_id": 200 }
        }
      }
    ],
    "next_cursor": "opaque-string-or-null"
  }
}
```

Notes:
- `body` may be omitted for compact notifications.
- `next_cursor` is `null` or absent when no further pages exist.

### Cache invalidation / refresh triggers (client-side)
Client should revalidate `GET /notifications`:
- on app resume / foreground
- after any successful `mark read` / `read all` write
- when Home preview `unread_count` changes (optional heuristic)

---

## 1.2 POST /notifications/{notification_id}/read

Marks a notification as read.

### Request

**Method:** POST  
**Path:** `/notifications/{notification_id}/read`  
**Body:** optional (choose one; recommend empty body)

Option A (recommended):
- No body

Option B:
```json
{ "is_read": true }
```

**Caching:** Category C (`no-store`)

### Response (success)

```json
{
  "meta": { "server_time": "2026-02-07T10:01:00Z", "last_updated": "2026-02-07T10:01:00Z", "etag": null },
  "data": { "ok": true }
}
```

### Client behavior
- Optimistic UI: mark as read immediately
- On success: revalidate `GET /notifications`
- Also revalidate `GET /home` (or rely on Home revalidate on entry) if Home shows an unread badge/preview

---

## 1.3 POST /notifications/read-all (optional v1)

Marks all notifications as read.

### Request

**Method:** POST  
**Path:** `/notifications/read-all`  
**Body:** empty

**Caching:** Category C (`no-store`)

### Response (success)

```json
{
  "meta": { "server_time": "2026-02-07T10:02:00Z", "last_updated": "2026-02-07T10:02:00Z", "etag": null },
  "data": { "ok": true, "read_count": 12 }
}
```

### Client behavior
- Optimistic UI: set all items to `is_read=true`
- On success: revalidate `GET /notifications`
- Revalidate `GET /home` if Home shows unread badge/preview

---

## 1.4 Target validity & navigation contract

The `target` object is a typed deep link descriptor.

Rules:
- If `target.kind` is league-scoped, `target.league_id` must be provided.
- Client must:
  1) ensure active league context matches `target.league_id` (if present)
  2) navigate to destination screen
- If destination fetch returns `403/404`, show toast and stay in Notifications.

---

## Appendix A — Caching summary (Notifications)

| Endpoint | Method | Category | Cache-Control | ETag | ETag scope |
|---|---|---:|---|---|---|
| `/notifications` | GET | A | `private, must-revalidate` | Yes | User |
| `/notifications/{id}/read` | POST | C | `no-store` | No | n/a |
| `/notifications/read-all` | POST | C | `no-store` | No | n/a |
---

# 2) Private Leagues (Bundle A)

These endpoints support Phase C Bundle A screens:
- Private Leagues (List)
- Private League (Detail / Standings)
- Invite Members (Search + Invite)
- Manage Invites (optional)

All endpoints below are **league-scoped** and require access to `league_id`.

> Note on IDs: `privateleague_id` identifies a private league.  
> Invites may be represented either by a dedicated `invite_id` **or** a deterministic composite (e.g., `privateleague_id` + invitee competitor). The API should treat invite actions as idempotent.

---

## 2.1 GET /leagues/{league_id}/private-leagues

Returns the user’s private leagues for the selected league, plus pending invites.

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/private-leagues`  
**Query params:** none

**Caching:**
- Category A
- ETag scope: **User + League**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:00:00Z",
    "last_updated": "2026-02-07T11:59:00Z",
    "etag": "W/\"pl-list-u123-l1-1707307140\""
  },
  "data": {
    "league_id": 1,
    "invites": [
      {
        "invite_id": "pl200-c9001",
        "privateleague_id": 200,
        "leaguename": "Friends League",
        "admin_alias": "adminGuy",
        "created_at": "2026-02-07T09:00:00Z",
        "status": "pending",
        "target": { "kind": "private_league_invite", "league_id": 1, "params": { "privateleague_id": 200 } }
      }
    ],
    "leagues": [
      {
        "privateleague_id": 201,
        "leaguename": "Workmates",
        "admin_alias": "you",
        "member_count": 8,
        "your_role": "admin",
        "your_status": "member_confirmed"
      }
    ]
  }
}
```

Client refresh triggers:
- after create/leave/delete
- after accept/decline invite
- on app foreground / pull-to-refresh

---

## 2.2 POST /leagues/{league_id}/private-leagues (Create)

Create a new private league in a base league.

### Request
**Method:** POST  
**Path:** `/leagues/{league_id}/private-leagues`  
**Body:**

```json
{ "leaguename": "Friends League" }
```

**Caching:** Category C (no-store)

### Response (success)

```json
{
  "meta": { "server_time": "2026-02-07T12:01:00Z", "last_updated": "2026-02-07T12:01:00Z", "etag": null },
  "data": { "ok": true, "privateleague_id": 200 }
}
```

Client behavior on success:
- revalidate `GET /leagues/{league_id}/private-leagues`
- navigate to private league detail (optional)

---

## 2.3 GET /leagues/{league_id}/private-leagues/{privateleague_id}

Returns header + membership + standings for the private league.

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/private-leagues/{privateleague_id}`

**Caching:**
- Category A
- ETag scope: **User + League + Current GW** (standings are GW-aware)
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:05:00Z",
    "last_updated": "2026-02-07T12:04:00Z",
    "etag": "W/\"pl-200-l1-gw12-1707307440\""
  },
  "data": {
    "league_id": 1,
    "privateleague": {
      "privateleague_id": 200,
      "leaguename": "Friends League",
      "admin_profile_id": 777,
      "admin_alias": "adminGuy",
      "member_count": 8
    },
    "membership": {
      "your_role": "member",
      "your_status": "member_confirmed"
    },
    "gameweek": { "gw": 12, "is_open": true, "deadline": "2026-02-07T19:00:00Z" },
    "standings": {
      "items": [
        {
          "rank": 1,
          "previous_rank": 1,
          "rank_change": 0,
          "competitor_id": 8123,
          "teamname": "BowlingStars",
          "alias": "mike",
          "total_points": 3201.5,
          "weekly_points": 312.5
        }
      ],
      "you": { "competitor_id": 9001, "rank": 2 }
    },
    "pending_members": [
      { "competitor_id": 9100, "alias": "kate", "teamname": "PinQueens", "invited_at": "2026-02-07T09:00:00Z" }
    ],
    "permissions": {
      "can_invite": false,
      "can_remove_members": false,
      "can_rename": false,
      "can_delete": false,
      "can_leave": true
    }
  }
}
```

Client refresh triggers:
- after invite sent (to reflect pending members)
- after member removed/left
- after GW change / ranking recalculation

---

## 2.4 POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave

Leave the private league.

### Request
**Method:** POST  
**Path:** `/leagues/{league_id}/private-leagues/{privateleague_id}/leave`  
**Body:** empty

**Caching:** Category C

### Response

```json
{
  "meta": { "server_time": "2026-02-07T12:06:00Z", "last_updated": "2026-02-07T12:06:00Z", "etag": null },
  "data": { "ok": true }
}
```

Client behavior:
- revalidate `GET /leagues/{league_id}/private-leagues`
- navigate back to list

---

## 2.5 POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove (Admin)

Remove a member from the private league.

### Request
**Method:** POST  
**Path:** `/leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove`  
**Body:** empty

**Caching:** Category C

### Response

```json
{
  "meta": { "server_time": "2026-02-07T12:07:00Z", "last_updated": "2026-02-07T12:07:00Z", "etag": null },
  "data": { "ok": true }
}
```

Client behavior:
- revalidate private league detail
- revalidate private leagues list

---

## 2.6 POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename (optional v1)

Rename a private league (admin only).

### Request
```json
{ "leaguename": "New Name" }
```

**Caching:** Category C

### Response
```json
{ "meta": { "server_time": "ISO-8601", "last_updated": "ISO-8601", "etag": null }, "data": { "ok": true } }
```

Client behavior:
- revalidate private league detail + list

---

## 2.7 POST /leagues/{league_id}/private-leagues/{privateleague_id}/delete (optional v1)

Delete a private league (admin only).

**Caching:** Category C

### Response
```json
{ "meta": { "server_time": "ISO-8601", "last_updated": "ISO-8601", "etag": null }, "data": { "ok": true } }
```

Client behavior:
- revalidate private leagues list
- navigate back to list

---

## 2.8 GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search

Autocomplete search for eligible invitees.

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search`  
**Query:**
- `q` (string, required)
- `limit` (optional, default 10, max 25)

**Caching:**
- Category B (short TTL) recommended, or Category A with very short TTL
- ETag scope: User + League (if ETag used)

### Response

```json
{
  "meta": { "server_time": "2026-02-07T12:10:00Z", "last_updated": "2026-02-07T12:10:00Z", "etag": "W/\"pl-invite-search-u123-l1-qabc-1707307800\"" },
  "data": {
    "league_id": 1,
    "q": "abc",
    "items": [
      {
        "competitor_id": 9002,
        "profile_id": 555,
        "alias": "abc_user",
        "teamname": "StrikeForce",
        "already_member": false,
        "already_invited": false
      }
    ]
  }
}
```

---

## 2.9 POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite

Send an invite to a competitor (admin only).

### Request
```json
{ "competitor_id": 9002 }
```

**Caching:** Category C

### Response
```json
{
  "meta": { "server_time": "2026-02-07T12:11:00Z", "last_updated": "2026-02-07T12:11:00Z", "etag": null },
  "data": { "ok": true }
}
```

Client behavior:
- revalidate private league detail (pending members)
- revalidate private leagues list (invites summary may change for admin)
- notification generated for invitee (`invite_received`)

---

## 2.10 Manage Invites (optional in-app inbox)

If implemented (separate from Notifications), these endpoints allow a user to manage invites in a league context.

### 2.10.1 GET /leagues/{league_id}/private-leagues/invites

**Caching:**
- Category A
- ETag scope: User + League

```json
{
  "meta": { "server_time": "ISO-8601", "last_updated": "ISO-8601", "etag": "W/\"pl-inv-u123-l1-...\"" },
  "data": {
    "league_id": 1,
    "items": [
      {
        "invite_id": "pl200-c9001",
        "privateleague_id": 200,
        "leaguename": "Friends League",
        "admin_alias": "adminGuy",
        "created_at": "ISO-8601",
        "status": "pending"
      }
    ]
  }
}
```

### 2.10.2 POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept

**Caching:** Category C

Response:
```json
{ "meta": { "server_time": "ISO-8601", "last_updated": "ISO-8601", "etag": null }, "data": { "ok": true } }
```

Client behavior:
- revalidate private leagues list
- revalidate private league detail (now member)
- notification generated for admin (`invite_accepted`)

### 2.10.3 POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline

**Caching:** Category C

Response:
```json
{ "meta": { "server_time": "ISO-8601", "last_updated": "ISO-8601", "etag": null }, "data": { "ok": true } }
```

Client behavior:
- revalidate private leagues list
- notification generated for admin (`invite_declined`)

---

## Appendix A — Caching summary (Private Leagues)

| Endpoint | Method | Category | Cache-Control | ETag | ETag scope |
|---|---|---:|---|---|---|
| `/leagues/{league_id}/private-leagues` | GET | A | `private, must-revalidate` | Yes | User + League |
| `/leagues/{league_id}/private-leagues/{privateleague_id}` | GET | A | `private, must-revalidate` | Yes | User + League + Current GW |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search` | GET | B | short TTL | Optional | User + League (+ q) |
| `/leagues/{league_id}/private-leagues/invites` *(optional)* | GET | A | `private, must-revalidate` | Yes | User + League |
| all private league POST endpoints | POST | C | `no-store` | No | n/a |
---

# 3) Matches & Results (Bundle B)

These endpoints support Phase C screens:
- **Matches (Tab)** — subviews: Matches / Table / Stats
- **Match Detail** (recommended for notification deep links)

All endpoints below are **league-scoped** and require access to `league_id`.

## 3.1 GET /leagues/{league_id}/matches

Returns fixtures/results for a given gameweek.

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/matches`  
**Query params (v1):**
- `gw` (int, optional) — if omitted, defaults to the server current GW for the league.

**Caching:**
- Category A
- ETag scope: **League + GW**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:00:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T12:59:00Z",
    "etag": "W/\"matches-l1-gw12-1707310740\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "gameweek": {
      "gw": 12,
      "deadline": "2026-02-07T18:00:00Z",
      "is_open": true
    },
    "gw_nav": {
      "prev_gw": 11,
      "next_gw": 13,
      "min_gw": 1,
      "max_gw": 22
    },
    "matches": [
      {
        "match_id": 99001,
        "kickoff_at": "2026-02-07T19:00:00Z",
        "status": "finished",
        "is_postponed": false,
        "home": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
        "away": { "team_id": 12, "short": "ABC", "name": "ABC United", "logo_url": "..." },
        "score": { "home": 5, "away": 3 },
        "points": { "team_points_home": 2, "team_points_away": 0, "match_points_home": 16, "match_points_away": 8, "set_points_home": 10, "set_points_away": 6 }
      }
    ]
  }
}
```

Client refresh triggers:
- pull-to-refresh
- app foreground (if Matches tab is visible)
- after admin imports results / recalculation (ETag change handles this)

---

## 3.2 GET /leagues/{league_id}/matches/{match_id}

Returns a single match with full details. This endpoint is recommended to support notification deep links (`target.kind="match"`).

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/matches/{match_id}`

**Caching:**
- Category A
- ETag scope: **League + Match**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:05:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T13:04:00Z",
    "etag": "W/\"match-99001-l1-1707311070\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "match": {
      "match_id": 99001,
      "kickoff_at": "2026-02-07T19:00:00Z",
      "status": "finished",
      "is_postponed": false,
      "home": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
      "away": { "team_id": 12, "short": "ABC", "name": "ABC United", "logo_url": "..." },
      "score": { "home": 5, "away": 3 }
    },
    "rows": [
      {
        "side": "home",
        "row": 1,
        "player_id": 123,
        "player_name": "John Example",
        "pins": 620,
        "setpoints": 2,
        "matchpoints": 1,
        "fantasy_points": 78.5,
        "was_starter": true,
        "was_substituted": false
      },
      {
        "side": "away",
        "player_id": 456,
        "player_name": "Mark Sample",
        "pins": 605,
        "setpoints": 2,
        "matchpoints": 0,
        "fantasy_points": 61.0,
        "was_starter": true,
        "was_substituted": false
      }
    ]
  }
}
```

Client behavior:
- If the user taps a player row, open Player Detail modal via `GET /leagues/{league_id}/players/{player_id}`.

---

## 3.3 GET /leagues/{league_id}/table

Returns the real-league table/standings (TeamPoints / MatchPoints / SetPoints ordering).

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/table`  
**Query params:** none (v1)

**Caching:**
- Category A
- ETag scope: **League + Current GW**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:10:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T13:09:00Z",
    "etag": "W/\"table-l1-1707311400\""
  },
  "data": {
    "league_id": 1,
    "table": [
      {
        "rank": 1,
        "team": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
        "win": 10,
        "draw": 1,
        "loss": 1,
        "team_points": 21,
        "match_points": 168,
        "set_points": 102
      }
    ]
  }
}
```


---

## 3.4 GET /leagues/{league_id}/stats/players

Returns a league-wide player leaderboard with **season-to-date totals and averages**.  
Optionally, the payload can include a “weekly” column for a chosen fixture GW (default: latest finished GW).

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/stats/players`  
**Query params (v1):**
- `week_gw` (int, optional) — which fixture GW to populate the weekly fields from.  
  If omitted, defaults to the latest finished GW (`totals_through_gw`).
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)

**Caching:**
- Category A
- ETag scope: **League + week_gw (+ paging)**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:15:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T13:14:00Z",
    "etag": "W/\"stats-players-l1-week11-1707311640\""
  },
  "data": {
    "league_id": 1,
    "totals_through_gw": 11,
    "week_gw": 11,
    "items": [
      {
        "player_id": 123,
        "name": "John Example",
        "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
        "matches_total": 10,
        "starter_total": 9,
        "substituted_total": 2,
        "pins_total": 7620,
        "setpoints_total": 28,
        "matchpoints_total": 12,
        "fantasy_points_total": 1012.5,

        "week_fantasy_points": 78.5,
        "week_pins": 620,
        "week_setpoints": 2,
        "week_matchpoints": 1
      }
    ],
    "total": 200,
    "limit": 50,
    "offset": 0
  }
}
```

Notes:
- `totals_through_gw` can be `< meta.current_gw` when the current GW is still open / not finished.
- Weekly fields are optional; if you only need “latest GW fantasy points”, `week_fantasy_points` alone is enough.

---

# 4) Players & Market (Bundle B)
 (Bundle B)

These endpoints support Phase C screens:
- **Transfer Market** (Players list)
- **Player Detail** (Modal)

Note: transfer validation/confirm endpoints remain defined in Phase B:
- `POST /leagues/{league_id}/transfers/quote`
- `POST /leagues/{league_id}/transfers/confirm`

## 4.1 GET /leagues/{league_id}/market/players

Returns a paginated player list for transfers, optionally contextualized to the user’s intended transfer (outgoing players).

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/market/players`  
**Query params (v1):**
- `q` (string, optional) — name search.
- `team_id` (int, optional) — filter by team.
- `sort` (string, optional) — e.g. `price_asc | price_desc | avg_points_desc | form_points_desc`.
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)
- `outgoing_player_ids[]` (int[], optional) — if present, server MAY compute `availability` against the user roster + credits.

**Caching:**
- Category A
- Cache key MUST include all query params (especially `outgoing_player_ids[]` when used).
- ETag scope:
  - **League + Current GW (+ query params)** for non-contextual lists
  - **User + League + Current GW (+ query params)** if `availability` depends on the user roster/credits
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:20:00Z",
    "last_updated": "2026-02-07T13:19:30Z",
    "etag": "W/\"market-l1-gw12-u123-q-1707311970\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "context": {
      "outgoing_player_ids": [111],
      "available_credits": 14.0
    },
    "items": [
      {
        "player_id": 123,
        "name": "John Example",
        "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
        "price": 12.5,
        "stats": { "avg_points": 51.2, "form_points": 256.0 },
        "availability": {
          "can_select": false,
          "disabled_reasons": ["ALREADY_OWNED"]
        }
      }
    ],
    "total": 800,
    "limit": 50,
    "offset": 0
  }
}
```

Client refresh triggers:
- pull-to-refresh
- after `POST /leagues/{league_id}/transfers/confirm` succeeds (market availability/ownership may change)

---

## 4.2 GET /leagues/{league_id}/players/{player_id}

Returns the player modal payload.

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/players/{player_id}`

**Caching:**
- Category A
- ETag scope: **User + League + Current GW + Player**
- Conditional requests: `If-None-Match` → `304`

### Response

```json
{
  "meta": {
    "server_time": "2026-02-07T13:25:00Z",
    "last_updated": "2026-02-07T13:24:30Z",
    "etag": "W/\"player-l1-p123-u123-gw12-1707312270\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "player": {
      "player_id": 123,
      "name": "John Example",
      "team": { "team_id": 34, "short": "SKC", "logo_url": "..." }
    },
    "ownership": { "owned_by_you": true, "roster_position": 1 },
    "price": { "current": 12.5, "previous": 12.0 },
    "base_stats": { "avg_points": 51.2, "form_points": 256.0, "selection_percent": 18.4 },
    "actions": {
      "can_buy": false,
      "can_sell": true,
      "can_replace": true,
      "can_captain": true,
      "disabled_reasons": []
    }
  }
}
```

Client behavior:
- Captain change uses Phase B endpoint: `POST /leagues/{league_id}/team/captain` (then revalidate `GET /leagues/{league_id}/team`).
- Transfer actions use Phase B endpoints (`/transfers/quote` + `/transfers/confirm`).

---

## Appendix C — Caching summary (Matches + Players/Market)

| Endpoint | Method | Category | Cache-Control | ETag | ETag scope |
|---|---|---:|---|---|---|
| `/leagues/{league_id}/matches?gw={gw}` | GET | A | `private, must-revalidate` | Yes | League + GW |
| `/leagues/{league_id}/matches/{match_id}` | GET | A | `private, must-revalidate` | Yes | League + Match |
| `/leagues/{league_id}/table` | GET | A | `private, must-revalidate` | Yes | League + Current GW |
| `/leagues/{league_id}/stats/players?week_gw={gw}` | GET | A | `private, must-revalidate` | Yes | League + GW (+ paging) |
| `/leagues/{league_id}/market/players` | GET | A | `private, must-revalidate` | Yes | League + GW (+ query) *(+ User if contextual)* |
| `/leagues/{league_id}/players/{player_id}` | GET | A | `private, must-revalidate` | Yes | User + League + GW + Player |

---

# 5) More / Account (Bundle C)

These endpoints support Phase C Bundle C screens:
- More (Account hub)
- Profile
- Settings
- Rules
- Contact / Support

Most endpoints here are **user-scoped** (no `league_id/current_gw` in `meta`), except league rules.

---

## 5.1 GET /me

Returns the authenticated user’s profile basics for account UI (alias/email/lang).

### Request
**Method:** GET  
**Path:** `/me`

**Caching:**
- Category A
- ETag scope: **User**
- Conditional requests: `If-None-Match` → `304`

### Response
```json
{
  "meta": {
    "server_time": "2026-02-10T09:00:00Z",
    "last_updated": "2026-02-10T08:59:30Z",
    "etag": "W/\"me-u123-1707559170\""
  },
  "data": {
    "me": {
      "profile_id": 123,
      "alias": "PlayerOne",
      "email": "player@example.com",
      "lang": "en",
      "created_at": "2025-09-01T12:00:00Z"
    }
  }
}
```

Notes:
- `email` is read-only in v1.
- `lang` is optional if you keep language purely client-side.

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

---

## 5.2 PATCH /me

Partial update of user profile fields used by the app (alias, lang).

### Request
**Method:** PATCH  
**Path:** `/me`

**Body (partial update):**
```json
{
  "alias": "NewAlias",
  "lang": "hu"
}
```

Rules:
- Only provided fields are updated.
- Server must validate alias constraints (length, allowed characters).

**Caching:** Category C (`no-store`)

### Response (success)
```json
{
  "meta": { "server_time": "2026-02-10T09:01:00Z", "last_updated": "2026-02-10T09:01:00Z", "etag": null },
  "data": { "ok": true }
}
```

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 422 `VALIDATION_ERROR` (invalid alias/lang)
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

Client behavior:
- Revalidate `GET /me`.
- Revalidate `GET /home` if alias is displayed there.

---

## 5.3 GET /me/teams

Returns all teams (competitors) that belong to the authenticated user across leagues.

### Request
**Method:** GET  
**Path:** `/me/teams`

**Caching:**
- Category A
- ETag scope: **User**
- Conditional requests: `If-None-Match` → `304`

### Response
```json
{
  "meta": {
    "server_time": "2026-02-10T09:02:00Z",
    "last_updated": "2026-02-10T09:01:40Z",
    "etag": "W/\"me-teams-u123-1707559320\""
  },
  "data": {
    "teams": [
      {
        "league": { "league_id": 1, "name": "BLSZ", "logo_url": "..." },
        "competitor": {
          "competitor_id": 9001,
          "teamname": "My Strikers",
          "created_at": "2025-09-10T10:00:00Z"
        }
      }
    ]
  }
}
```

Notes:
- This is intentionally compact. The client can navigate to league-scoped screens (Team/Rankings) using `league_id`.

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

---

## 5.4 DELETE /leagues/{league_id}/team

Deletes the authenticated user’s team (competitor) in the given league.

### Request
**Method:** DELETE  
**Path:** `/leagues/{league_id}/team`

Confirmation:
- The API does **not** need a complex security flow in v1.
- Client must show an explicit confirmation UI before calling this.

**Caching:** Category C (`no-store`)

### Response (success)
```json
{
  "meta": { "server_time": "2026-02-10T09:03:00Z", "last_updated": "2026-02-10T09:03:00Z", "etag": null },
  "data": { "ok": true }
}
```

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 403 `LEAGUE_FORBIDDEN` / `LEAGUE_ACCESS_DENIED`
- 404 `LEAGUE_NOT_FOUND` or `TEAM_NOT_FOUND`
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

Client behavior:
- Revalidate `GET /me/teams`.
- Revalidate `GET /home` (team snapshot may change).
- Revalidate `GET /leagues/{league_id}/fantasy` (rankings eligibility may change).

---

## 5.5 DELETE /me

Deletes the authenticated user profile and all associated data.

### Request
**Method:** DELETE  
**Path:** `/me`

Confirmation:
- Client must show an explicit confirmation UI (second step) before calling.

**Caching:** Category C (`no-store`)

### Response (success)
```json
{
  "meta": { "server_time": "2026-02-10T09:04:00Z", "last_updated": "2026-02-10T09:04:00Z", "etag": null },
  "data": { "ok": true }
}
```

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

Client behavior:
- Clear local auth tokens.
- Navigate to Login.

Idempotency guideline:
- Repeating the delete should return `200 { ok: true }` (or `404` if you prefer strict semantics), but pick one and keep it consistent.

---

## 5.6 GET /leagues/{league_id}/rules

Returns a display-oriented rules payload for the league (authoritative, read-only).

### Request
**Method:** GET  
**Path:** `/leagues/{league_id}/rules`

**Caching:**
- Category A (recommended)
- ETag scope: **League** (optionally include season lock state)
- Conditional requests: `If-None-Match` → `304`

### Response
```json
{
  "meta": {
    "server_time": "2026-02-10T09:05:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-10T08:58:00Z",
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
      "initial_budget": 80.0
    },
    "links": {
      "full_rules_url": "https://example.com/rules"
    }
  }
}
```

Notes:
- You can also implement this by reusing `GET /config` for the structured parts + a hosted rules URL; this endpoint is still useful as the “single authoritative rules payload” for the Rules screen.

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN`
- 403 `LEAGUE_FORBIDDEN` / `LEAGUE_ACCESS_DENIED`
- 404 `LEAGUE_NOT_FOUND`
- 429 `RATE_LIMITED`
- 500 `INTERNAL_ERROR`

---

## 5.7 POST /contact

Sends a support/feedback message to admins.

### Request
**Method:** POST  
**Path:** `/contact`

**Body:**
```json
{
  "subject": "Bug report",
  "message": "I cannot open the Team tab.",
  "context": {
    "app_version": "1.0.0",
    "league_id": 1
  }
}
```

Rules:
- `message` is required.
- `subject` is optional.
- `context` must not include secrets.

**Caching:** Category C (`no-store`)

### Response (success)
```json
{
  "meta": { "server_time": "2026-02-10T09:06:00Z", "last_updated": "2026-02-10T09:06:00Z", "etag": null },
  "data": { "ok": true }
}
```

Possible errors:
- 401 `AUTH_REQUIRED` / `AUTH_INVALID_TOKEN` (recommended if contact requires auth)
- 422 `VALIDATION_ERROR` (message empty/too long)
- 429 `CONTACT_RATE_LIMITED` (too many messages)
- 500 `INTERNAL_ERROR`

---

## Appendix D — Caching summary (More / Account)

| Endpoint | Method | Category | Cache-Control | ETag | ETag scope |
|---|---|---:|---|---|---|
| `/me` | GET | A | `private, must-revalidate` | Yes | User |
| `/me` | PATCH | C | `no-store` | No | n/a |
| `/me/teams` | GET | A | `private, must-revalidate` | Yes | User |
| `/leagues/{league_id}/team` | DELETE | C | `no-store` | No | n/a |
| `/me` | DELETE | C | `no-store` | No | n/a |
| `/leagues/{league_id}/rules` | GET | A | `private, must-revalidate` | Yes | League |
| `/contact` | POST | C | `no-store` | No | n/a |
