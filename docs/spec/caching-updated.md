# Caching Rules (v1) – Fantasy 9pin API

This document defines caching rules for the Fantasy 9pin API.
The goals are:
- reduce server load
- improve mobile responsiveness
- avoid stale or incorrect game-critical state

Caching must never break rule correctness. When in doubt, prefer correctness over caching.

---

## 1. Principles

1) **Server remains authoritative.**  
Clients may cache responses, but must treat them as potentially stale.

2) **Prefer conditional requests over long TTL.**  
Use ETags so clients can revalidate cheaply.

3) **Do not cache actions.**  
POST/DELETE endpoints should not be cached.

4) **Cache screen payloads cautiously.**  
Cache reads (GET), but invalidate/refresh after writes.

---

## 2. Standard Headers

### 2.1 ETag Support (recommended for screen payload endpoints)

For cacheable GET endpoints, the server should return:

- `ETag: W/"..."`
- `Cache-Control: private, must-revalidate`

Clients should send:
- `If-None-Match: W/"..."`

If unchanged, server returns:
- `304 Not Modified` with no body.

### 2.2 Cache-Control Baseline

Use one of these per endpoint:

#### A) Revalidate always (default for user/league data)

Cache-Control: private, must-revalidate


#### B) Short-lived caching (for mostly static data)

Cache-Control: public, max-age=3600


#### C) No caching (for sensitive or write endpoints)

Cache-Control: no-store



## 3. What Clients Cache (recommended behavior)

### 3.1 Mobile App
- Store ETags per endpoint (+ league_id/gw context when relevant).
- Store the last successful response body locally.
- On refresh, always revalidate using `If-None-Match` rather than relying on TTL.
- After any successful write, refresh affected payload(s).

### 3.2 Web App
- Can use the same ETag logic, or can skip caching initially.
- Server-side caching (PHP) may be introduced later after correctness is stable.

---

## 4. Endpoint Caching Categories


### 4.1 Endpoint → cache category mapping (Phase B + Phase C Bundles A–C)

| Endpoint | Method | Category | Cache-Control | ETag | ETag scope |
|---|---|---:|---|---|---|
| `/home` | GET | A | `private, must-revalidate` | Yes | **User** |
| `/home?league_id={league_id}` | GET | A | `private, must-revalidate` | Yes | **User + League + Current GW** |
| `/me` | GET | A | `private, must-revalidate` | Yes | **User** |
| `/me` | PATCH | C | `no-store` | No | n/a |
| `/me/teams` | GET | A | `private, must-revalidate` | Yes | **User** |
| `/notifications` | GET | A | `private, must-revalidate` | Yes | **User** |
| `/notifications/{notification_id}/read` | POST | C | `no-store` | No | n/a |
| `/notifications/read-all` *(optional v1)* | POST | C | `no-store` | No | n/a |
| `/leagues/{league_id}/team` | GET | A | `private, must-revalidate` | Yes | **User + League + Current GW** |
| `/leagues/{league_id}/team` | DELETE | C | `no-store` | No | n/a |
| `/leagues/{league_id}/matches?gw={gw}` | GET | A | `private, must-revalidate` | Yes | **League + GW** |
| `/leagues/{league_id}/matches/{match_id}` | GET | A | `private, must-revalidate` | Yes | **League + Match** |
| `/leagues/{league_id}/table` | GET | A | `private, must-revalidate` | Yes | **League + Current GW** |
| `/leagues/{league_id}/stats/players?week_gw={gw}` | GET | A | `private, must-revalidate` | Yes | **League + week_gw (+ paging)** |
| `/leagues/{league_id}/fantasy` | GET | A | `private, must-revalidate` | Yes | **League + Current GW** |
| `/leagues/{league_id}/players/{player_id}` | GET | A | `private, must-revalidate` | Yes | **User + League + Current GW + Player** |
| `/leagues/{league_id}/market/players` | GET | A | `private, must-revalidate` | Yes | **League + Current GW (+ query)** *(+ User if contextual)* |
| `/leagues/{league_id}/rules` | GET | A | `private, must-revalidate` | Yes | **League** |
| `/leagues/{league_id}/team/captain` | POST | C | `no-store` | No | n/a |
| `/leagues/{league_id}/team/substitute` *(optional v1)* | POST | C | `no-store` | No | n/a |
| `/leagues/{league_id}/transfers/quote` | POST | C | `no-store` | No | n/a |
| `/leagues/{league_id}/transfers/confirm` | POST | C | `no-store` | No | n/a |
| `/leagues/{league_id}/private-leagues` | GET | A | `private, must-revalidate` | Yes | **User + League** |
| `/leagues/{league_id}/private-leagues/{privateleague_id}` | GET | A | `private, must-revalidate` | Yes | **User + League + Current GW** |
| `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search` | GET | B | short TTL | Optional | **User + League (+ q)** |
| `/leagues/{league_id}/private-leagues/invites` *(optional)* | GET | A | `private, must-revalidate` | Yes | **User + League** |
| private league writes (create/leave/invite/accept/decline/remove/rename/delete) | POST | C | `no-store` | No | n/a |
| `/contact` | POST | C | `no-store` | No | n/a |
| `/me` | DELETE | C | `no-store` | No | n/a |

- Category **A** endpoints should support conditional requests (`If-None-Match` → `304`).
- Category **C** endpoints are writes or sensitive operations and must never be cached.

### Category A – High-value conditional caching (ETag, must-revalidate)
These endpoints should:
- return ETag
- return `Cache-Control: private, must-revalidate`
- support `304 Not Modified`

Recommended endpoints:
- `GET /home`
- `GET /home?league_id=...`
- `GET /me`
- `GET /me/teams`
- `GET /notifications`
- `GET /leagues/{league_id}/team`
- `GET /leagues/{league_id}/matches?gw={gw}`
- `GET /leagues/{league_id}/matches/{match_id}`
- `GET /leagues/{league_id}/table`
- `GET /leagues/{league_id}/stats/players?week_gw={gw}`
- `GET /leagues/{league_id}/fantasy`
- `GET /leagues/{league_id}/players/{player_id}`
- `GET /leagues/{league_id}/market/players`
- `GET /leagues/{league_id}/rules`
- `GET /leagues/{league_id}/private-leagues`
- `GET /leagues/{league_id}/private-leagues/{privateleague_id}`
  - (optional) `GET /leagues/{league_id}/private-leagues/invites`

### Category B – Cacheable with TTL (mostly static / public content)
These endpoints may use:
- `Cache-Control: public, max-age=3600` (or higher)

Recommended endpoints:
- (Optional) truly static content endpoints (if any) — use TTL when correctness is unaffected
- `GET /news` (optional TTL like 300–900s if it’s not critical)

If you prefer consistency, you can still use ETag + must-revalidate here.

### Category C – Never cache (no-store)
Endpoints that should return:
- `Cache-Control: no-store`

Recommended endpoints:
- all `POST`, `PATCH`, `DELETE`
- auth endpoints:
  - `POST /auth/login`
  - `POST /auth/token/refresh`
  - `POST /auth/logout`
  - OTP endpoints

---

## 5. ETag Compilation Rules (v1)


### 5.0 ETag scope definitions

ETags must reflect the **smallest correct scope** of the payload:

- **User scope**  
  Payload is specific to the authenticated user (e.g., league list, unread notification count).  
  Example: `GET /home` (no league selected)

- **User + League scope**  
  Payload depends on both the authenticated user and a specific league.

- **User + League + Current GW scope**  
  Payload depends on user, league, **and** the server’s current gameweek for that league (or the requested GW when applicable).  
  Example: `GET /leagues/{league_id}/team`, `GET /home?league_id=...`

- **League + Current GW scope**  
  Payload is the same for all users in the league for the same current GW.  
  Example: `GET /leagues/{league_id}/fantasy`

Practical guidance:
- If a payload includes **“your team / your competitor / your notifications”**, it is **User-scoped** at minimum.
- If a payload can change when the server advances or recalculates a GW, it must incorporate the **Current GW** marker.
- Membership changes (joining/leaving a league or private league) must also bump the relevant last-update marker so ETags change.

ETags should be weak ETags and “good enough”.
They do not require server-side storage.

Recommended pattern:
- ETag is built from endpoint type + context + a last-updated marker.

Example:
- `W/"team-{league_id}-{current_gw}-{last_update_unix}"`

### 5.1 Suggested last-update markers per endpoint

#### /home
- If no league selected:
  - max(updated_at) of user notifications + global news + league status
- If league selected:
  - include above plus:
    - max(updated_at) of teamranking/teamresult for user
    - max(updated_at) of team-of-the-week source (playerresult or teamoftheweek table)

#### /me
- max(updated_at) of the user profile row (alias/lang/email verification fields as applicable)
- if you store derived flags (e.g., `is_verified`), ensure they are included in the same marker or bump the profile updated_at

#### /me/teams
- max(updated_at) of competitor rows belonging to the user’s profile (across leagues)
- include league membership/eligibility changes if they affect the returned list

#### /leagues/{league_id}/team
Use a last-update marker derived from:
- roster row for current gw
- transfers rows for current gw
- competitor changes (credits/teamname/favorite)
- playertrade price updates for current gw (if prices change)

#### /leagues/{league_id}/matches?gw=X
- max(updated_at) of matches rows for that GW
- max(updated_at) of playerresult rows for that GW (if results embedded)

#### /leagues/{league_id}/matches/{match_id}
- max(updated_at) of the match row
- max(updated_at) of playerresult rows for that match

#### /leagues/{league_id}/table
- max(updated_at) of leaguetable rows for the current GW (or selected GW if you ever add `gw`)

#### /leagues/{league_id}/stats/players (optional week_gw)
- Prefer a derived/stored stats source (e.g., a materialized `playerstats` table) and use its max(updated_at) as the ETag marker.
- If `week_gw` is used to populate weekly columns, the ETag must also reflect the underlying playerresult rows for that `week_gw`.
- Include playertrade updates if “form” / price-linked metrics are embedded.

#### /leagues/{league_id}/fantasy
- max(updated_at) of teamranking/teamresult rows for league + GW
- include private league membership updates if private-league summaries are included

#### /leagues/{league_id}/players/{player_id}
- max(updated_at) of player + playertrade(current GW) + playerresult(window)
- include an ownership/roster marker if the modal exposes actions dependent on it

#### /leagues/{league_id}/market/players
- max(updated_at) of playertrade/current GW (price/availability inputs)
- optionally include playerresult updates if sorting/form depends on it
- if the response is contextual (`outgoing_player_ids[]`), include user roster/credits markers or use a user-scoped ETag

#### /leagues/{league_id}/rules
- rules/config change marker for the league (e.g., `league_rules.updated_at` or a monotonic `rules_version`)
- include season lock state if it affects the returned payload (`season.is_locked`)

#### /leagues/{league_id}/private-leagues
- user+league scoped marker: max(updated_at) of private league membership/invite rows for that user in the given league

#### /leagues/{league_id}/private-leagues/{privateleague_id}
- user+league+GW scoped marker: max(updated_at) of private league header + membership + standings sources for current GW

#### /notifications
- max(updated_at) of notifications for the user (or a monotonic user-scoped `notifications_version`)

---

## 6. Client Refresh / Invalidation Rules (v1)

After successful write actions, the client should refresh the following payloads:

### 6.1 After captain change or substitution

**Endpoints affected:**
- `POST /leagues/{league_id}/team/captain`
- `POST /leagues/{league_id}/team/substitute` (if implemented)

**Client rule:** revalidate `GET /leagues/{league_id}/team` immediately after success.

- refresh `GET /leagues/{league_id}/team`

### 6.2 After transfer confirm
On success, the client should revalidate:
- `GET /leagues/{league_id}/team`
- `GET /home?league_id=...` (your-team snapshot / rank may change)
- `GET /leagues/{league_id}/fantasy` (rankings may change)

If relevant UI is currently open, also revalidate:
- `GET /leagues/{league_id}/market/players` (ownership/availability may change)
- `GET /leagues/{league_id}/players/{player_id}` (modal context/actions)

If you generate a `transfer_confirmed` notification in Phase C, also revalidate:
- `GET /notifications` (and Home badge/preview if shown)


### 6.2.1 After transfer quote

**Endpoint:**
- `POST /leagues/{league_id}/transfers/quote`

**Client rule:** no cache invalidation is required on success because the operation is read/validate-only.
If the quote response indicates state drift (e.g., GW just closed), the client should revalidate:
- `GET /leagues/{league_id}/team`
- (optionally) `GET /home?league_id=...`


### 6.3 After team creation
- refresh `GET /home?league_id=...`
- refresh `GET /leagues/{league_id}/team`
- refresh `GET /leagues/{league_id}/fantasy`

### 6.4 After profile changes
- refresh `GET /me`
- refresh `GET /home` (if alias/team name is displayed there)

---


### 6.5 After notification read updates (Phase C)

**Endpoints affected:**
- `POST /notifications/{notification_id}/read`
- `POST /notifications/read-all` (if implemented)

**Client rule:**
- Revalidate `GET /notifications` after success.
- If Home shows `unread_count` / notifications preview, also revalidate:
  - `GET /home` (no league selected) and/or `GET /home?league_id=...` (if preview is league-contextual)

**Server note:**
- Marking a notification read must bump the **user-scoped notifications version** so ETag changes for `/notifications`.


### 6.6 After private league actions (Phase C Bundle A)

**Write endpoints (Category C):**
- `POST /leagues/{league_id}/private-leagues` (create)
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave`
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove`
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite`
- (optional) `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept`
- (optional) `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline`
- (optional) rename/delete endpoints

**Client rule (minimum):**
- Revalidate `GET /leagues/{league_id}/private-leagues` after any successful private league write.
- If the user is viewing a specific private league, also revalidate:
  - `GET /leagues/{league_id}/private-leagues/{privateleague_id}`

**Notifications coupling:**
- Invites and invite responses generate notifications. After these actions, also revalidate:
  - `GET /notifications`
- If Home shows an invite/unread preview, revalidate `GET /home` (heuristic).

**Server note:**
- Private league membership changes must bump the **User+League** version so list ETags change.
- Standings should bump the **User+League+GW** version when recalculated.

---

### 6.7 After team deletion (Phase C Bundle C)

**Endpoint affected:**
- `DELETE /leagues/{league_id}/team`

**Client rule:** on success, revalidate:
- `GET /me/teams`
- `GET /home` (and `GET /home?league_id=...` if the deleted league was selected)
- `GET /leagues/{league_id}/fantasy`

Also clear any cached league-scoped team payloads for that league:
- cached `GET /leagues/{league_id}/team` response + ETag

### 6.8 After profile deletion (Phase C Bundle C)

**Endpoint affected:**
- `DELETE /me`

**Client rule:** on success:
- clear local auth tokens
- clear *all* cached user-scoped data (`/home`, `/me`, `/me/teams`, `/notifications`, etc.)
- navigate to Login

### 6.9 After contact message sent (Phase C Bundle C)

**Endpoint affected:**
- `POST /contact`

**Client rule:** no cache invalidation required.

## 7. Staleness & Correctness Rules

- If an endpoint response affects game-critical decisions (transfers/captain/roster),
  the client must revalidate (ETag) rather than relying on cached data indefinitely.
- If the server rejects an action due to state change (e.g., GW closed),
  the client must:
  - discard pending UI state
  - reload the affected payload endpoint(s)

---

## 8. Notes for Future Improvements (not required for v1)

- Add server-side caching layers once correctness is stable.
- Add endpoint-specific TTLs where beneficial.
- Add `Last-Modified` headers if desired (ETag is usually enough).
- Add “stale-while-revalidate” patterns for news/rules content if needed.

---
