# Phase B Screens – Details Definition (v1)

This file defines a **screen template** for Phase B.
It is the single place where we pin down: payload(s), actions, rules/config dependencies, caching, and edge states.

---

## Screen template (copy/paste)

### <Screen Name>
**Type:** Screen / Tab / Modal / Flow  
**Primary route:** (mobile route name)  
**League-scoped:** Yes/No (how `league_id` is obtained)

#### 1) Purpose
- What the user is trying to do on this screen.

#### 2) Payload endpoint(s)
- **GET** …
- Notes: query params, when to call, refresh triggers.

#### 3) Actions (write endpoints)
List each action as:
- **Action name**
  - **Endpoint:** METHOD /path
  - **Request:** minimal fields
  - **Success:** what changes in UI + what to refresh
  - **Key error codes:** (from api-errors.md) + UX handling

#### 4) Core UI sections (data contract)
- Section A …
- Section B …
- Identify “must have” vs “nice to have”.

#### 5) Rules / config fields it cares about
- Rule IDs (core-rules.md) + fields expected in payload (or /config, /rules)

#### 6) Caching & invalidation
- Cache category (A/B/C), ETag key scope, and what events must trigger revalidation.

#### 7) Edge / empty / error states
- Not logged in / token expired
- No league selected / no competitor yet
- GW closed / season locked
- “Ranking not available yet”, etc.

#### 8) Telemetry (optional but recommended)
- Screen open event
- Action events (confirm transfer, switch league, etc.)
- Error events (code + endpoint)

---

# 1) Home / League switch

**Type:** Tab screen  
**Primary route:** `HomeTab`  
**League-scoped:** *Optional* — works without a selected league; becomes league-scoped when `selected_league_id` exists.

## 1) Purpose
- Let user pick a league context quickly.
- Show “what’s happening now”: deadline status, your team snapshot, quick highlights (Team of the Week), and previews (notifications/news).
- Provide the top-level entry point into the league context for other tabs.

## 2) Payload endpoint(s)
- **GET** `/home` (no league selected)
- **GET** `/home?league_id={league_id}` (league selected)

**When to call**
- On tab open (always revalidate via ETag).
- After league switch (call `GET /home?league_id=...` immediately).
- After actions elsewhere that affect “your_team” snapshot (e.g., after transfer confirm, team creation).

## 3) Actions (write endpoints)
Home is read-only in v1 (navigation only).
- *(Optional future)* mark notifications read:
  - **Endpoint:** `POST /me/notifications/read`
  - **Refresh:** `GET /home` (notifications preview)

## 4) Core UI sections (data contract)
**Must have**
- `league_selector`
  - `selected_league_id`
  - `leagues[]` with `league_id`, `name`, `logo_url`
  - `status.current_gw`, `status.deadline`, `status.is_open`
  - `competitor` snapshot (if user has a team in that league)

**When league selected (must have)**
- `league_context.gameweek` (gw, deadline, is_open, gamedate)
- `league_context.your_team` (teamname, rank, total_points, weekly_points, rank_change)

**Nice to have**
- `notifications_preview` (unread_count + latest items)
- `news_preview` (global or league mode)
- `highlights.team_of_the_week` (gw + list of players)

## 5) Rules / config fields it cares about
- **R3 Gameweek lifecycle**: show open/closed, countdown to deadline.
  - from payload: `league_selector.leagues[].status.*` and (if selected) `league_context.gameweek.*`
- **R8 Rankings**: show your team rank block.
  - from payload: `league_context.your_team.rank`, `previous_rank`, `rank_change`
- **R7 Scoring inputs** (display-only): Team of the Week preview.

## 6) Caching & invalidation
- **Cache category:** A (ETag + `private, must-revalidate`)
- **ETag scope:** user + (optional) selected league id
- **Invalidate / refresh after**
  - team creation (new competitor appears in selector + league_context)
  - transfer confirm (rank/weekly points may change)
  - private league actions if they generate notifications

## 7) Edge / empty / error states
- **401 AUTH_REQUIRED / AUTH_INVALID_TOKEN:** trigger refresh-token flow; if fails → login.
- **No leagues returned:** show “No leagues available” + retry.
- **League accessible but no competitor yet:** show CTA “Create your team” (goes to Team Management flow).
- **Season locked (R17.8):** show read-only message; team actions disabled elsewhere.

## 8) Telemetry (optional)
- `home_opened`
- `league_switched` (from_league_id, to_league_id)
- `home_refresh_failed` (error.code)

---

# 2) Team management (roster + transfers)

**Type:** Tab screen (includes subviews/modals: roster grid/list, player modal, transfer flow)  
**Primary route:** `TeamTab`  
**League-scoped:** Yes — requires active `league_id` (from Home league selector).

## 1) Purpose
- View and manage the current GW roster (positions 1–8).
- Set captain (starter-only).
- Perform transfers (1–2 or unlimited in free GW), with budget + constraints.

## 2) Payload endpoint(s)
- **GET** `/leagues/{league_id}/team`

**When to call**
- On tab open (revalidate via ETag).
- After any successful roster-changing action (captain/substitute/transfer confirm).
- After server rejects an action due to state changes (GW closed, etc.) → reload.

## 3) Actions (write endpoints)

### A) Set captain
- **Endpoint:** `POST /leagues/{league_id}/team/captain`
- **Request:** `{ "captain_player_id": <player_id> }`
- **Success:** update captain badge; refresh `GET /leagues/{league_id}/team`
- **Key error codes**
  - `GW_CLOSED` / `GW_NOT_OPEN` → show “Deadline passed”; refresh payload
  - `CAPTAIN_INVALID` / `CAPTAIN_NOT_STARTER` → show validation message

### B) Substitute / reorder (if implemented)
- **Endpoint:** `POST /leagues/{league_id}/team/substitute`
- **Request:** minimal, e.g. `{ "swap_pos_a": 6, "swap_pos_b": 7 }` (exact schema to be finalized)
- **Success:** refresh `GET /leagues/{league_id}/team`
- **Key error codes**
  - `GW_CLOSED` / `GW_NOT_OPEN`
  - `ROSTER_INVALID_POSITION`

### C) Transfers – quote (validation)
- **Endpoint:** `POST /leagues/{league_id}/transfers/quote`
- **Request:** `{ "outgoing_player_ids": [..], "incoming_player_ids": [..] }`
- **Success:** show summary + `rules_check` results (can be invalid but still 200 OK)
- **Key error codes**
  - (Prefer 200 + `rules_check.is_valid=false`; only hard errors for malformed requests / auth)

### D) Transfers – confirm
- **Endpoint:** `POST /leagues/{league_id}/transfers/confirm`
- **Request:** same arrays as quote
- **Success:** show toast “Transfers confirmed”; refresh `GET /leagues/{league_id}/team`
  - optionally refresh market list if open
- **Key error codes**
  - `TRANSFER_LIMIT_REACHED` (unless free GW)
  - `TRANSFER_BUDGET_INSUFFICIENT`
  - `MAX_PLAYERS_FROM_TEAM`
  - `TRANSFER_NOT_ALLOWED_GW_CLOSED` / `GW_CLOSED`

## 4) Core UI sections (data contract)
**Must have**
- `competitor` (competitor_id, teamname, credits, favorite_team_id)
- `gameweek` (gw, deadline, is_open, transfers_allowed, transfers_used)
- `roster`
  - `captain_player_id`
  - `positions[]` with `pos`, `player`, `team`, `price`
  - `ui`-useful stats: avg_points/form_points/weekly_points (nice but very useful)

**Nice to have**
- `next_fixture` per player (for decision making)
- `player modal` uses separate lookup endpoint, but can reuse roster data for initial render.

## 5) Rules / config fields it cares about
- **R3.5–R3.6** GW open/closed:
  - payload: `gameweek.is_open`, `gameweek.deadline`
- **R4.1–R4.3** roster size and starter/sub split:
  - implied by `positions[].pos`; client shows 1–6 starters, 7–8 subs
- **R4.6** max 2 players from same team:
  - config: `max_from_same_team` (via `/config` or embedded in payload)
- **R5** transfers:
  - payload: `transfers_allowed`, `transfers_used`, `competitor.credits`
  - free GW (R5.13): payload or `/rules`: `transfers.is_free_gw` and/or `free_transfer_gw`
- **R6** captain rules:
  - payload: `roster.captain_player_id`
  - client must restrict captain selection to positions 1–6 (UX only; server enforces)

## 6) Caching & invalidation
- **Cache category:** A (ETag + must-revalidate)
- **Invalidate / refresh after**
  - captain change → refresh `/team`
  - substitute/reorder → refresh `/team`
  - transfer confirm → refresh `/team` (+ `/market/players` if needed)

## 7) Edge / empty / error states
- **No competitor yet (team not created):**
  - show “Create team” flow entry (builder endpoint exists: `GET /leagues/{league_id}/team/builder`, then `POST /leagues/{league_id}/team`)
- **GW closed:** disable all actions, show “Read-only after deadline”.
- **Season locked (R17.8):** disable all actions; still show last roster snapshot.
- **Roster missing (R4.4–R4.5):** server should auto-create; if not → `ROSTER_NOT_FOUND` → show retry.
- **Transfer quote invalid:** show violations list from `rules_check.violations[]`.

## 8) Telemetry (optional)
- `team_opened` (league_id, gw)
- `captain_changed` (player_id)
- `transfer_quote_requested` (count_out, count_in)
- `transfer_confirmed` / `transfer_failed` (error.code)

---

# 3) Rankings

**Type:** Screen (tab or subview under “Leagues”; in Phase B we treat it as a core destination)  
**Primary route:** `RankingsScreen`  
**League-scoped:** Yes — requires `league_id`.

## 1) Purpose
- Show fantasy league rankings:
  - overall ranking (primary)
  - fan league ranking (if favorite team set)
  - list of private leagues with your membership status (and entry points)

## 2) Payload endpoint(s)
- **GET** `/leagues/{league_id}/fantasy`

**When to call**
- On screen open (revalidate via ETag).
- After private league actions (create/invite/accept/leave) → refresh.
- After admin recalculation / postponed-match updates → refresh (ETag will change).

## 3) Actions (write endpoints)
Rankings is read-only *unless* we embed private league entry points on the same screen.

If we include private league actions here (recommended UX):
- **Create private league**
  - **Endpoint:** `POST /leagues/{league_id}/private-leagues`
  - **Refresh:** `GET /leagues/{league_id}/fantasy`
- **Open private league detail**
  - **GET** `/leagues/{league_id}/private-leagues/{privateleague_id}` (detail screen)
- **Invite/search**
  - **GET** `/leagues/{league_id}/private-leagues/{id}/invite/search?q=...`
  - **POST** `/leagues/{league_id}/private-leagues/{id}/invite`
  - **Refresh:** detail payload + `/fantasy`
- **Leave**
  - **POST** `/leagues/{league_id}/private-leagues/{id}/leave`
  - **Refresh:** `/fantasy`

## 4) Core UI sections (data contract)
**Must have**
- `gameweek` context (gw, last_update_at, has_postponed_matches)
- `overall.items[]` (rank, previous_rank, rank_change, teamname, alias, total_points, weekly_points)
- `overall.you` (your competitor_id, rank)

**Conditional**
- `fan_league` (enabled + items) when favorite team exists

**Nice to have**
- `private_leagues.items[]` (name, admin_alias, member_count, your_status)

## 5) Rules / config fields it cares about
- **R8.1–R8.4** rankings ordering and postponed-match recalculation:
  - server is authoritative; client must display ties correctly
- **R9** private league membership rules (status display + admin capabilities)
- **R17.4 / R17.8** post-season: rankings still viewable; actions disabled

## 6) Caching & invalidation
- **Cache category:** A (ETag + must-revalidate)
- **Invalidate / refresh after**
  - any private league action
  - team creation (so you appear in ranking once eligible)
  - admin recalculation jobs (ETag changes)

## 7) Edge / empty / error states
- **RANKING_NOT_AVAILABLE (409):** show “Rankings not computed yet” + pull-to-refresh.
- **No competitor yet:** show “Create a team to appear in rankings.”
- **Fan league disabled:** hide the section.
- **Postponed matches:** show banner “Provisional rankings — postponed match(es) pending” (from `has_postponed_matches`).

## 8) Telemetry (optional)
- `rankings_opened` (league_id, gw)
- `private_league_created` / `invite_sent` / `leave_private_league`
- `rankings_refresh_failed` (error.code)

---
