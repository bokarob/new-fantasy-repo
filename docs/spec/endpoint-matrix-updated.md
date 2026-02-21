# Endpoint Matrix – Screens & Caching (v1)

This document maps API endpoints to:
- which mobile screens use them
- caching category and ETag strategy
- refresh triggers after actions

Caching categories:
- **A** = ETag + `Cache-Control: private, must-revalidate`
- **B** = TTL cache (`public` or `private` + `max-age`)
- **C** = `no-store` (never cache)

---

## Core Payload Endpoints

| Endpoint | Type | Used by screens | Cache | ETag key (v1) | Refresh triggers |
|---|---|---|---|---|---|
| GET /home | Payload | Home / League switch | A | user-scoped: max(updated) of league selector sources + notifications/news previews | after team creation/rename (if shown), after profile change (alias shown), after private league actions (if notifications preview), after transfer confirm (if home shows your team snapshot) |
| GET /home?league_id={id} | Payload | Home / League switch | A | user+league scoped: include league context (GW, your_team snapshot, highlights) | after transfer confirm, after team creation, after admin recalculation (rank/points), after private league actions if surfaced |
| GET /leagues/{league_id}/team | Payload | Team management, Player Detail modal (context) | A | user+league+GW scoped: max(updated) of roster (GW), competitor credits/teamname, transfer usage (GW) | after captain change, substitution, transfer confirm, team creation |
| GET /leagues/{league_id}/matches?gw={gw} | Payload | Matches (Matches subview) | A | league+GW scoped: max(updated) of matches + playerresult (gw) | after admin imports results / recalculation |
| GET /leagues/{league_id}/matches/{match_id} | Payload | Match Detail | A | league+match scoped: max(updated) of match + playerresult rows (match) | after admin imports results / corrections |
| GET /leagues/{league_id}/table | Payload | Matches (Table subview) | A (or B if stable) | league+currentGW scoped: max(updated) of leaguetable rows | after admin updates leaguetable / recalculation |
| GET /leagues/{league_id}/stats/players | Payload | Matches (Stats subview) | A | league+week_gw(+paging) scoped: max(updated) of playerresult (totals window) + playertrade (if form shown) | after admin imports results / recalculation |
| GET /leagues/{league_id}/fantasy | Payload | Rankings | A | league+GW scoped: max(updated) of teamranking/teamresult + private league membership updates | after private league create/invite/accept/leave; after admin recalculation; after team creation (eligibility) |
| GET /leagues/{league_id}/rules | Payload | Rules | A | league scoped: max(updated) of league rules snapshot | after admin rule/config change; on app foreground; after league switch in Rules |
| GET /me | Payload | More (Account hub), Profile, Settings | A | max(updated) of profile fields | after profile update |
| GET /me/teams | Payload | Profile | A | max(updated) of competitor rows for profile | after team creation/deletion, rename |
| GET /notifications | Payload | Notifications, Home preview source | A | max(updated) of notifications for profile | after notification events |

## Lookup Endpoints

| Endpoint | Type | Used by screens | Cache | ETag key (v1) | Refresh triggers |
|---|---|---|---|---|---|
| GET /leagues/{league_id}/players/{player_id} | Lookup | Player Detail (modal), Team management (tap player), Matches/Stats (tap player), Market (tap player) | A | user+league+GW+player scoped: max(updated) of player + playertrade (GW) + playerresult window + ownership state | after admin result import; after transfer confirm; after captain change (if player modal exposes it) |
| GET /leagues/{league_id}/market/players | Lookup | Transfer Market (Players list) | A | league+currentGW+query scoped ( + user if contextual ): max(updated) of playertrade (GW) + playerresult (if form shown) | after transfer confirm (ownership/availability), after admin updates prices/results |
| GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q= | Lookup | Invite Members | B (short TTL) | user+league(+q) scoped | none |

## Action Endpoints (Never cached)

| Endpoint | Type | Used by screens | Cache | Notes |
|---|---|---|---|---|
| POST /leagues/{league_id}/team/captain | Action | Team management, Player Detail (modal) | C | refresh/revalidate GET /leagues/{league_id}/team |
| POST /leagues/{league_id}/team/substitute | Action | Team management | C | refresh/revalidate GET /leagues/{league_id}/team |
| POST /leagues/{league_id}/transfers/quote | Action-like (validation) | Team management (transfer flow), Transfer Market, Player Detail (modal) | C | should not be cached; prefer 200 with is_valid=false for rule violations |
| POST /leagues/{league_id}/transfers/confirm | Action | Team management (transfer flow), Transfer Market, Player Detail (modal) | C | refresh/revalidate GET /leagues/{league_id}/team; also revalidate GET /leagues/{league_id}/market/players (if open/contextual), GET /home?league_id and GET /leagues/{league_id}/fantasy |
| POST /leagues/{league_id}/team | Action | Team creation flow | C | refresh /home?league_id + /team + /fantasy |
| DELETE /leagues/{league_id}/team | Action | Profile | C | refresh /me/teams, /home, /fantasy |
| PATCH /me | Action | Profile, Settings | C | refresh /me (and /home if alias is shown there) |
| DELETE /me | Action | Profile | C | on success: logout + clear local caches/session |
| POST /contact | Action | Contact / Support | C | no cache invalidation required; show success state |

## Auth Endpoints (Never cached)

| Endpoint | Type | Used by screens | Cache | Notes |
|---|---|---|---|---|
| POST /auth/register | Auth | Registration | C | no-store |
| POST /auth/otp/send | Auth | Registration/Login/Reset | C | no-store + rate limit |
| POST /auth/otp/verify | Auth | Registration/Login/Reset | C | no-store |
| POST /auth/login | Auth | Login | C | no-store |
| POST /auth/token/refresh | Auth | Token refresh | C | no-store |
| POST /auth/logout | Auth | More (Logout action) | C | no-store |

---
---


## Notifications (Inbox)

| Endpoint | Method | Screen(s) | Purpose | Cache category | ETag scope | Notes |
|---|---|---|---|---:|---|---|
| `/notifications` | GET | Notifications | User notification list + global unread count (paged) | A | User | Supports `filter`, `cursor`, `limit`. ETag + 304. |
| `/notifications/{notification_id}/read` | POST | Notifications | Mark one notification as read | C | n/a | On success, revalidate `/notifications` and (optionally) `/home` badge. |
| `/notifications/read-all` *(optional v1)* | POST | Notifications | Mark all notifications as read | C | n/a | On success, revalidate `/notifications` and `/home` badge. |

**Cross-screen dependency notes**
- Home (Phase B) may show a notifications preview + `unread_count`. If so, any notifications write should also trigger Home revalidation.
---

## Phase C — Private Leagues (Bundle A)

### Screens
- Private Leagues (List)
- Private League (Detail / Standings)
- Invite Members (Search + Invite)
- Manage Invites (optional)

| Endpoint | Method | Screen(s) | Purpose | Cache category | ETag scope | Notes |
|---|---|---|---|---:|---|---|
| `/leagues/{league_id}/private-leagues` | GET | Private Leagues (List) | List user’s private leagues + pending invites | A | User + League | Revalidate after create/leave/accept/decline. |
| `/leagues/{league_id}/private-leagues` | POST | Private Leagues (List) | Create private league | C | n/a | On success: revalidate list, navigate to detail (optional). |
| `/leagues/{league_id}/private-leagues/{privateleague_id}` | GET | Private League (Detail) | Header + membership + standings + pending members | A | User + League + Current GW | Standings GW-aware; revalidate after membership changes. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/leave` | POST | Private League (Detail), Private Leagues (List) | Leave private league | C | n/a | On success: revalidate list; navigate back. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove` | POST | Private League (Detail) | Remove member (admin) | C | n/a | Revalidate detail + list. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/rename` *(optional v1)* | POST | Private League (Detail) | Rename private league (admin) | C | n/a | Revalidate detail + list. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/delete` *(optional v1)* | POST | Private League (Detail) | Delete private league (admin) | C | n/a | Revalidate list; navigate back. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search` | GET | Invite Members | Autocomplete eligible invitees | B | User + League (+ q) | Short TTL; exclude existing members/invited. |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/invite` | POST | Invite Members | Send invite (admin) | C | n/a | Generates `invite_received` notification for invitee. |
| `/leagues/{league_id}/private-leagues/invites` *(optional)* | GET | Manage Invites | List pending invites | A | User + League | Can be skipped if Notifications covers invites. |
| `/leagues/{league_id}/private-leagues/invites/{invite_id}/accept` *(optional)* | POST | Manage Invites | Accept invite | C | n/a | Generates `invite_accepted` notification for admin; revalidate list+detail. |
| `/leagues/{league_id}/private-leagues/invites/{invite_id}/decline` *(optional)* | POST | Manage Invites | Decline invite | C | n/a | Generates `invite_declined` notification for admin; revalidate list. |

**Cross-screen dependency notes**
- Invite actions should also affect Notifications unread counts (revalidate `/notifications`) and Home preview if it shows invite badge.
- Private league standings reuse the ranking item shape; changes can follow GW recalculation.
---

## Phase C — Matches & Results (Bundle B)

### Screens
- Matches (Tab) — subviews: Matches / Table / Stats
- Match Detail (screen)

| Endpoint | Method | Screen(s) | Purpose | Cache category | ETag scope | Notes |
|---|---|---|---|---:|---|---|
| `/leagues/{league_id}/matches?gw={gw}` | GET | Matches (Matches subview) | Fixtures + results for the selected GW | A | League + GW | GW selector drives `gw`. If `gw` omitted, server may default to current GW. |
| `/leagues/{league_id}/matches/{match_id}` | GET | Match Detail | Full match detail for deep links + detail screen | A | League + Match | Recommended to support Notifications `target.kind="match"`. |
| `/leagues/{league_id}/table` | GET | Matches (Table subview) | Real league standings table | A | League + Current GW | Keep Category A for consistency (even if relatively stable). |
| `/leagues/{league_id}/stats/players` | GET | Matches (Stats subview) | Player stats leaderboard (season totals + optional weekly column) | A | League + week_gw (+ paging) | Optional `week_gw` (defaults to latest finished GW). Supports `limit/offset`. |

**Cross-screen dependency notes**
- Tapping a player in Match Detail / Stats should open Player Detail via `GET /leagues/{league_id}/players/{player_id}`.

---

## Phase C — Players & Market (Bundle B)

### Screens
- Transfer Market (Players list)
- Player Detail (Modal)

| Endpoint | Method | Screen(s) | Purpose | Cache category | ETag scope | Notes |
|---|---|---|---|---:|---|---|
| `/leagues/{league_id}/market/players` | GET | Transfer Market | Player list (filters/sort) + optional availability vs roster/budget | A | League + Current GW (+ query) *(+ User if contextual)* | Cache key MUST include all query params (esp. `outgoing_player_ids[]`). |
| `/leagues/{league_id}/players/{player_id}` | GET | Player Detail (Modal) | Player modal payload (ownership, price, actions) | A | User + League + Current GW + Player | Used from Team, Matches/Stats, Market, and Notifications deep links. |
| `/leagues/{league_id}/transfers/quote` | POST | Transfer Market / Team transfer flow | Validate transfer selection (read-only) | C | n/a | Does not modify state; do not invalidate caches on success. |
| `/leagues/{league_id}/transfers/confirm` | POST | Transfer Market / Team transfer flow | Persist transfers | C | n/a | On success: revalidate `/team`, and if open revalidate `/market/players` + the active `/players/{player_id}` modal; also revalidate `/home?league_id` and `/fantasy`. |

---

## Phase C — More / Account (Bundle C)

### Screens
- More (Account hub)
- Profile
- Settings
- Rules
- Contact / Support

| Endpoint | Method | Screen(s) | Purpose | Cache category | ETag scope | Notes |
|---|---|---|---|---:|---|---|
| `/me` | GET | More, Profile, Settings | Account header basics (alias/email/lang) | A | User | User-scoped ETag. Use 304 on If-None-Match. |
| `/me` | PATCH | Profile, Settings | Update alias/lang (partial update) | C | n/a | On success: revalidate `/me` (and `/home` if alias is displayed there). |
| `/me/teams` | GET | Profile | List user’s teams across leagues (grouped) | A | User | Revalidate after team creation/deletion/rename. |
| `/leagues/{league_id}/team` | DELETE | Profile | Delete competitor/team in a specific league | C | n/a | On success: revalidate `/me/teams`, `/home` and `/leagues/{league_id}/fantasy`; also clear cached `/leagues/{league_id}/team` payload if present. |
| `/me` | DELETE | Profile | Delete user profile (and associated data) | C | n/a | On success: logout; clear all cached user data. |
| `/leagues/{league_id}/rules` | GET | Rules | League rules/config payload for display | A | League | Optionally include a hosted `full_rules_url` for long-form text. |
| `/contact` | POST | Contact / Support | Send feedback/support message | C | n/a | Rate limit via 429 `CONTACT_RATE_LIMITED`. |
