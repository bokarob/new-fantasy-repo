# Core Rules Specification  
Fantasy 9pin – Web & Mobile

## Status
Draft v0.1  
This document is the authoritative definition of game rules for the Fantasy 9pin application.  
All implementations (web, mobile, admin tools, APIs) must conform to these rules.


## Rule Precedence

If multiple rules apply to the same situation, the following precedence is used:
1. Gameweek state rules (open/closed)
2. Roster validity rules
3. Transfer rules
4. Scoring rules

---

## 1. Purpose & Scope

This document defines:
- game rules
- constraints
- edge-case behavior
- league and gameweek logic

It explicitly does NOT define:
- UI layout or presentation
- animations or visual effects
- platform-specific UX details

---

## 2. Core Concepts & Definitions

### 2.1 Definitions

- **League**  
  A competition context with its own calendar, matches, teams, and rankings.

- **Gameweek (GW)**  
  A fixed time period with a defined deadline and a set of matches.

- **Profile**  
  A registered user account, identified by email.

- **Competitor**  
  A fantasy team owned by a profile within a specific league.

- **Roster**  
  The list of players assigned to a competitor for a given gameweek.

- **Transfer**  
  The replacement of one or more players in a roster.

- **Starter**  
  A player occupying positions 1–6 in the roster.

- **Substitute**  
  A player occupying positions 7–8 in the roster.

---

## 3. League & Gameweek Rules

### 3.1 League Independence

R3.1 Each league operates independently.  
R3.2 Actions, rosters, matches, and rankings in one league do not affect other leagues.

### 3.2 Gameweek Lifecycle

R3.3 Each league has exactly one *current gameweek* at any given time.  
R3.4 A gameweek has:
- a start date
- a deadline date (midnight, server time)
- a gamedate
- an open state

R3.5 A gameweek is considered **open** if:
- its open_state = 1
- and the current server time is before or equal to its deadline.

R3.6 After the deadline, the gameweek is **closed** and no roster modifications are allowed.

R3.7
- Timezone is server timezone for all leagues

---

## 4. Team & Roster Rules

### 4.1 Roster Composition

R4.1 Each competitor must have exactly **8 players** in their roster.  
R4.2 Exactly **6 players** are starters (positions 1–6).  
R4.3 Exactly **2 players** are substitutes (positions 7–8).

### 4.2 Roster Availability

R4.4 A roster must exist for the current gameweek.  
R4.5 If no roster exists, it must be automatically created before any roster-related action.

### 4.3 Team Constraints

R4.6 A competitor may have **at most 2 players** from the same real-life team.

R4.7
- The order of the substitutes is defined by user. It has significance in the scoring.
- Auto-substitution exist if a starter does not play. First position 7 counts, then if needed, position 8.

---

## 5. Transfer Rules

### 5.1 Transfer Allowance

R5.1 A competitor may make **up to 2 transfers per gameweek**.
R5.2 Transfer allowance resets at the start of each new gameweek.

### 5.2 Transfer Structure

R5.3 A transfer consists of:
- selecting **1 or 2 outgoing players**
- selecting an equal number of incoming players

R5.4 Transfers must always preserve a valid roster of 8 players.

### 5.3 Budget Rules

R5.5 Each player has a gameweek-specific price.  
R5.6 At the initial team creation a competitor has the budget of 80 to create a team.
R5.7 Later in the season the total team value is not checked. At substitutions the total price of incoming players must not exceed the available credits after selling outgoing players.

### 5.4 Timing Rules

R5.8 Transfers may only be confirmed while the gameweek is open.  
R5.9 If the gameweek deadline passes during a pending transfer, the transfer must be rejected.

### 5.5 Atomicity

R5.10 Transfers are atomic:
- either all changes are applied
- or none are applied

### 5.6 Pending Transfers

R5.11 Pending transfers are not persisted server-side.  
R5.12 Pending transfers are discarded when:
- the user cancels them
- the gameweek closes
- the user switches leagues
- Partial confirmation is not allowed

### 5.7 Free transfer gameweek

R5.13 Each league has a gameweek when unlimited transfers can be done. The gameweek number is defined in the database for each league.
R5.13a In the free transfer gameweek, the transfer allowance limit (R5.1) is ignored and transfers_used is not capped.
R5.13b In all other gameweeks, R5.1 applies normally.

---

## 6. Captain Rules

R6.1 Each competitor must have exactly **one captain** per gameweek.  
R6.2 Only starter players (positions 1–6) may be selected as captain.  
R6.3 Captain changes are allowed only while the gameweek is open.

R6.4:
- The captain's points are counted double.
- If the captain doesn't play, no other player's points are doubled instead.
R6.5 If the captain does not play and is replaced by an auto-substitute, the substitute does NOT inherit captain status.

---

## 7. Scoring & Results

### 7.1 Player Scoring

R7.1 Player fantasy points are derived from match results.  
R7.2 Each player result belongs to exactly one gameweek.

R7.3 Fantasy points are gained based on pins hit, setpoints and matchpoints won:
- 0.1 point for each pin hit
- 5 points for each setpoint won
- 15 points for each matchpoint won
R7.4 In case of substitution, if the matchpoint is won, each players receive 7.5-7.5 points, regardless of who won more setpoints or hit more pins.

R7.5
- In case of postponed matches, the weekly results and rankings are first calculated without the player results of the postponed game
- Later, when the game is played, the results are recalculated, taking into consideration the results now

### 7.2 Team Scoring

R7.6 A competitor’s gameweek score is the sum of fantasy points of all starters.  
R7.7 Substitute players only contribute to score if one or more of the starters did not play. First the position 7 points are taken into consideration, then, if needed, position 8.
R7.8 Finally the captain's points are counted double, in case the captain played in the gameweek

R7.9 A player is considered to have played in a gameweek if they have a recorded playerresult entry for that gameweek.

---

## 8. Rankings & Standings

### 8.1 Fantasy Rankings

R8.1 Rankings are calculated per league and gameweek.  
R8.2 Rankings are ordered by total fantasy points. If there is a tie, both teams occupy the same position (to be displayed alphabetically based on team name, but both have the same ranking)


R8.3 Rankings are computed after gameweek results are finalized.
R8.4 If there is one or more postponed game in the gameweek, the ranking will be recalculated after the game is played

### 8.2 Real League Standings

R8.5 Real league standings are calculated from match results.  
R8.6 Standings are stored in a dedicated table and updated by admin processes.

R8.7 Real league ranking is based on:
1: TeamPoints earned
2: Matchpoints earned
3: Setpoints earned

---

## 9. Fan Leagues & Private Leagues

### 9.1 Fan Leagues

R9.1 A fan league includes all competitors who selected the same favorite team.  
R9.2 Favorite team selection is optional.
- Favorite team can be changed mid-season

### 9.2 Private Leagues

R9.3 A private league may only include competitors from the same league.  
R9.4 Private leagues have an administrator.  
R9.5 Private league rankings follow the same rules as overall rankings.
R9.6 Private league admin can add other teams to the private league. They are also the ones who can accept the application of teams to the private league.
R9.7 Teams cannot be added to a private league without the admin's approval
R9.8 Admins can delete teams from the private league. They can also delete the entire private league.
R9.9 There is no maximum number for a private league.
R9.10 There is no expiry for the invitation/application to a private league

---

## 10. Registration & Identity

R10.1 A profile must be verified before creating a competitor.  
R10.2 Verification is performed using a one-time password (OTP).  
R10.3 OTPs have a limited validity period.

R10.4 OTP expiry time: 10 minutes
R10.5 Retry limits: maximum 5 times
R10.6 Cooldown between OTP requests: Cooldown increases with each resend (rate escalation):
- 1st resend: 60s
- 2nd: 120s
- 3rd+: 300s


R10.7 A profile contains user-facing fields used across the app (e.g. `alias`, optional `lang`).  
R10.8 Email is the unique identifier for login and is read-only in v1.

R10.9 Alias rules (server enforced):
- alias must be non-empty after trimming
- recommended length: 3–30 characters
- allowed characters should support common European alphabets (Unicode letters) plus digits, space, `- _ .`
- profanity filtering is recommended (same philosophy as team names)

R10.10 Language (`lang`) rules (if stored server-side):
- value must be one of the supported language codes (e.g. ISO 639-1 like `en`, `hu`, `de`)
- unsupported values must be rejected with validation errors


---

## 11. Team Creation Rules

R11.1 A competitor must be created before participating in a league.  
R11.2 Initial team creation allows free selection as long as all roster rules are met.  
R11.3 A team name must be provided.

R11.4 Team name can be changed later. Teamname can be maximum 50 characters long. No check for uniqueness. Users have to avoid profanity.

---

## 12. Deletion & Irreversible Actions

R12.1 Deleting a competitor is irreversible.  
R12.2 Deleting a competitor removes all associated data.
R12.3 Deletion requires explicit confirmation from the user including a confirmation dialog.


R12.4 Team deletion is league-scoped: it deletes the user’s competitor for that league only.  
R12.5 Team deletion must be blocked when the league is in a locked state (R17.8).  
R12.6 After a successful team deletion, the user may create a new team in the same league again (R11.x), starting fresh.

R12.7 Deleting a profile is irreversible.  
R12.8 Deleting a profile removes all associated data, including:
- competitors/teams across leagues
- rosters, transfers, and derived fantasy results
- notifications
- private league memberships and pending invites (as invitee)
- private leagues administered by the user (v1 policy: delete the private league and remove all members)

R12.9 Profile deletion requires explicit confirmation from the user (same UX pattern as team deletion).  
R12.10 Deletion operations should be idempotent: repeating the same delete request should not cause an error (implementation may return `ok=true` even if already deleted).


---

## 13. Notifications & Communication

R13.1 Notifications may be generated for important events (deadlines, transfers, rank changes).
R13.2 Current notification categories:
- Approaching trade deadline
- Invitation received for private league
- Application received for the private league where user is admin
- User's application into a private league was accepted
- Points and rankings were updated for a league where the user has competitor


R13.3 The app may provide a Contact / Support message flow for authenticated users.  
R13.4 Contact messages must not include secrets (passwords, OTP codes, tokens). Only safe context may be attached (e.g. app version, optional league_id).  
R13.5 The server should rate limit contact submissions to prevent spam. When rate limited, return `429 CONTACT_RATE_LIMITED`.


---

## 14. Out of Scope

The following are explicitly out of scope for this document:
- UI layout decisions
- Visual styling
- Animation behavior
- Platform-specific UX differences

---

## 15. Joining the league mid-season

R15.1 A user can create a competitor and join a league mid-season
R15.2 Whichever gameweek the user joins the league, they will have budget of 80 to create their initial team.
R15.3 The prices of the players will reflect the actual gameweek's prices when the competitor joins the league
R15.4 The competitor can make unlimited amount of transfers after joining the league, before the first upcoming gameweek deadline.
R15.5 Competitors are joining the league with 0 initial fantasy points.
R15.6 After creating a roster and participating in a gameweek (even if earning 0 fantasy points), the competitor will be added to the fantasy league's ranking table.
R15.7 A competitor created mid-season is created for the league’s current gameweek only if the gameweek is open; otherwise it is created for the next open gameweek.

## 16. Rule Enforcement Responsibility

This section defines where and how the rules described in this document must be enforced.

The guiding principle is:
**All game-critical rules must be enforced server-side.  
Client-side enforcement exists only to improve user experience.**

---

### 16.1 Server-Side Enforcement (Authoritative)

The backend is the single source of truth and must enforce all rules that affect:
- fairness
- data integrity
- rankings
- scoring
- security

The server MUST validate and enforce, at minimum, the following:

#### League & Gameweek
- current gameweek determination (R3.x)
- open/closed gameweek status (R3.5–R3.6)
- deadline checks for any modifying action

#### Team & Roster
- roster size and structure (R4.1–R4.3)
- starter vs substitute positions
- maximum players per real-life team (R4.6)
- automatic roster creation for current gameweek (R4.4–R4.5)

#### Transfers
- transfer allowance per gameweek (R5.1)
- outgoing/incoming player count matching (R5.2)
- budget validation (R5.4–R5.6)
- gameweek open-state validation (R5.6–R5.7)
- atomic application of transfers (R5.8)
- rejection of expired or invalid pending transfers (R5.9–R5.10)

#### Captain Rules
- exactly one captain per gameweek (R6.1)
- captain must be a starter (R6.2)
- captain changes only while gameweek is open (R6.3)
- captain scoring behavior (R6.4–R6.5)

#### Scoring & Results
- fantasy point calculation (R7.x)
- auto-substitution logic (R4.7, R7.7)
- postponed match recalculation logic (R7.5)
- final gameweek score computation

#### Rankings & Standings
- fantasy rankings calculation (R8.1–R8.4)
- real league standings calculation and ordering (R8.5–R8.7)
- tie-handling logic

#### Identity & Security
- OTP verification rules (R10.x)
- retry limits and cooldown enforcement
- authorization checks for all modifying actions

Any request that violates these rules MUST be rejected by the server, regardless of client behavior.

---

### 16.2 Client-Side Enforcement (UX Only)

The client (web or mobile) MAY enforce rules proactively to:
- guide the user
- prevent invalid actions
- provide immediate feedback

Client-side enforcement is considered **advisory only** and must never be relied upon for correctness.

Examples of client-side enforcement:
- disabling transfer confirmation when budget is insufficient
- greying out players when max players per team is reached
- hiding captain selection for substitute players
- showing countdown timers to deadlines
- preventing UI actions when gameweek appears closed

If a client-side rule check fails, the client should:
- block the action in the UI
- explain the reason clearly to the user

However, the server must still validate the same rule independently.

---

### 16.3 Conflict Resolution

If client-side and server-side rule evaluations differ:
- the server decision always prevails
- the client must handle server rejections gracefully

Examples:
- gameweek closes while the user is mid-transfer
- budget changes due to concurrent updates
- roster becomes invalid due to rule changes

In such cases, the client should:
- discard pending local state
- display an informative error message
- reload authoritative data from the server

---

### 16.4 Error Handling Expectations

For any rejected action, the server should return:
- a clear error code
- a human-readable message
- (optionally) a reference to the violated rule number (e.g. R5.6)

This enables:
- consistent client handling
- easier debugging
- traceability back to this specification

---

### 16.5 Future Extensions

If new rules or league-specific rule variations are introduced:
- server-side enforcement must be updated first
- client-side behavior may be updated afterward for UX support
- this document must be updated accordingly


## 17. End of the season

R17.1 A season ends for a league when all the matches have been played and the results and rankings are recalculated
R17.2 The end of season is marked in the database with an extra gameweek, which has no match_id assigned in matches table.
R17.3 After the season ends, users can view their last valid roster, but cannot do any changes or transfers
R17.4 After the season ends, users can view the latest ranking of the fantasy leagues
R17.5 No new private league can be created after the end of season
R17.6 Favorite team cannot be changed after the end of the season
R17.7 No new competitor can be created in the league after the end of the season.
R17.8 There is a "locked" state for any actions (team creation, transfers, captain changes, team management, favorite team selection, private team actions) related to the given league: only display of results/teams/statistics is possible, no changes anymore.
R17.9 Teams are not copied to the next season in the league. Each season the competitors have to be created newly.
---

# Phase B Rules Additions & Clarifications (v1)

This section aligns **core game rules** with the finalized Phase B mobile API behavior.
These rules are authoritative for both backend enforcement and client UX logic.

---

## B1. Gameweek lifecycle

Each league progresses through discrete **gameweeks (GW)**.

### GW states
- **Upcoming**
  - GW exists but is not yet open
  - Transfers and roster edits are **not allowed**
- **Open**
  - `gw.is_open = true`
  - Transfers, captain selection, and substitutions are allowed
- **Closed**
  - Deadline has passed
  - All roster-related actions are locked
  - Rankings may not yet be finalized

### Rules
- The GW state is evaluated **server-side only**
- Clients must treat GW state as read-only information
- Any write attempt during a non-open GW must be rejected

---

## B2. Team existence rule

A user may or may not have a team (competitor) in a given league.

### Rule
- A team is considered to exist only after successful team creation
- Until then, roster-based actions are not allowed

### API alignment
- Preferred handling: return `409 NO_COMPETITOR`
- Alternative (allowed): return `200` with `competitor = null` and `roster = null`
- The chosen approach must be consistent across all endpoints

---

## B3. Transfer model (quote vs confirm)

Transfers are a **two-step operation**:

### 1) Transfer quote
- Purpose: validation only
- Does **not** modify server state
- May return rule violations as data

Typical violations:
- insufficient budget
- transfer limit reached
- max players from same real team
- player unavailable

Quote behavior:
- Rule violations should be returned in `violations[]`
- Quotes should not invalidate caches
- Quotes may fail with errors only for auth or unrecoverable state (e.g. GW closed)

---

### 2) Transfer confirm
- Purpose: persist changes
- Modifies roster, credits, and transfer counters
- Must revalidate all rules at execution time

Confirm behavior:
- Rule violations result in **errors**, not soft violations
- If roster state drifted since quote, confirm must fail
- Successful confirm updates:
  - roster
  - remaining credits
  - transfers used

---

## B4. Transfer limits & free gameweek logic

Each GW defines:
- `transfers_allowed`
- `transfers_used`

### Standard GW
- Transfers are limited by `transfers_allowed`
- Each confirmed transfer increments `transfers_used`

### Free GW
- Transfer limit is ignored
- `transfers_used` may still increment for display, but must not block confirms
- Free GW applies only to the GW explicitly marked as such by league config

---

## B5. Roster edit rules

### Captain
- Exactly one captain must be selected
- Captain must be part of the active roster
- Captain cannot be changed after GW closes

### Substitutions
- Substitutions are position-based
- Only valid roster positions may be swapped
- Substitutions are locked after GW close

---

## B6. Rankings availability

Rankings are derived data and may lag behind real match completion.

### Rules
- Rankings are considered **available** only after server-side calculation
- Before availability:
  - `/fantasy` may return `RANKING_NOT_AVAILABLE`
- Rankings may be recalculated if:
  - postponed matches are resolved
  - scoring corrections are applied

---

## B7. Caching & consistency guarantees

- All rule evaluation happens server-side
- Clients must assume cached data can be stale
- After any write:
  - client must revalidate relevant GET endpoints
- ETag changes signal authoritative state changes

---

## B8. Phase B freeze notice

The above rules define the **complete Phase B rule set**.

Changes after Phase B freeze:
- Allowed: bug fixes, clarifications
- Not allowed: new mechanics, new constraints, new GW states

Further features belong to Phase C or later.
---

# Phase C — Notifications Rules (v1)

This section defines authoritative rules for the Notifications system introduced in Phase C.
These rules apply to backend generation, API behavior, and client expectations.

---

## C1. Notification creation (generation rules)

Notifications are generated **server-side** as a result of defined events.

### Supported notification types (initial set)
- `invite_received` — user receives a private league invitation
- `invite_accepted` — invitee accepts a private league invite
- `invite_declined` — invitee declines a private league invite
- `application_received` — admin receives a private league application
- `application_accepted` — admin accepts application to the private league
- `application_declined` — admin declines application to the private league
- `deadline_near` — approaching trade deadline
- `gw_closed` — gameweek deadline passes
- `transfer_confirmed` — user successfully confirms a transfer
- `result_published` — match or GW results become available
- `system_message` — global or targeted system announcement

Rules:
- Notifications must never be created client-side.
- Each triggering event must define **exactly one** notification per affected user.
- Duplicate notifications for the same event must be avoided.

---

## C2. Ownership & visibility

- Notifications are **user-owned**.
- A user may only fetch or modify their own notifications.
- League-scoped notifications require that the user:
  - is (or was, at creation time) a member of the league.
- If a user later loses access to a league, existing notifications remain visible but their targets may become invalid.

---

## C3. Read / unread semantics

### Definitions
- **Unread**: `is_read = false`
- **Read**: `is_read = true`

Rules:
- Unread count is calculated **server-side** and is authoritative.
- Marking a notification as read is **idempotent**.
- Marking notifications as read does not delete them.
- `read-all` affects only notifications existing at the time of the call.

---

## C4. Ordering & pagination

- Default ordering: **newest first** (`created_at DESC`).
- Pagination must be stable:
  - Order by `(created_at, notification_id)` to prevent duplicates.
- A notification must not appear on multiple pages for the same query.

---

## C5. Target resolution & validity

Each notification may define a `target` for navigation.

Rules:
- Targets are resolved **at navigation time**, not at read time.
- If a target entity no longer exists or is inaccessible:
  - The API should return `403` or `404` on the destination request.
  - The client must show a non-blocking message and remain in Notifications.
- Clients must not attempt to infer or rebuild targets locally.

---

## C6. Cross-screen consistency

- Home screen unread badge / preview must reflect the same unread count as Notifications.
- After any notification read action:
  - Clients must revalidate Notifications.
  - Clients should revalidate Home if it displays unread indicators.
- Notifications do not directly invalidate league or team data unless the target navigation requires it.

---

## C7. Phase C scope & freeze (Notifications)

The following are **in scope** for Phase C Notifications:
- List notifications
- Mark single notification as read
- Mark all notifications as read
- Deep link navigation via typed targets

Out of scope for Phase C (future phases):
- Push notifications
- Notification grouping (by date/type)
- Bulk multi-select actions
- User notification preferences

Changes after Phase C freeze:
- Allowed: bug fixes, clarifications
- Not allowed: new notification mechanics or types without a new phase
---

# Phase C — Private Leagues Rules (Bundle A)

This section defines the authoritative rules for **Private Leagues** (Phase C Bundle A).
It aligns with `phase-c-screens.bundleA-updated.md`, `phase-c-api-contracts.bundleA-updated.md`, caching, and error handling.

---

## PL1. Entities, roles, and scope

- A **Private League** exists within a base **League** (`league_id`).
- Identifiers:
  - `privateleague_id` — private league identifier
  - `competitor_id` — user team in the base league
- Roles:
  - **admin**: creator/owner of the private league
  - **member**: confirmed participant

Scope rules:
- Private leagues are always **league-scoped**.
- A user can only participate in private leagues that belong to a base league they can access.

---

## PL2. Membership lifecycle (invites)

Membership is modeled as a lifecycle:

### Statuses (conceptual)
- `pending` — user invited, not yet confirmed (invite exists) OR user applied, not yet confirmed by admin (application exists)
- `member_confirmed` — admin / user accepted and user is now an active member
- `declined` — admin / user declined the invite
- `expired` — invite expired (optional)
- `left` — user was a confirmed member and left
- `removed` — user was removed by admin

Implementation note:
- If the database model only supports a boolean `confirmed` flag, you may need an additional structure (e.g., invite table or status column) to represent `declined/expired/removed/left` cleanly. The API behavior below remains authoritative regardless of storage.

### Invite rules
- Only the private league **admin** may send invites.
- Invites are addressed to a **competitor_id** within the same base league.
- An invite must not be sent to:
  - an existing confirmed member (`ALREADY_MEMBER`)
  - an already pending invitee (`ALREADY_INVITED`)
- Invites may optionally expire; if so, the server must enforce `INVITE_NOT_FOUND` or `INVITE_NOT_PENDING` for expired invites.

### Application rules
- Users can apply to be a member of a private league
- Applications are addressed to the **admin** of the given private league
- Application must not be sent to:
  - a league where the user's **competitor_id** is already member (`ALREADY_MEMBER`)
  - a league where the user already has a pending application (`ALREADY_APPLIED`)

### Accept / decline rules (optional in-app inbox)
- Accept/decline is only allowed when the invite/application is `pending`.
- Accept transitions:
  - `pending` → `member_confirmed`
  - Creates or updates the membership as confirmed
- Decline transitions:
  - `pending` → `declined` (or remove invite record)
- Repeating accept/decline on a non-pending invite returns `INVITE_NOT_PENDING`.

Idempotency guideline:
- The server may treat repeated accept/decline as idempotent **only if** the resulting state matches the requested action; otherwise return `INVITE_NOT_PENDING`.

---

## PL3. Access control

### Viewing
- `GET private league detail/standings` requires:
  - user has access to base league, and
  - user is a **confirmed member** of the private league

If the user is not a member:
- return `403 PRIVATE_LEAGUE_FORBIDDEN` (do not leak membership lists)

### Actions
- Only **admin** can:
  - invite
  - remove members
  - accept applications
  - rename
  - delete
- A member can:
  - leave
- A non-member can:
  - apply for membership
---

## PL4. Admin and member actions

### Leaving
- A confirmed member may leave at any time (leaving does not require GW open).
- Admin leave behavior (v1 decision):
  - If admin is the **only** confirmed member, leaving deletes the private league.
  - If other confirmed members exist, admin cannot leave:
    - return `409 ADMIN_CANNOT_LEAVE`
  - Admin transfer is out of scope for Bundle A.

### Removing members (admin)
- Admin may remove a confirmed member.
- Admin cannot remove themselves via remove endpoint:
  - return `409 CANNOT_REMOVE_SELF`
  - use the leave/delete flows instead

### Rename (optional v1)
- Admin-only.
- Must validate name length and allowed characters (`422 VALIDATION_ERROR`).

### Delete (optional v1)
- Admin-only.
- Deleting a private league:
  - removes all memberships and pending invites
  - makes the league inaccessible (`404 PRIVATE_LEAGUE_NOT_FOUND` afterwards)

---

## PL5. Standings and ranking rules

Private league standings are derived from the same scoring system as global league rankings, filtered to private league members.

### Data shown (v1)
- `total_points` (season total)
- `weekly_points` (current GW points)
- `rank` and (optional) `previous_rank` / `rank_change`

### Ordering (tie-breakers)
Sort descending by:
1) `total_points`
2) `weekly_points`
3) `competitor_id` (stable final tie-break)

### Availability and recalculation
- Standings are **GW-aware** and may be unavailable until computed:
  - if not computed, the API may return `409 RANKING_NOT_AVAILABLE`
- Standings may be recalculated after postponed matches or scoring corrections.

---

## PL6. Invite search eligibility

Invite search returns eligible candidates for the admin to invite.

Rules:
- Only admin may use invite search (`403 NOT_ADMIN` otherwise).
- Results must exclude:
  - confirmed members
  - pending invitees
- Recommended minimum query length: 2–3 characters (server may return `422 QUERY_TOO_SHORT`).
- Search results should include enough info to disambiguate:
  - `alias`, `teamname`, `competitor_id` (and `profile_id` optionally)

---

## PL7. Notification generation coupling

Private league events generate notifications per Phase C Notifications rules:

- On invite sent:
  - invitee receives `invite_received`
- On invite accepted:
  - admin receives `invite_accepted`
- On invite declined:
  - admin receives `invite_declined`
- On application sent:
  - admin receives `application_received`
- On application accepted:
  - user receives `application_accepted`
- On application declined:
  - user receives `application_declined`

Optional future notifications (out of scope for Bundle A):
- member removed
- private league deleted/renamed

---

## PL8. Consistency, caching, and refresh rules

Server is the source of truth.

After any private league write (create/leave/invite/accept/decline/remove/rename/delete), clients should revalidate:
- `GET /leagues/{league_id}/private-leagues`
- If relevant, `GET /leagues/{league_id}/private-leagues/{privateleague_id}`
- If invites are involved, also revalidate:
  - `GET /notifications`
  - Home badge/preview if displayed

ETag scope expectations:
- Private leagues list: **User + League**
- Private league detail/standings: **User + League + Current GW**

---

## PL9. Phase C Bundle A scope

In scope (Bundle A):
- list private leagues + pending invites summary
- create private league
- view private league detail + standings
- invite search + send invite
- leave league
- remove member (admin)
- optional: manage invites inbox (accept/decline)
- optional: rename/delete

Out of scope (future phases):
- admin transfer / co-admin roles
- public invite links
- chat/messages in private leagues
- advanced moderation/audit logs
---

## B1. Match lifecycle & status (Phase C Bundle B)

Bundle B surfaces real match data (matches list, match detail), real league table, and player statistics.  
These rules define how match states are represented and how they affect table/stats visibility and recalculation.

### Match status vocabulary (server source of truth)

R-B1.1 Every match has a `status` with one of the following values:

- `scheduled` — fixture exists, not started
- `in_progress` — match is currently being played (informational flag; the app does not provide live scoring)
- `finished` — final result recorded (may still be subject to correction; see R-B1.6)
- `postponed` — not played at the scheduled time; may be played later
- `cancelled` — will not be played (no result)

R-B1.2 `finished` indicates the match has a recorded result and is eligible for inclusion in:
- real league table calculations
- player stats aggregations

R-B1.3 `postponed` matches remain visible in matches lists and match detail, but do not contribute to table/stats until they become `finished`.

R-B1.4 `cancelled` matches:
- may be displayed for transparency
- must not contribute to table/stats
- must not produce player scoring effects unless explicitly re-opened and re-finalized by admin processes

### GW association

R-B1.5 Each match belongs to a fixture gameweek `gw` determined by the league schedule.  
If a match is played later than its fixture gameweek (postponed), it still keeps its original `gw` for:
- matches list filtering by `gw`
- table/stats attribution

### Corrections and recalculation

R-B1.6 Admin corrections may change a match from `finished` → `finished` with updated numbers.
In super rare circumstances, a previously recorded match may be invalidated and set to `cancelled`.
When this happens:
- the league table must be recalculated for affected teams
- player stats must be recalculated for affected players
- any derived fantasy scoring (if applicable) must be recalculated per scoring rules

R-B1.7 When a match becomes `finished` (or is corrected), the backend must bump relevant ETags so clients can revalidate:
- `GET /leagues/{league_id}/matches?gw={gw}`
- `GET /leagues/{league_id}/matches/{match_id}`
- `GET /leagues/{league_id}/table`
- `GET /leagues/{league_id}/stats/players` (including paging and optional `week_gw`)

---

## B2. Matches list behavior (GET /leagues/{league_id}/matches?gw={gw})

R-B2.1 The matches list is GW-scoped; it returns all fixtures for the requested `gw` that the user is permitted to view.

R-B2.2 Ordering:
- default order is by scheduled start time ascending (or a stable server-defined order if start time is unknown)
- if two matches have identical start times, use `match_id` as the final stable tie-break

R-B2.3 For each match card, the server should provide enough fields to render:
- home/away team names and identifiers
- current status
- scoreline if `finished` (and optionally partial scoreline if `in_progress`)
- a `can_open_detail` boolean is allowed but not required (client can always open detail and handle errors)

R-B2.4 If a `gw` is outside the league’s valid range, the API must return `404 GW_NOT_FOUND` rather than an empty list.

R-B2.5 If match data for a valid `gw` exists but is not yet published/available, the API may:
- return `409 MATCHES_NOT_AVAILABLE`, OR
- return an empty list with an explicit availability indicator in the response meta (if implemented).
One approach must be chosen consistently per league/environment.

---

## B3. Match detail behavior (GET /leagues/{league_id}/matches/{match_id})

R-B3.1 Match detail must include:
- identifiers and fixture context (`league_id`, `gw`, `match_id`)
- status and timing
- team identifiers and display fields
- score breakdown sufficient for the match detail screen (per API schemas)

R-B3.2 If `match_id` is unknown in the league, return `404 MATCH_NOT_FOUND`.

R-B3.3 If the match exists but is not visible to the user due to league access rules, return the standard league access error.

---

## B4. Real league table rules (GET /leagues/{league_id}/table)

R-B4.1 The real league table is derived from match results that are `finished`.

R-B4.2 Tie-break order is:
1) `team_points` (primary)
2) `match_points`
3) `set_points`

R-B4.3 Table visibility:
- If the table is not computed/published yet (e.g., early season or admin not run), the API may return `409 TABLE_NOT_AVAILABLE`.

R-B4.4 Recalculation is required after:
- a postponed match becomes `finished`
- any correction to a previously finished match (R-B1.6)

---

## B5. Player stats rules (GET /leagues/{league_id}/stats/players)

Bundle B stats are a **league-wide leaderboard** showing **season-to-date totals** for players.  
Optionally, the API can also expose a “weekly” column for a chosen gameweek (default: the latest finished GW).

R-B5.1 Player stats are derived from match events/results in `finished` matches.

R-B5.2 The response must declare the scope explicitly:
- `totals_through_gw` — the latest fixture GW that is fully included in totals (can be `< meta.current_gw` if the current GW is still open / not finished).
- `week_gw` — the fixture GW used for any “weekly” fields in the payload (see R-B5.4).

R-B5.3 The request may accept `week_gw` (int, optional).  
If omitted, the server must default `week_gw = totals_through_gw` (latest finished GW).

R-B5.4 If the UI needs “latest GW points”, the server should provide per-player weekly fields for `week_gw`, e.g.:
- `week_fantasy_points` (recommended minimum)
- optional: `week_pins`, `week_setpoints`, `week_matchpoints`

R-B5.5 Eligibility:
- By default, return only players that have non-zero participation in the totals scope.
- If the product later needs “all players including zeros”, that must be an explicit option (do not silently change behavior).

R-B5.6 If `week_gw` is outside the league’s valid range, the API must return `404 GW_NOT_FOUND`.

R-B5.7 If stats are not computed/published yet (e.g., no finished match data exists), the API may return `409 STATS_NOT_AVAILABLE`.

R-B5.8 Corrections:
- If a match is corrected (R-B1.6), affected player totals and weekly fields must be recalculated, and the stats ETag must change.

---

## B6. Market + player detail “availability” semantics (Bundle B screens)

These rules support consistent UX for:
- `GET /leagues/{league_id}/market/players`
- `GET /leagues/{league_id}/players/{player_id}`

R-B6.1 Server computes availability; client must not attempt to replicate business logic beyond presentation.

R-B6.2 Market list entries may expose:
- `availability.can_select` (bool)
- `availability.disabled_reasons[]` (array of machine-stable reason codes; empty when `can_select=true`)

R-B6.3 Player detail actions may expose:
- `actions.can_buy / can_sell / can_replace / can_captain` (bools)
- `actions.disabled_reasons[]` (same reason code set as R-B6.2)

R-B6.4 Recommended reason codes (non-exhaustive; extend as needed):
- `GW_CLOSED`
- `BUDGET_INSUFFICIENT`
- `MAX_PLAYERS_FROM_TEAM`
- `ALREADY_OWNED`
- `NOT_IN_MARKET`
- `TRANSFER_LIMIT_REACHED`

R-B6.5 If a request is valid syntactically but impossible to evaluate in the current market context, the API may return `422 MARKET_CONTEXT_INVALID` (preferred when the whole payload is invalid).  
Otherwise, return the payload with `can_select=false` (and/or action booleans false) and appropriate `disabled_reasons`.

R-B6.6 Any write that affects roster/availability (transfer confirm, admin correction of budgets/limits) should trigger client revalidation of:
- market list
- relevant player detail payloads
- home/my-team screens that show budget or roster counts

---

## B7. Phase C Bundle B scope

In scope (Bundle B):
- Matches list (GW-scoped)
- Match detail
- Real league table
- Player stats leaderboard (season-to-date totals + weekly column)
- Market players list
- Player detail modal (market context)

Out of scope (future):
- season-to-date stats variants
- advanced match timelines / play-by-play
- admin endpoints for publishing/recalculating (implementation detail)
