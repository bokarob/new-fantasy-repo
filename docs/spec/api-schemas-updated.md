# API Schemas – Fantasy 9pin

This document defines the request and response payloads
for the core Fantasy 9pin API endpoints.

All schemas follow the conventions described in api-overview.md.

---

## Common Response Envelope

All endpoints return data wrapped in a common envelope.

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"resource-version\""
  },
  "data": { }
}


1. Home
### GET /home

Optional query params:
- `league_id` (int)

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"home-u123-l1-12-1707211140\""
  },
  "data": {
    "league_selector": {
      "selected_league_id": 1,
      "leagues": [
        {
          "league_id": 1,
          "name": "1. Liga",
          "logo_url": "...",
          "status": {
            "current_gw": 12,
            "deadline": "ISO-8601",
            "is_open": true
          },
          "competitor": {
            "competitor_id": 9001,
            "teamname": "PinKings"
          }
        }
      ]
    },

    "league_context": {
      "league_id": 1,
      "gameweek": {
        "gw": 12,
        "deadline": "ISO-8601",
        "is_open": true,
        "gamedate": "YYYY-MM-DD"
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

    "notifications_preview": {
      "unread_count": 3,
      "items": [
        {
          "notification_id": 501,
          "type": "invite_received",
          "title": "Private league invite",
          "created_at": "ISO-8601"
        }
      ]
    },

    "news_preview": {
      "mode": "global",
      "items": [
        {
          "news_id": 100,
          "title": "Welcome to Fantasy 9pin",
          "published_on": "ISO-8601",
          "image_url": "..."
        }
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

Notes:
- If `league_id` is **omitted**, `meta.league_id`, `meta.current_gw`, and `data.league_context` MAY be null.
- `data.highlights` MAY be null when not available.
- Caching: Category A (ETag + must-revalidate).


2. Team management (Roster + Transfers)
### GET /leagues/{league_id}/team

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
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
      "deadline": "ISO-8601",
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

Notes:
- If user has no competitor in this league, backend should either:
  - return a typed API error (recommended), or
  - return `competitor=null` and `roster=null` (less strict).
- Caching: Category A (ETag + must-revalidate).

### POST /leagues/{league_id}/team/captain

Request:
```json
{ "captain_player_id": 123 }
```

Response:
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true }
}
```

Caching: Category C (no-store)

### POST /leagues/{league_id}/team/substitute (optional v1)

Request:
```json
{ "swap_pos_a": 6, "swap_pos_b": 7 }
```

Response:
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true }
}
```

Caching: Category C (no-store)




3. Matches & Results (Bundle B)

### GET /leagues/{league_id}/matches

Optional query params:
- `gw` (int, optional) — defaults to the server current GW for the league.

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"matches-l1-gw12-1707310740\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "gameweek": {
      "gw": 12,
      "deadline": "ISO-8601 UTC",
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
        "kickoff_at": "ISO-8601 UTC",
        "status": "scheduled | in_progress | finished | postponed | cancelled",
        "is_postponed": false,

        "home": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
        "away": { "team_id": 12, "short": "ABC", "name": "ABC United", "logo_url": "..." },

        "score": { "home": 5, "away": 3 },

        "points": {
          "team_points_home": 2,
          "team_points_away": 0,
          "match_points_home": 16,
          "match_points_away": 8,
          "set_points_home": 10,
          "set_points_away": 6
        }
      }
    ]
  }
}
```

Field notes:
- `gw_nav` is recommended for UI navigation; if omitted, client can derive bounds from `/leagues/{league_id}/rules` (season fields) or app constants.

Caching: Category A (ETag + must-revalidate) — ETag scope: League + GW.

---

### GET /leagues/{league_id}/matches/{match_id}

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"match-99001-l1-1707311070\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
    "match": {
      "match_id": 99001,
      "kickoff_at": "ISO-8601 UTC",
      "status": "scheduled | in_progress | finished | postponed | cancelled",
      "is_postponed": false,
      "home": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },
      "away": { "team_id": 12, "short": "ABC", "name": "ABC United", "logo_url": "..." },
      "score": { "home": 5, "away": 3 }
    },

    "rows": [
      {
        "side": "home | away",
        "row": 1,
        "player_id": 123,
        "player_name": "John Example",
        "pins": 620,
        "setpoints": 2,
        "matchpoints": 1,
        "fantasy_points": 78.5,
        "was_starter": true,
        "was_substituted": false
      }
    ]
  }
}
```

Field notes:
- `row`, `was_starter`, `was_substituted` are optional if the source data does not provide them.

Caching: Category A (ETag + must-revalidate) — ETag scope: League + Match.

---

### GET /leagues/{league_id}/table

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"table-l1-gw12-1707311340\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,
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

Caching: Category A (ETag + must-revalidate) — ETag scope: League + Current GW.

---

### GET /leagues/{league_id}/stats/players

Optional query params:
- `week_gw` (int, optional) — which fixture GW to populate the “weekly” columns from.  
  If omitted, the server defaults this to the latest finished GW (`totals_through_gw`).
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
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

Caching: Category A (ETag + must-revalidate) — ETag scope: **League + week_gw (+ paging)**.  
Notes:
- `totals_through_gw` can be `< meta.current_gw` when the current GW is still open / not finished.
- Weekly fields (`week_*`) are optional; if you only need “latest GW fantasy points”, `week_fantasy_points` alone is enough.



4. Fantasy Rankings
### GET /leagues/{league_id}/fantasy

**Response (common envelope):**

```json
{league_id}/fantasy

{
  "meta": {
    "server_time": "2026-02-02T18:22:11Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-02T18:10:00Z",
    "etag": "W/\"fantasy-1-12-1706897400\""
  },
  "data": {
    "gameweek": {
      "gw": 12,
      "has_postponed_matches": false,
      "last_update_at": "2026-02-01T22:30:00Z"
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
        },
        {
          "rank": 2,
          "previous_rank": 3,
          "rank_change": 1,
          "competitor_id": 9001,
          "teamname": "PinKings",
          "alias": "you",
          "total_points": 3190.0,
          "weekly_points": 301.0
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


Notes:
- Caching: Category A (ETag + must-revalidate).




5. Player Modal
GET /leagues/{league_id}/players/{player_id}

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"player-l1-p123-u123-gw12-1707312270\""
  },
  "data": {
    "league_id": 1,
    "gw": 12,

    "player": {
      "player_id": 123,
      "name": "John Example",
      "team": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." }
    },

    "ownership": { "owned_by_you": true, "roster_position": 1 },

    "price": { "current": 12.5, "previous": 12.0 },

    "base_stats": {
      "avg_points": 51.2,
      "form_points": 256.0,
      "selection_percent": 18.4
    },

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

Field notes:
- `actions.disabled_reasons` is an array of machine-readable strings (e.g. `ALREADY_OWNED`, `BUDGET_INSUFFICIENT`, `MAX_PLAYERS_FROM_TEAM`).

Caching: Category A (ETag + must-revalidate) — ETag scope: User + League + Current GW + Player.





6. Market List
GET /leagues/{league_id}/market/players

Optional query params (v1):
- `q` (string, optional) — name search
- `team_id` (int, optional)
- `sort` (string, optional) — e.g. `price_asc | price_desc | avg_points_desc | form_points_desc`
- `limit` (int, optional; default 50; max 200)
- `offset` (int, optional; default 0)
- `outgoing_player_ids[]` (int[], optional) — if present, server MAY compute `availability` against the user roster + credits

**Response (common envelope):**

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
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
        "team": { "team_id": 34, "short": "SKC", "name": "SKC Example", "logo_url": "..." },

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

Field notes:
- If the response is **not contextual** (no `outgoing_player_ids[]`), the server may omit `context`.
- When `availability.can_select = true`, `disabled_reasons` should be an empty array.

Caching: Category A (ETag + must-revalidate).
- ETag scope is **League + Current GW (+ query params)** for non-contextual lists.
- If `availability` depends on the user roster/credits, ETag scope becomes **User + League + Current GW (+ query params)**.



7. Transfers
### POST /leagues/{league_id}/transfers/quote

Request:
```json
{
  "outgoing_player_ids": [123],
  "incoming_player_ids": [456]
}
```

Response (valid):
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
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

Response (invalid, still 200 OK):
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": {
    "is_valid": false,
    "summary": {
      "credits_before": 4.5,
      "credits_after": -1.0,
      "transfers_used_after": 1
    },
    "violations": [
      { "code": "TRANSFER_BUDGET_INSUFFICIENT", "message": "Not enough credits." },
      { "code": "MAX_PLAYERS_FROM_TEAM", "message": "Max 2 players from the same team." }
    ]
  }
}
```

Caching: Category C (no-store)

### POST /leagues/{league_id}/transfers/confirm

Request:
```json
{
  "outgoing_player_ids": [123],
  "incoming_player_ids": [456]
}
```

Response:
```json
{
  "meta": { "server_time": "ISO-8601 UTC", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601 UTC", "etag": null },
  "data": { "ok": true, "transfer_id": 70001 }
}


Caching: Category C (no-store)


8. Initial Team Creation
GET /leagues/{league_id}/team/builder

Returns rules and available players.
{
  "meta": {
    "server_time": "2026-02-02T19:20:00Z",
    "league_id": 1,
    "current_gw": 1
  },
  "data": {
    "rules": {
      "roster_size": 8,
      "starters": 6,
      "subs": 2,
      "budget": 80.0,
      "max_from_same_team": 2
    },

    "players": [
      {
        "player_id": 123,
        "name": "John Example",
        "team": { "team_id": 34, "short": "SKC", "logo_url": "..." },
        "price": 12.5,
        "stats": {
          "avg_points": 48.1
        }
      }
    ]
  }
}


POST /leagues/{league_id}/team
{
  "teamname": "PinKings",
  "player_ids": [123,124,125,126,127,128,129,130],
  "captain_player_id": 123,
  "favorite_team_id": 34
}

Creates competitor and initial roster.

Success response
{
  "meta": {
    "server_time": "2026-02-02T19:25:00Z",
    "league_id": 1
  },
  "data": {
    "competitor_id": 9001,
    "teamname": "PinKings",
    "credits": 4.5
  }
}

9. Rules / Configuration

GET /leagues/{league_id}/rules
Response:
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
      "is_free_gw": false
      "initial_budget": 80.0
    },
    "links": {
      "full_rules_url": "https://example.com/rules"
    }
  }
}

Field notes:
- `links.full_rules_url` is optional.
- This payload is display-only and authoritative.



9.2 GET /config

Purpose: deliver app-usable configuration and gameplay constants that clients may display or use for UI validation.
This can be global or league-scoped.

Query:

league_id (optional; if provided, include league-specific settings)

Response:
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"config-1-1706898000\""
  },
  "data": {
    "global": {
      "money_precision_decimals": 1,
      "captain_multiplier": 2.0,
      "otp": {
        "expiry_minutes": 10,
        "retry_limit": 5,
        "cooldown_seconds": [60, 120, 300]
      }
    },
    "league": {
      "league_id": 1,
      "roster_size": 8,
      "starters": 6,
      "subs": 2,
      "max_from_same_team": 2,
      "transfers_allowed_per_gw": 2,
      "initial_budget": 80.0
    }
  }
}

10. Auth

## Auth Endpoints

All auth endpoints return `Cache-Control: no-store`.
Errors follow the standard error envelope in `api-errors.md`.

### POST /auth/register
Creates an unverified profile and sends OTP.

Request:
```json
{
  "email": "user@example.com",
  "password": "plaintext-or-client-validated",
  "alias": "optional",
  "lang": "en"
}

Response (success):
{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": {
    "status": "otp_sent",
    "email": "user@example.com"
  }
}

Possible errors:

400: invalid payload
409: email already exists (if you add a specific code later)
429: OTP_RESEND_COOLDOWN / OTP_SEND_LIMIT

POST /auth/otp/send

Sends OTP for a given purpose.

Request:

{
  "email": "user@example.com",
  "purpose": "register"
}


Response:

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": { "status": "otp_sent" }
}


Errors:

429: OTP_RESEND_COOLDOWN, OTP_SEND_LIMIT


POST /auth/otp/verify

Verifies OTP for the given purpose.

Request:

{
  "email": "user@example.com",
  "otp": "123456",
  "purpose": "register"
}


Response (register success; recommended to return tokens immediately):

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": {
    "status": "verified",
    "tokens": {
      "access_token": "…",
      "access_expires_in_seconds": 1800,
      "refresh_token": "…",
      "refresh_expires_in_seconds": 2592000
    }
  }
}


Errors:

409: OTP_EXPIRED
422: OTP_INVALID
429: OTP_RETRY_LIMIT


POST /auth/login

Request:

{
  "email": "user@example.com",
  "password": "..."
}


Response:

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": {
    "tokens": {
      "access_token": "…",
      "access_expires_in_seconds": 1800,
      "refresh_token": "…",
      "refresh_expires_in_seconds": 2592000
    }
  }
}


Errors:

401: AUTH_INVALID_CREDENTIALS
403: AUTH_EMAIL_NOT_VERIFIED


POST /auth/token/refresh

Request:

{
  "refresh_token": "…"
}


Response (rotation recommended):

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": {
    "tokens": {
      "access_token": "…",
      "access_expires_in_seconds": 1800,
      "refresh_token": "…",
      "refresh_expires_in_seconds": 2592000
    }
  }
}


Errors:

401: AUTH_INVALID_TOKEN


POST /auth/logout

Request:

{
  "refresh_token": "…"
}


Response:

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": { "status": "logged_out" }
}


Errors:

401: AUTH_INVALID_TOKEN

POST /auth/password/forgot

Sends OTP (or equivalent) for password reset.

Request:

{
  "email": "user@example.com"
}


Response:

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": { "status": "otp_sent" }
}


Errors:
429: OTP rate limit codes

POST /auth/password/reset

Request:

{
  "email": "user@example.com",
  "otp": "123456",
  "new_password": "..."
}


Response:

{
  "meta": { "server_time": "ISO-8601 UTC" },
  "data": { "status": "password_reset" }
}


Errors:

409: OTP_EXPIRED
422: OTP_INVALID
429: OTP_RETRY_LIMIT


### Field Presence

- Optional fields may be omitted entirely.
- Nullable fields may be present with null value.
---

# Phase C — Notifications Schemas (v1)

These schemas implement the contracts in `api-contracts` for the Notifications screen.

## NotificationTarget

```json
{
  "kind": "league_home | team | rankings | private_league | private_league_invite | match | player | url",
  "league_id": 1,
  "params": {
    "privateleague_id": 200,
    "match_id": 999,
    "player_id": 456,
    "url": "https://example.com"
  }
}
```

Field notes:
- `kind` is required.
- `league_id` is required for league-scoped kinds (`league_home`, `team`, `rankings`, `private_league`, `private_league_invite`, `match`, `player`).
- `params` keys are optional and depend on `kind`.

## NotificationItem

```json
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
```

Field notes:
- `body` is optional (may be omitted for compact notifications).
- `target` is optional; if omitted, tapping the notification does nothing except mark-read.

## GET /notifications — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T10:00:00Z",
    "league_id": null,
    "current_gw": null,
    "last_updated": "2026-02-07T09:59:00Z",
    "etag": "W/\"notif-u123-1707299940\""
  },
  "data": {
    "unread_count": 3,
    "items": [ { "<NotificationItem>": "..." } ],
    "next_cursor": "opaque-string-or-null"
  }
}
```

## POST /notifications/{notification_id}/read — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T10:01:00Z",
    "league_id": null,
    "current_gw": null,
    "last_updated": "2026-02-07T10:01:00Z",
    "etag": null
  },
  "data": { "ok": true }
}
```

## POST /notifications/read-all — Response (optional v1)

```json
{
  "meta": {
    "server_time": "2026-02-07T10:02:00Z",
    "league_id": null,
    "current_gw": null,
    "last_updated": "2026-02-07T10:02:00Z",
    "etag": null
  },
  "data": { "ok": true, "read_count": 12 }
}
```
---

# Phase C — Private Leagues Schemas (Bundle A)

These schemas implement the Phase C Bundle A (Private Leagues) contracts in `phase-c-api-contracts.bundleA-updated.md`.

## PrivateLeagueSummary

```json
{
  "privateleague_id": 201,
  "leaguename": "Workmates",
  "admin_alias": "you",
  "member_count": 8,
  "your_role": "admin",
  "your_status": "member_confirmed"
}
```

## PrivateLeagueInviteSummary

```json
{
  "invite_id": "pl200-c9001",
  "privateleague_id": 200,
  "leaguename": "Friends League",
  "admin_alias": "adminGuy",
  "created_at": "2026-02-07T09:00:00Z",
  "status": "pending",
  "target": {
    "kind": "private_league_invite",
    "league_id": 1,
    "params": { "privateleague_id": 200 }
  }
}
```

Field notes:
- `invite_id` is an opaque identifier (string).
- `status` values (v1): `pending | accepted | declined | expired`.

---

## GET /leagues/{league_id}/private-leagues — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:00:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T11:59:00Z",
    "etag": "W/\"pl-list-u123-l1-1707307140\""
  },
  "data": {
    "league_id": 1,
    "invites": [ { "<PrivateLeagueInviteSummary>": "..." } ],
    "leagues": [ { "<PrivateLeagueSummary>": "..." } ]
  }
}
```

---

## POST /leagues/{league_id}/private-leagues — Request

```json
{ "leaguename": "Friends League" }
```

## POST /leagues/{league_id}/private-leagues — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:01:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T12:01:00Z",
    "etag": null
  },
  "data": { "ok": true, "privateleague_id": 200 }
}
```

---

## PrivateLeagueHeader

```json
{
  "privateleague_id": 200,
  "leaguename": "Friends League",
  "admin_profile_id": 777,
  "admin_alias": "adminGuy",
  "member_count": 8
}
```

## PrivateLeagueMembership

```json
{
  "your_role": "member",
  "your_status": "member_confirmed"
}
```

## PrivateLeaguePermissions

```json
{
  "can_invite": false,
  "can_remove_members": false,
  "can_rename": false,
  "can_delete": false,
  "can_leave": true
}
```

## PrivateLeagueStandingItem

```json
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
```

## PrivateLeagueStandings

```json
{
  "items": [ { "<PrivateLeagueStandingItem>": "..." } ],
  "you": { "competitor_id": 9001, "rank": 2 }
}
```

## PrivateLeaguePendingMember

```json
{
  "competitor_id": 9100,
  "alias": "kate",
  "teamname": "PinQueens",
  "invited_at": "2026-02-07T09:00:00Z"
}
```

---

## GET /leagues/{league_id}/private-leagues/{privateleague_id} — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:05:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T12:04:00Z",
    "etag": "W/\"pl-200-l1-gw12-1707307440\""
  },
  "data": {
    "league_id": 1,
    "privateleague": { "<PrivateLeagueHeader>": "..." },
    "membership": { "<PrivateLeagueMembership>": "..." },
    "gameweek": { "gw": 12, "is_open": true, "deadline": "2026-02-07T19:00:00Z" },
    "standings": { "<PrivateLeagueStandings>": "..." },
    "pending_members": [ { "<PrivateLeaguePendingMember>": "..." } ],
    "permissions": { "<PrivateLeaguePermissions>": "..." }
  }
}
```

---

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave — Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove — Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename — Request (optional v1)

```json
{ "leaguename": "New Name" }
```

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename — Response (optional v1)

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/delete — Response (optional v1)

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

---

## InviteSearchResult

```json
{
  "competitor_id": 9002,
  "profile_id": 555,
  "alias": "abc_user",
  "teamname": "StrikeForce",
  "already_member": false,
  "already_invited": false
}
```

## GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search — Response

```json
{
  "meta": {
    "server_time": "2026-02-07T12:10:00Z",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "2026-02-07T12:10:00Z",
    "etag": "W/\"pl-invite-search-u123-l1-qabc-1707307800\""
  },
  "data": {
    "league_id": 1,
    "q": "abc",
    "items": [ { "<InviteSearchResult>": "..." } ]
  }
}
```

---

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite — Request

```json
{ "competitor_id": 9002 }
```

## POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite — Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

---

## Manage Invites schemas (optional in-app inbox)

### GET /leagues/{league_id}/private-leagues/invites — Response

```json
{
  "meta": {
    "server_time": "ISO-8601",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601",
    "etag": "W/\"pl-inv-u123-l1-...\""
  },
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

### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept — Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline — Response

```json
{
  "meta": { "server_time": "ISO-8601", "league_id": 1, "current_gw": 12, "last_updated": "ISO-8601", "etag": null },
  "data": { "ok": true }
}
```

---

# Phase C — More / Account Schemas (Bundle C)

These schemas implement the Phase C Bundle C (More / Account) contracts in `phase-c-api-contracts.md`.

## Me

```json
{
  "profile_id": 123,
  "alias": "PlayerOne",
  "email": "player@example.com",
  "lang": "en",
  "created_at": "2025-09-01T12:00:00Z"
}
```

Field notes:
- `email` is read-only in v1.
- `lang` is optional if language is stored client-side only.

## MeTeamItem

```json
{
  "league": { "league_id": 1, "name": "BLSZ", "logo_url": "..." },
  "competitor": {
    "competitor_id": 9001,
    "teamname": "My Strikers",
    "created_at": "2025-09-10T10:00:00Z"
  }
}
```

---

## GET /me — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": null,
    "current_gw": null,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/"me-u123-1707559170""
  },
  "data": {
    "me": { "<Me>": "..." }
  }
}
```

---

## PATCH /me — Request

```json
{
  "alias": "NewAlias",
  "lang": "hu"
}
```

Notes:
- Partial update: omit fields you are not changing.

## PATCH /me — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": null,
    "current_gw": null,
    "last_updated": "ISO-8601 UTC",
    "etag": null
  },
  "data": { "ok": true }
}
```

---

## GET /me/teams — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": null,
    "current_gw": null,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/"me-teams-u123-1707559320""
  },
  "data": {
    "teams": [ { "<MeTeamItem>": "..." } ]
  }
}
```

---

## DELETE /leagues/{league_id}/team — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1,
    "current_gw": 12,
    "last_updated": "ISO-8601 UTC",
    "etag": null
  },
  "data": { "ok": true }
}
```

---

## DELETE /me — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": null,
    "current_gw": null,
    "last_updated": "ISO-8601 UTC",
    "etag": null
  },
  "data": { "ok": true }
}
```

Notes:
- Server should invalidate tokens/sessions; client should logout.

---

## POST /contact — Request

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

Field notes:
- `message` is required.
- `subject` and `context` are optional.
- `context` must not include secrets.

## POST /contact — Response

```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": null,
    "current_gw": null,
    "last_updated": "ISO-8601 UTC",
    "etag": null
  },
  "data": { "ok": true }
}
```
