# Phase C Screens (v1)

This document defines Phase C screens using the same template style as Phase B.
Phase C focuses on completing the product experience (notifications, private leagues, match detail, player detail/market, profile, deep links).

---

## Screen template

Use this template for each screen.

### Screen: <Name>

**Primary goal:**  
**Entry points:**  
**Exit points / deep links:**  

#### UI sections
- 

#### Data model (local UI state)
- 

#### Payload(s) needed
- **GET** ...
- **POST** ...

#### Actions
- 

#### Rules / config fields it cares about
- 

#### Caching
- Category:
- Revalidation triggers:
- Offline behavior:

#### Error handling (high level)
- 

#### Analytics (optional)
- 

---

# 1) Notifications

### Screen: Notifications (Inbox)

**Primary goal:**  
Show the user’s notifications (system, league, private league, gameplay events), allow marking as read, and enable navigation via deep links.

**Entry points:**
- Home badge / notifications preview tap
- Push notification tap (if later enabled)
- Deep link from elsewhere (e.g., private league invite banner)

**Exit points / deep links:**
- Tap a notification → navigate to its `target` (see “Deep link targets”)
- Back → previous screen
- Optional: filter chip tap (All / Unread / Invites / System) stays in-screen

---

## 1.1 UI sections

- **Header**
  - Title: Notifications
  - Optional actions: “Read all”, filter chips
- **Filters (optional v1)**
  - All / Unread
  - (v2) Invites / Transfers / Results / System
- **Notification list**
  - Each item:
    - icon (by type)
    - title
    - short body (optional)
    - created_at (relative time)
    - unread indicator
- **Empty states**
  - No notifications
  - No unread notifications (when filter=Unread)
- **Pagination**
  - Infinite scroll or “Load more”
- **Pull-to-refresh**
  - Revalidate list

---

## 1.2 Data model (local UI state)

- `filter`: `"all"` | `"unread"` (extendable)
- `cursor`: paging cursor or page index
- `items`: list of notifications
- `optimistic_read_ids`: set for immediate UX feedback (optional)
- `last_seen_unread_count`: used to update Home badge quickly (optional)

---

## 1.3 Payloads needed

### A) GET /notifications

Returns a paginated list of notifications for the authenticated user.

**Request (suggested v1):**
- Method: GET
- Path: `/notifications`
- Query:
  - `filter` = `all` | `unread` (optional, default `all`)
  - `cursor` (optional)
  - `limit` (optional, default 20, max 50)

**Response (shape):**
- `unread_count` (global unread count for badge)
- `items[]`:
  - `notification_id`
  - `type`
  - `title`
  - `body` (optional)
  - `created_at`
  - `is_read`
  - `target` (deep link descriptor; see below)
- `next_cursor` (optional)

### B) POST /notifications/{notification_id}/read

Marks one notification as read.

**Request:**
- Method: POST
- Path: `/notifications/{id}/read`
- Body: empty or `{ "is_read": true }` (choose one pattern)

**Response:**
- `{ ok: true }`

### C) POST /notifications/read-all  (optional v1)

Marks all notifications as read.

**Response:**
- `{ ok: true, read_count: <int> }`

---

## 1.4 Deep link targets (notification.target)

To keep targets stable and typed, each notification should include a `target` object:

```json
{
  "target": {
    "kind": "league_home" | "team" | "rankings" | "private_league" | "private_league_invite" | "match" | "player" | "url",
    "league_id": 1,
    "params": {
      "privateleague_id": 200,
      "match_id": 999,
      "player_id": 456,
      "url": "https://..."
    }
  }
}
```

Rules:
- `league_id` is required for league-scoped targets.
- If the user is currently in a different league context:
  - app switches league context first (via Home/league switch logic),
  - then navigates to the destination.
- If the target is no longer valid (deleted resource, left league), show a toast and keep the user in Notifications.

---

## 1.5 Actions

- **Open notification**
  - If unread: mark read (optimistic) → navigate to target
- **Mark as read (swipe or overflow menu, optional v1)**
  - POST read
- **Read all**
  - POST read-all, update list state
- **Refresh**
  - Pull-to-refresh → revalidate GET
- **Load more**
  - Fetch next cursor

---

## 1.6 Rules / config fields it cares about

- Notification visibility rules:
  - User sees only own notifications
  - League-scoped notifications require membership/visibility
- Notification type definitions (Phase C rules):
  - `invite_received`, `invite_accepted`, `gw_closed`, `transfer_confirmed`, `result_published`, `system_message`, etc.
- Badge rules:
  - Home preview `unread_count` must match Notifications `unread_count`
  - Marking read must decrement count consistently (server is source of truth)

---

## 1.7 Caching

- **Category:** A (user-scoped, ETag + must-revalidate)
- **ETag scope:** User
- **Revalidation triggers:**
  - app resume / foreground
  - after any “mark read” write succeeds
  - after actions elsewhere that create notifications (private league invite, transfers confirm, GW events) — typically just rely on ETag change + periodic revalidate
- **Offline behavior:**
  - Show last cached list (if present) with “Offline” banner
  - Writes queued? (v2) In v1, if offline, block mark-read and show toast

---

## 1.8 Error handling (high level)

- 401 AUTH_REQUIRED → navigate to Login
- 429 RATE_LIMITED → toast, allow retry
- 5xx → toast + keep cached list if available
- Target navigation failures (404/403 on destination):
  - toast “This content is no longer available”
  - stay on Notifications

---

## 1.9 Notes / open decisions (keep short)

- Do we support bulk mark-read by IDs (preferred for swipe multi-select) or read-all only?
- Do we want notification grouping by date (Today / Yesterday / Older) in v1?
---

# 2) Private Leagues

This bundle introduces private league social gameplay: creating leagues, inviting members, accepting/declining invites, and viewing private league standings.

## Screen group overview

Screens in this bundle:
- Private Leagues (List)
- Private League (Detail / Standings)
- Invite Members (Search + Invite)
- Manage Invites (optional in-app inbox; can also be handled via Notifications)

All private-league interactions should generate notifications where relevant:
- invite received
- invite accepted / declined
- member removed (optional future)

---

## 2.1 Screen: Private Leagues (List)

**Primary goal:**  
Show the user’s private leagues in the selected league context, plus pending invites and a clear “Create league” CTA.

**Entry points:**
- Rankings screen → “Private leagues”
- Home → shortcut card (optional)
- Deep link target: `private_leagues_list` (optional)

**Exit points / deep links:**
- Tap league → Private League Detail
- Tap invite → accept/decline flow (or open Notifications target)
- Tap create → Create Private League (modal or dedicated screen)
- Back → previous screen

### UI sections
- Header: “Private Leagues”
- Create CTA (primary)
- Pending invites section (if any)
- Your private leagues list
  - league name
  - member count
  - your role (admin/member)
  - your status (confirmed/pending)
- Empty state:
  - “No private leagues yet” + Create CTA

### Data model (local UI state)
- `active_league_id`
- `invites[]`
- `leagues[]`
- `is_creating`: bool
- `create_form`: { leaguename }

### Payload(s) needed
- **GET** `/leagues/{league_id}/private-leagues` (list + invites summary)
- **POST** `/leagues/{league_id}/private-leagues` (create)
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/leave` (leave)
- (optional) **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/delete` (admin only)

### Actions
- Create private league
- Open league detail
- Accept/decline invite (either inline, or by opening Notifications)
- Leave league
- (Admin) Delete league

### Rules / config fields it cares about
- User must be authenticated
- User must have access to the league
- Create constraints:
  - max private leagues per user (optional)
  - name length constraints
- Invite status lifecycle: `pending` → `accepted|declined|expired`
- Leaving rules:
  - admin cannot leave if other members exist (optional)
  - admin leaving transfers admin (optional)

### Caching
- Category: A (user + league scope)
- Revalidation triggers:
  - after create/leave/delete
  - after accept/decline invite
  - on app foreground
- Offline behavior:
  - show cached list
  - block create/leave actions if offline

### Error handling (high level)
- 401 → Login
- 403/404 → toast + navigate back
- validation errors (name) → inline
- conflict (already member, invite expired) → toast + revalidate

---

## 2.2 Screen: Private League (Detail / Standings)

**Primary goal:**  
Show private league standings (members ranked) and allow admin/member actions (invite, remove, rename).

**Entry points:**
- Private Leagues List → tap league
- Notifications → `private_league_invite` or `private_league` target
- Deep link: `private_league` with ids

**Exit points / deep links:**
- Back → list
- Tap member team → (optional future) competitor detail
- Tap invite → Invite Members screen

### UI sections
- Header:
  - league name
  - member count
  - role badge (admin/member)
- Standings table:
  - rank
  - teamname
  - alias
  - weekly points (current GW)
  - total points
  - (optional) last active
- Pending members (optional):
  - invited but not confirmed
- Admin actions (if admin):
  - Invite members
  - Remove member
  - Rename league (optional v1)
  - Delete league (optional v1)
- Member actions:
  - Leave league

### Data model (local UI state)
- `privateleague_id`
- `active_league_id`
- `standings[]`
- `pending_members[]`
- `role`: admin|member
- `ui`: { is_loading, is_admin_menu_open }

### Payload(s) needed
- **GET** `/leagues/{league_id}/private-leagues/{privateleague_id}` (header + standings + membership status)
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/leave`
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/rename` (optional v1)
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove` (admin)
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/delete` (optional v1)

### Actions
- Refresh standings (pull-to-refresh)
- Invite members (navigate)
- Leave league
- (Admin) Remove member
- (Admin) Rename / delete (optional v1)

### Rules / config fields it cares about
- Membership required to view (or invite link allows preview? choose one; recommend membership required)
- Admin permissions:
  - only admin can remove members / rename / delete
- Remove member:
  - cannot remove self via remove endpoint; use leave flow
- Standings are GW-aware:
  - show `current_gw` in header and points for that GW
- If user has no competitor in the base league, private league standings still show but “you” marker may be absent

### Caching
- Category: A (user + league + current GW scope)
- Revalidation triggers:
  - after accept/decline invite
  - after member removed/leave
  - on GW changes / ranking recalculation
- Offline behavior:
  - show cached standings
  - block admin writes

### Error handling (high level)
- 401 → Login
- 403 (not member) → toast + navigate to list
- 404 → toast + navigate to list
- 409 (invite expired, already left) → toast + revalidate list

---

## 2.3 Screen: Invite Members (Search + Invite)

**Primary goal:**  
Allow an admin to invite users/teams into a private league via search/autocomplete.

**Entry points:**
- Private League Detail → “Invite members”

**Exit points / deep links:**
- Back → Private League Detail
- Success → stay, show invited state

### UI sections
- Search bar (autocomplete)
- Results list:
  - alias + teamname
  - invite button / invited state
- Invited list (optional): recently invited this session
- Empty state: “No results”

### Data model (local UI state)
- `query`
- `results[]`
- `invited_competitor_ids` set
- `is_searching` bool

### Payload(s) needed
- **GET** `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q=...` (autocomplete)
- **POST** `/leagues/{league_id}/private-leagues/{privateleague_id}/invite` (send invite)
  - body: `{ "competitor_id": 9001 }`

### Actions
- Search
- Invite selected result
- Retry on failure

### Rules / config fields it cares about
- Only admin can invite
- Search results must exclude:
  - users already members
  - users already invited (pending)
- Invite constraints:
  - max members (optional)
  - cannot invite self (optional)
- Invites generate Notifications:
  - invite_received for invitee
  - (optionally) invite_sent confirmation for inviter

### Caching
- Search endpoint: Category B (short TTL / no ETag), or Category A with very short cache (recommend B)
- Invite POST: Category C
- Revalidation triggers:
  - after invite: revalidate private league detail (pending members) and list
- Offline behavior:
  - block invite and show toast

### Error handling (high level)
- 401 → Login
- 403 (not admin) → toast + navigate back
- 409 (already member/invited) → toast + refresh detail
- 422 (invalid competitor_id) → toast

---

## 2.4 Screen: Manage Invites (optional in-app inbox)

**Primary goal:**  
Allow the user to view and respond to private league invites without relying on Notifications list.

Note: This can be deferred in v1 if Notifications already provides invite targets.

### Payload(s) needed
- **GET** `/leagues/{league_id}/private-leagues/invites` (list pending)
- **POST** `/leagues/{league_id}/private-leagues/invites/{invite_id}/accept`
- **POST** `/leagues/{league_id}/private-leagues/invites/{invite_id}/decline`

### Caching
- Category A (user + league)
- Revalidate after accept/decline
---

# 3) Matches & Results

## Screen group overview

Screens in this bundle:
- Matches (Tab) — subviews: Matches / Table / Stats
- Match Detail (optional but recommended for deep links)

This bundle is read-only (no writes). It must support deep links from Notifications:
- `target.kind = "match"` → Match Detail
- `target.kind = "player"` → Player Detail (modal; defined in next bundle section)

---

## 3.1 Screen: Matches (Tab)

**Primary goal:**  
Let users browse fixtures/results by gameweek, inspect match details, view the real-league table, and view player stats.

**Entry points:**
- Bottom nav: Matches tab
- Deep link: `match` (lands in Match Detail and can navigate back into Matches)

**Exit points / deep links:**
- Tap match card → Match Detail
- Tap player row (from match details or stats) → Player Detail (modal)
- Back → previous tab

### UI sections

- **Header**
  - League name / logo (optional)
  - Gameweek selector (prev/next + current GW label)
- **Subview tabs**
  - Matches | Table | Stats
- **Matches subview**
  - Match cards:
    - home vs away, kickoff time/date, status (scheduled/in_progress/finished/postponed/cancelled)
    - score / matchpoints (if finished)
  - Empty states:
    - no matches for GW
    - results not available yet (if admin import pending)
- **Table subview**
  - Standings table with columns per R8.5–R8.7 ordering:
    - TeamPoints, MatchPoints, SetPoints
- **Stats subview**
  - Player leaderboard with **season-to-date totals and averages** (matches, substitutions, pins, matchpoints, fantasy points)
  - Optional “weekly” column (default: latest finished GW) to show recent form (e.g., `week_fantasy_points`)
  - Filters (optional v1): team filter, search

### Data model (local UI state)

- `active_league_id`
- `selected_gw` (defaults to server current GW)
- `subview`: `"matches" | "table" | "stats"`
- `matches_payload` (cached by gw)
- `table_payload`
- `stats_payload`
- `stats_week_gw` (optional; used only if UI allows choosing which GW populates weekly columns)
- `ui`: `{ is_loading, is_refreshing, last_error }`

### Payload(s) needed

- **GET** `/leagues/{league_id}/matches?gw={gw}`
  - Recommended: includes enough match data for a compact list + minimal per-match summary
- **GET** `/leagues/{league_id}/table`
- **GET** `/leagues/{league_id}/stats/players` (optional `week_gw` query param; default is latest finished GW)

> If `Match Detail` is implemented as a dedicated endpoint (recommended), Matches list can keep match cards light and fetch details on-demand (see 3.2).

### Actions

- Change GW (prev/next / picker) → fetch or revalidate matches payload for that GW
- Switch subview → fetch/revalidate relevant payload
- Pull-to-refresh → revalidate active subview payload
- Open match → navigate to Match Detail
- Open player → show Player Detail modal

### Rules / config fields it cares about

- R3 GW lifecycle:
  - GW navigation bounds: `gw_nav.prev_gw/next_gw/min_gw/max_gw`
  - Display `gameweek.deadline`, `gameweek.is_open` for context (read-only)
- R7 scoring display rules:
  - show `fantasy_points`, `pins`, `setpoints`, `matchpoints` where available
  - postponed matches should be marked clearly (rankings may be provisional) (R7.5)
- R8.5–R8.7 real league table ordering (display alignment)

### Caching

- **Category:** A for all three GETs (ETag + must-revalidate)
- **Revalidation triggers:**
  - user pull-to-refresh
  - app foreground (lightweight: only the active subview)
  - after admin imports results / recalculation (ETag change handles this)
- **Offline behavior:**
  - show last cached payload (if available) + “Offline” banner
  - disable GW switching only if no cached payload for target GW

### Error handling (high level)

- 401 AUTH_REQUIRED → navigate to Login
- 403 LEAGUE_FORBIDDEN / 404 LEAGUE_NOT_FOUND → toast + navigate back to league picker / Home
- 404 GW_NOT_FOUND (if user navigates outside bounds) → toast + snap back to valid GW
- 409 TABLE_NOT_AVAILABLE / RANKING_NOT_AVAILABLE (optional) → toast + allow retry
- 5xx → toast; keep cached content if present

---

## 3.2 Screen: Match Detail (recommended)

**Primary goal:**  
Show a single match with full details: teams, status, and per-player rows (pins/setpoints/matchpoints/fantasy_points).

**Entry points:**
- Matches → tap a match
- Notifications deep link: `target.kind="match"` with `{ league_id, match_id }`

**Exit points / deep links:**
- Back → Matches (or previous screen)
- Tap player → Player Detail (modal)

### UI sections

- Match header: teams, kickoff time/date, status
- Score summary (if finished)
- Player rows (home + away), grouped by row/position:
  - pins, setpoints, matchpoints, fantasy_points
  - starter/substitute indicators (if known)
- Notes/badges:
  - postponed / provisional results (if applicable)

### Data model (local UI state)

- `active_league_id`
- `match_id`
- `match` (full detail object)
- `ui`: `{ is_loading, last_error }`

### Payload(s) needed

Preferred:
- **GET** `/leagues/{league_id}/matches/{match_id}`

Fallback (if you do not add the dedicated endpoint):
- **GET** `/leagues/{league_id}/matches?gw={gw}` and locate `match_id` client-side  
  (Downside: deep link must also carry `gw`, and payload may be heavy.)

### Actions

- Pull-to-refresh → revalidate match detail payload
- Open player modal from any player row

### Rules / config fields it cares about

- R7 scoring inputs for display
- R7.5 postponed match behavior (clearly label provisional vs finalized)

### Caching

- **Category:** A (ETag + must-revalidate)
- **Revalidation triggers:**
  - pull-to-refresh
  - app foreground if screen is open
- **Offline behavior:**
  - show cached match detail if present; otherwise show offline empty state

### Error handling (high level)

- 401 → Login
- 403 LEAGUE_FORBIDDEN → toast + navigate back
- 404 MATCH_NOT_FOUND (new code, if you introduce it) or generic 404 → toast + navigate back
- 5xx → toast; keep cached detail if available
---

# 4) Players & Market (Bundle B)

## Screen group overview

Screens in this bundle:
- Transfer Market (Players list)
- Player Detail (Modal)

These screens are tightly coupled with the Phase B transfer flow endpoints:
- `POST /leagues/{league_id}/transfers/quote`
- `POST /leagues/{league_id}/transfers/confirm`

---

## 4.1 Screen: Transfer Market (Players list)

**Primary goal:**  
Let users browse and select players for transfers: filter/sort, inspect prices/stats, and clearly show whether a player is buyable given roster/budget/team constraints.

**Entry points:**
- Team Management → “Transfer” / “Replace player”
- Player Detail modal → “Replace”
- Deep link (optional future): `target.kind="market"` (not in v1 targets)

**Exit points / deep links:**
- Tap a player → Player Detail (modal)
- Confirm selection → Transfer confirm flow (modal/sheet)
- Back → Team Management

### UI sections

- Header: “Market”
- Search (optional v1): name filter
- Filters (optional v1): team, price range
- Sort (optional v1): price / avg points / form
- Player list rows/cards:
  - name, team, price
  - compact stats (avg/form) + next fixture (optional)
  - availability state:
    - enabled action (Select / Replace)
    - disabled with reason label (max-from-team, budget, already-owned, GW closed)

### Data model (local UI state)

- `active_league_id`
- `filters`: `{ q, team_id, sort, limit, offset }`
- `transfer_context`:
  - `outgoing_player_ids[]` (1–2)
  - computed `available_credits`
- `players[]`
- `pagination`: `{ limit, offset, total }`
- `ui`: `{ is_loading, is_refreshing, last_error }`

### Payload(s) needed

- **GET** `/leagues/{league_id}/market/players`
  - Recommended query params (v1):
    - `q` (optional)
    - `team_id` (optional)
    - `sort` (optional)
    - `limit`, `offset`
    - `outgoing_player_ids[]` (optional; if provided, server can compute availability against the user’s roster + credits)
- **POST** `/leagues/{league_id}/transfers/quote` (to validate selection before confirm)
- **POST** `/leagues/{league_id}/transfers/confirm` (persist)

### Actions

- Update filters/sort → reload market list
- Select incoming player(s) → run quote (or defer until confirm modal)
- Confirm transfer → call confirm endpoint
- Open Player Detail modal

### Rules / config fields it cares about

- R3 GW state:
  - disable transfer actions if `gameweek.is_open=false` (server enforcement is authoritative)
- R4 roster constraints:
  - max from same team (R4.6)
  - roster size invariants (R4.1–R4.3)
- R5 transfer constraints:
  - transfers per GW (or free-transfer GW) (R5.1, R5.13)
  - budget after selling outgoing players (R5.6–R5.7)

### Caching

- **Category:** A (ETag + must-revalidate) for the base market list.  
  If the response is **contextual** (depends on `outgoing_player_ids[]` and user credits), treat those params as part of the cache key.
- **Revalidation triggers:**
  - after transfer confirm (market prices/ownership/availability may change)
  - after admin updates prices/results (ETag change)
- **Offline behavior:**
  - show cached list if available
  - block quote/confirm writes when offline

### Error handling (high level)

- 401 → Login
- 403/404 league errors → toast + navigate back
- 409 GW_CLOSED / GW_NOT_OPEN / MARKET_CLOSED (if introduced) → toast + force refresh Team payload
- 429 → toast
- 5xx → toast; keep cached list if present

---

## 4.2 Screen: Player Detail (Modal)

**Primary goal:**  
Show player info, price/stats, recent history and fixtures; provide contextual actions (captain / replace / buy).

**Entry points:**
- Team Management (tap player)
- Matches / Stats (tap player)
- Market (tap player)
- Notifications deep link: `target.kind="player"` with `{ league_id, player_id }`

**Exit points / deep links:**
- Close modal → returns to underlying screen
- “Replace / Buy” → opens transfer flow (Market or confirm modal)

### UI sections

- Player header: name, team, price
- Ownership chip (owned by you / not owned)
- Key stats:
  - avg points, form points, selection %
- Fixtures:
  - next matches list
  - recent results list (last N)
- Actions:
  - If owned:
    - Set captain (if eligible)
    - Replace (opens transfer flow)
  - If not owned:
    - Buy / Replace (opens transfer flow)
  - Disabled reasons list (when actions blocked)

### Data model (local UI state)

- `active_league_id`
- `player_id`
- `player_payload`
- `ui`: `{ is_loading, last_error }`

### Payload(s) needed

- **GET** `/leagues/{league_id}/players/{player_id}`
- If captain action is exposed:
  - **POST** `/leagues/{league_id}/team/captain`

### Actions

- Set captain (owned + starter + GW open)
- Start transfer flow (buy/replace) → navigates to Market with context
- Pull-to-refresh (optional)

### Rules / config fields it cares about

- R3 GW state (captain & transfers only while open)
- R6 captain eligibility:
  - must be in roster and starter positions (R6.1–R6.2)
- R5 transfers (buy/replace uses quote/confirm)
- If player is not owned: show “why blocked” hints (budget, max-from-team)

### Caching

- **Category:** A (ETag + must-revalidate)
- **ETag scope:** user + league + current GW + player (because `ownership` and `actions` are user-specific)
- **Revalidation triggers:**
  - after captain change (revalidate Team payload; this modal may close)
  - after transfer confirm (ownership may change)
- **Offline behavior:**
  - show cached details if present; disable actions

### Error handling (high level)

- 401 → Login
- 403 → toast + close modal (or navigate back)
- 404 → toast “Player not found” + close modal
- 409 GW_CLOSED on captain action → toast + revalidate Team
- 5xx → toast; keep cached detail if present

---

# 5) More / Account (Bundle C)

This bundle covers the “More” tab and account-level experiences:
- Profile (me + teams)
- Settings
- Rules
- Contact / Support
- Irreversible actions: team deletion + profile deletion (simple confirmation)

## 5.1 Screen: More (Account hub)

**Primary goal:**  
Provide a single place to access account-related screens (Profile, Settings, Rules, Contact) and session actions (Logout).

**Entry points:**
- Bottom nav: More
- Deep links to sub-screens (e.g., `target.kind="url"` or in-app navigation)

**Exit points / deep links:**
- Profile → Profile screen
- Settings → Settings screen
- Rules → Rules screen
- Contact → Contact screen
- Logout → Login

### UI sections

- Profile card (alias + email; optional avatar)
- Quick links list:
  - Profile
  - Settings
  - Rules
  - Contact
- Session actions:
  - Logout

### Data model (local UI state)

- `me` (cached)
- `ui`: `{ is_loading, last_error }`

### Payload(s) needed

- **GET** `/me` (for top card + navigation context)

### Actions

- Navigate to sub-screens
- Logout

### Rules / config fields it cares about

- Auth required
- Display-only: do not show league-specific state here

### Caching

- **Category:** A
- **Revalidation triggers:**
  - on app foreground
  - after Profile update
- **Offline behavior:**
  - show cached `me` card if present; allow navigation to Rules (if cached)

### Error handling (high level)

- 401 → Login
- 5xx → toast; keep cached card if present

---

## 5.2 Screen: Profile

**Primary goal:**  
Let the user view and edit basic account information and manage their teams (per league).

**Entry points:**
- More → Profile

**Exit points / deep links:**
- Back → More
- Tap team → (optional) switch league context and navigate to Team / Rankings
- Delete team → remains in Profile
- Delete profile → Logout/Login

### UI sections

- Account header:
  - alias (editable)
  - email (read-only)
  - language (optional quick display)
- “Your teams” list (grouped by league):
  - league name/logo
  - competitor/teamname
  - actions:
    - Open (switch league context → Team)
    - Delete team (requires confirmation)
- Danger zone:
  - Delete profile (requires confirmation)

### Data model (local UI state)

- `me`
- `teams[]` (per league)
- `edit_form`: `{ alias, lang }`
- `ui`: `{ is_loading, is_saving, last_error }`
- `confirm`: `{ action: "delete_team"|"delete_profile", context_ids }`

### Payload(s) needed

- **GET** `/me`
- **GET** `/me/teams`
- **PATCH** `/me` (or **POST** `/me` — choose one pattern)
  - body: `{ "alias": "...", "lang": "en" }` (partial update)
- **DELETE** `/leagues/{league_id}/team` (delete competitor/team in the given league)
- **DELETE** `/me` (delete the user profile and all associated data)

### Actions

- Edit profile fields (alias, lang)
- Open team in a league (switch league context then navigate)
- Delete team (per league)
- Delete profile

### Rules / config fields it cares about

- Deletion rules:
  - Team deletion is irreversible and removes associated data (R12.1–R12.2).
  - Team deletion requires explicit confirmation (R12.3).
  - Profile deletion requires explicit confirmation (same UX pattern).
  - No additional cooldown / “security hardening” is required in v1; avoid accidental deletion via a second confirmation step.
- Auth rules:
  - user can only delete their own teams/profile

### Caching

- **GET /me**: Category A (user scope)
- **GET /me/teams**: Category A (user scope)
- **PATCH /me**, **DELETE** writes: Category C
- **Revalidation triggers:**
  - after profile update: revalidate `GET /me` (+ `GET /home` if alias is shown there)
  - after team deletion: revalidate `GET /me/teams`, `GET /home`, and `GET /leagues/{league_id}/fantasy` (if user was in ranking)
- **Offline behavior:**
  - show cached data; block writes (save/delete) with toast

### Error handling (high level)

- 401 → Login
- 422 validation errors (alias) → inline
- 403/404 on team delete (league access/team not found) → toast + revalidate teams list
- 429 → toast
- 5xx → toast; keep cached state

---

## 5.3 Screen: Settings

**Primary goal:**  
Let the user configure app preferences that affect UI behavior (not gameplay rules).

**Entry points:**
- More → Settings

**Exit points / deep links:**
- Back → More

### UI sections

- Language selector (if supported)
- Theme (light/dark/system) (optional)
- (Optional v1) Notification preferences link (in-app only; push is out of scope)
- About / app version

### Data model (local UI state)

- `me` (for language)
- `ui`: `{ is_loading, is_saving }`

### Payload(s) needed

- **GET** `/me`
- **PATCH** `/me` (if language is stored server-side)
- Optional (if you prefer client-only settings): no API writes for theme

### Actions

- Change language
- (Optional) Reset cache / diagnostics (developer-only; out of scope for production)

### Rules / config fields it cares about

- Language codes supported (from `/config.global` or app constants)
- If language is stored server-side, it must persist across devices

### Caching

- **Category:** A for `GET /me`
- **Revalidation triggers:** after saving
- **Offline behavior:** show cached value; allow client-only settings; block server save if offline

### Error handling (high level)

- 401 → Login
- 422 validation (unsupported lang) → inline
- 5xx → toast

---

## 5.4 Screen: Rules

**Primary goal:**  
Show the authoritative game rules/config in a readable format (and optionally a compact “key rules” summary).

**Entry points:**
- More → Rules
- Optional deep link / help link from elsewhere

**Exit points / deep links:**
- Back → More

### UI sections

- League selector (optional): choose which league’s rules to display
- Key rules cards:
  - roster size, starters/subs, max from same team
  - transfers per GW + free transfer GW
  - initial budget
  - season locked state
- Full rules section (optional): “Read more” / link to a hosted rules page

### Data model (local UI state)

- `active_league_id` (optional; if league rules are league-scoped)
- `rules_payload`
- `ui`: `{ is_loading, last_error }`

### Payload(s) needed

- **GET** `/leagues/{league_id}/rules`
- Optional: **GET** `/config` (global constants shown in UI, e.g. OTP timings)

### Actions

- Switch league (re-fetch rules)
- Pull-to-refresh

### Rules / config fields it cares about

- Rules payload is authoritative and display-only.
- If `season.is_locked=true`, UI should show “Season locked” banner and disable any links that imply edits.

### Caching

- **Category:** A (preferred for consistency) or B (if you want TTL); recommend A with ETag.
- **Revalidation triggers:** pull-to-refresh; app foreground
- **Offline behavior:** show cached rules if present; otherwise offline empty state

### Error handling (high level)

- 401 → Login
- 403/404 (league access) → toast + navigate back
- 5xx → toast; keep cached if present

---

## 5.5 Screen: Contact / Support

**Primary goal:**  
Allow the user to contact the admins (feedback, bug report, support) with basic rate limiting.

**Entry points:**
- More → Contact

**Exit points / deep links:**
- Back → More

### UI sections

- Form:
  - subject (optional)
  - message (required)
  - include context toggles (optional): device/app version, current league id
- Submit button
- Success state

### Data model (local UI state)

- `form`: `{ subject, message, include_context }`
- `ui`: `{ is_sending, last_error, sent_ok }`

### Payload(s) needed

- **POST** `/contact`
  - body: `{ "subject": "...", "message": "...", "context": { "app_version": "...", "league_id": 1 } }`

### Actions

- Submit contact message

### Rules / config fields it cares about

- Rate limiting (see API errors catalog): `CONTACT_RATE_LIMITED`
- Payload must not include secrets; only safe context

### Caching

- **Category:** C (no-store)
- **Offline behavior:** block submit, show toast

### Error handling (high level)

- 401 → Login (if contact requires auth; recommended)
- 422 validation (message empty/too long) → inline
- 429 CONTACT_RATE_LIMITED → toast “Please try again later”
- 5xx → toast

