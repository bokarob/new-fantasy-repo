# Phase B API Contracts (v1)

This document defines the **API contract** for the Phase B core screens:

It complements:
- `phase-b-screens.md` (screen behavior + UX expectations)
- `api-overview.md` (API philosophy + naming)
- `api-errors.md` (error codes)
- `caching.md` (global caching rules)

All timestamps are ISO-8601 UTC. Monetary values (credits/prices) are numbers with 1 decimal.

---

## 0) Common conventions

### 0.1 Common response envelope (all endpoints)

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

Notes
- `meta.league_id` is **null** for non-league-scoped responses (e.g., `GET /home` without `league_id`).
- `meta.current_gw` is the server’s current GW for the relevant league context (or null if not applicable).
- The HTTP response should also include the same `ETag` value in the header for cacheable GETs.

### 0.2 Common caching headers

For **Category A (default for user/league payloads)**:
- Response headers:
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
- Client request headers on refresh:
  - `If-None-Match: W/"..."`

If unchanged, server responds:
- `304 Not Modified` (no body)

### 0.3 League context validation (authz)

All league-scoped endpoints must validate:
- user is authenticated
- user has access to the `league_id`
- user has a competitor in that league when required (or return a clear error / null sections)

---

# 1) Home / League switch

## 1.1 GET /home

Returns the data needed for the Home tab, and optionally league-context when `league_id` is supplied.

### Request

**Method:** GET  
**Path:** `/home`  
**Query params (optional):**
- `league_id` (int) — if present, return league context sections for that league.

**Caching:**
- Category A (ETag + must-revalidate)
- ETag scope: user + (optional) league_id

### Response (no league selected)

```json
{
  "meta": {
    "server_time": "2026-02-06T12:00:00Z",
    "league_id": null,
    "current_gw": null,
    "last_updated": "2026-02-06T11:59:00Z",
    "etag": "W/\"home-u123-1707211140\""
  },
  "data": {
    "league_selector": {
      "selected_league_id": null,
      "leagues": [
        {
          "league_id": 1,
          "name": "1. Liga",
          "logo_url": "...",
          "status": {
            "current_gw": 12,
            "deadline": "2026-02-06T19:00:00Z",
            "is_open": true
          },
          "competitor": {
            "competitor_id": 9001,
            "teamname": "PinKings"
          }
        }
      ]
    },

    "league_context": null,

    "notifications_preview": {
      "unread_count": 3,
      "items": [
        {
          "notification_id": 501,
          "type": "invite_received",
          "title": "Private league invite",
          "created_at": "2026-02-06T09:00:00Z"
        }
      ]
    },

    "news_preview": {
      "mode": "global",
      "items": [
        {
          "news_id": 100,
          "title": "Welcome to Fantasy 9pin",
          "published_on": "2026-02-05T08:00:00Z",
          "image_url": "..."
        }
      ]
    },

    "highlights": null
  }
}
```

### Response (league selected)

If `league_id` is provided and accessible, the response fills in `league_context` and may fill in `highlights`.
`news_preview.mode` may switch to `league`.

```json
{
  "meta": {
    "server_time": "2026-02-06T12:00:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-06T11:59:00Z",
    "etag": "W/\"home-u123-l1-12-1707211140\""
  },
  "data": {
    "league_selector": { "...": "..." },

    "league_context": {
      "league_id": 1,
      "gameweek": {
        "gw": 12,
        "deadline": "2026-02-06T19:00:00Z",
        "is_open": true,
        "gamedate": "2026-02-06"
      },
      "your_team": {
        "competitor_id": 9001,
        "teamname": "PinKings",
        "rank": 2,
        "previous_rank": 2,
        "rank_change": 0,
        "total_points": 3190.0,
        "weekly_points": 301.0
      }
    },

    "notifications_preview": { "...": "..." },

    "news_preview": {
      "mode": "league",
      "items": [
        { "news_id": 201, "title": "GW12 update", "published_on": "2026-02-06T08:00:00Z", "image_url": "..." }
      ]
    },

    "highlights": {
      "team_of_the_week": {
        "gw": 11,
        "players": [
          {
            "player_id": 123,
            "name": "John Example",
            "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
            "pins": 620,
            "weekly_points": 78.5
          }
        ]
      }
    }
  }
}
```

### Cache invalidation / refresh triggers (client-side)

After a successful write that affects the Home snapshot, the client should revalidate `GET /home` and/or `GET /home?league_id=...`:
- team created (competitor appears, your_team block becomes available)
- transfer confirmed (your_team weekly points/rank may change)
- private league actions that generate notifications (preview changes)
- profile/teamname changes if displayed on Home

---

# 2) Team management (roster + transfers)

## 2.1 GET /leagues/{league_id}/team

Returns the full state needed to render the Team tab for the current GW.

### Request

**Method:** GET  
**Path:** `/leagues/{league_id}/team`  
**Query params:** none (current GW implied)

**Caching:**
- Category A (ETag + must-revalidate)
- ETag scope: user + league_id + current_gw

### Response

```json
{
  "meta": {
    "server_time": "2026-02-06T12:00:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-06T11:59:00Z",
    "etag": "W/\"team-u123-l1-12-1707211140\""
  },
  "data": {
    "competitor": {
      "competitor_id": 9001,
      "teamname": "PinKings",
      "credits": 4.5,
      "favorite_team_id": 34
    },

    "gameweek": {
      "gw": 12,
      "deadline": "2026-02-06T19:00:00Z",
      "is_open": true,
      "transfers_allowed": 2,
      "transfers_used": 0
    },

    "roster": {
      "captain_player_id": 123,
      "positions": [
        {
          "pos": 1,
          "player": { "player_id": 123, "name": "John Example" },
          "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
          "price": 12.5,
          "stats": {
            "avg_points": 51.2,
            "form_points": 256.0,
            "weekly_points": 78.5
          },
          "next_fixture": {
            "gw": 12,
            "opponent": { "team_id": 12, "short": "ABC", "logo_url": "..." },
            "home_away": "H"
          }
        }
      ]
    },

    "config": {
      "max_from_same_team": 2,
      "starters_count": 6,
      "subs_count": 2
    }
  }
}
```

Notes
- `data.config` may be embedded as shown, or delivered via a separate `/config` endpoint later.
- If the user has **no competitor in the league**, return an error (`NO_COMPETITOR`) or return `competitor=null` + `roster=null` (choose one approach and keep consistent).

### Cache invalidation / refresh triggers (client-side)

After any successful roster mutation:
- always refresh/revalidate `GET /leagues/{league_id}/team`

This includes:
- captain change
- substitute/reorder (if supported)
- transfer confirm
- team creation (first-time roster creation)

---

## 2.2 POST /leagues/{league_id}/team/captain

Set the captain for the current GW roster.

### Request

```json
{
  "captain_player_id": 123
}
```

### Response (success)

```json
{
  "meta": { "server_time": "2026-02-06T12:01:00Z", "league_id": 1, "current_gw": 12, "last_updated": "2026-02-06T12:01:00Z", "etag": null },
  "data": { "ok": true }
}
```

### Errors (examples)
- `GW_CLOSED` / `GW_NOT_OPEN`
- `CAPTAIN_INVALID`
- `CAPTAIN_NOT_STARTER`

Client behavior:
- show message
- revalidate `GET /leagues/{league_id}/team` if the error indicates state drift (GW closed, roster changed)

Caching: Category C (no-store)

---

## 2.3 POST /leagues/{league_id}/team/substitute  (optional v1)

Swap two roster positions.

### Request (minimal, position based)

```json
{
  "swap_pos_a": 6,
  "swap_pos_b": 7
}
```

### Response (success)

```json
{ "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null }, "data": { "ok": true } }
```

### Errors (examples)
- `GW_CLOSED` / `GW_NOT_OPEN`
- `ROSTER_INVALID_POSITION`
- `ROSTER_SWAP_NOT_ALLOWED`

Caching: Category C (no-store)

---

## 2.4 POST /leagues/{league_id}/transfers/quote

Validate a candidate transfer. Prefer returning **200 OK** with `is_valid=false` for rule violations
(only use errors for malformed requests / auth).

### Request

```json
{
  "outgoing_player_ids": [123],
  "incoming_player_ids": [456]
}
```

### Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": {
    "is_valid": true,
    "summary": {
      "credits_before": 4.5,
      "credits_after": 2.0,
      "transfers_used_after": 1
    },
    "violations": []
  }
}
```

If invalid:

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": {
    "is_valid": false,
    "summary": { "credits_before": 4.5, "credits_after": -1.0, "transfers_used_after": 1 },
    "violations": [
      { "code": "TRANSFER_BUDGET_INSUFFICIENT", "message": "Not enough credits." },
      { "code": "MAX_PLAYERS_FROM_TEAM", "message": "Max 2 players from the same team." }
    ]
  }
}
```

Caching: Category C (no-store)

---

## 2.5 POST /leagues/{league_id}/transfers/confirm

Confirm and persist the transfer for the current GW.

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
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": {
    "ok": true,
    "transfer_id": 70001
  }
}
```

### Errors (examples)
- `TRANSFER_LIMIT_REACHED` (unless free GW)
- `TRANSFER_BUDGET_INSUFFICIENT`
- `MAX_PLAYERS_FROM_TEAM`
- `GW_CLOSED` / `TRANSFER_NOT_ALLOWED_GW_CLOSED`

Client behavior:
- on success: refresh/revalidate `GET /leagues/{league_id}/team`
- optionally refresh `GET /home?league_id=...` (snapshot + rank change)
- optionally refresh `GET /leagues/{league_id}/market/players` if market is open

Caching: Category C (no-store)

---

# 3) Rankings

## 3.1 GET /leagues/{league_id}/fantasy

Returns the league rankings payload (overall + optional fan league + private leagues list).

### Request

**Method:** GET  
**Path:** `/leagues/{league_id}/fantasy`  
**Query params:** optional pagination in v2 (not required in v1 if list is bounded)

**Caching:**
- Category A (ETag + must-revalidate)
- ETag scope: league_id + current_gw (+ private league membership updates if included)

### Response

```json
{
  "meta": {
    "server_time": "2026-02-06T12:00:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-06T11:59:00Z",
    "etag": "W/\"fantasy-1-12-1707211140\""
  },
  "data": {
    "gameweek": {
      "gw": 12,
      "has_postponed_matches": false,
      "last_update_at": "2026-02-06T11:30:00Z"
    },

    "overall": {
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

    "fan_league": {
      "enabled": true,
      "favorite_team_id": 34,
      "favorite_team": { "team_id": 34, "name": "SKC Example", "short": "SKC", "logo_url": "..." },
      "items": [
        {
          "rank": 1,
          "previous_rank": 1,
          "rank_change": 0,
          "competitor_id": 9001,
          "teamname": "PinKings",
          "alias": "you",
          "total_points": 3190.0,
          "weekly_points": 301.0
        }
      ]
    },

    "private_leagues": {
      "items": [
        {
          "privateleague_id": 200,
          "leaguename": "Friends League",
          "admin_alias": "adminGuy",
          "member_count": 8,
          "your_status": "member_confirmed"
        }
      ]
    }
  }
}
```

### Errors (examples)
- `RANKING_NOT_AVAILABLE` (recommended as 409) when rankings not computed yet

### Cache invalidation / refresh triggers (client-side)

Revalidate `GET /leagues/{league_id}/fantasy` after:
- private league actions (create/invite/accept/leave)
- team creation (user becomes eligible / appears)
- recalculation jobs / postponed match resolution (ETag changes)

---

## Appendix A — Caching summary (for these 3 screens)

| Endpoint | Category | Cache-Control | ETag | Notes |
|---|---:|---|---|---|
| GET /home | A | private, must-revalidate | Yes | scope: user (+ league_id if provided) |
| GET /leagues/{league_id}/team | A | private, must-revalidate | Yes | scope: user + league + current_gw |
| GET /leagues/{league_id}/fantasy | A | private, must-revalidate | Yes | scope: league + current_gw |
| POST /... (all actions) | C | no-store | No | never cache writes |

