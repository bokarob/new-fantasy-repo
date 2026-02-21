# Bottom navigation tabs

> **Note (legacy):** This file is an early Phase A screen list kept for reference.
> For the current source of truth, use **phase-c-screens.md** + **phase-c-api-contracts.md** + **api-schemas-updated.md**.


## 1) Home

Type: Screen (tab)

Payload endpoints
    GET /home (no league selected)
    GET /home?league_id={league_id} (league selected)

Actions
    none (pure navigation)
    optional: POST /me/notifications/read (if you implement read state)

Rules / config it cares about
    R3 Gameweek open/closed
        league_selector.leagues[].status.current_gw
        league_selector.leagues[].status.deadline
        league_context.gameweek.is_open
    R8 Rankings (for “your team rank” block)
        league_context.your_team.rank, previous_rank, rank_change
    Team of the week (derived from results; not a rule, but depends on R7 scoring inputs)
        highlights.team_of_the_week.players[].weekly_points, pins

## 2) Team

Type: Screen (tab) with subview toggle (Grid / List)

Payload endpoints
    GET /leagues/{league_id}/team

Actions
    Captain:
        POST /leagues/{league_id}/team/captain
    Substitution / reorder (if implemented as swap):
        POST /leagues/{league_id}/team/substitute (or reorder endpoint)
    Transfers (via flow endpoints):
        POST /leagues/{league_id}/transfers/quote
        POST /leagues/{league_id}/transfers/confirm

Rules / config it cares about
    R3 GW state: gameweek.deadline, gameweek.is_open
    R4 Roster structure & max2/team:
        roster.positions[pos=1..8]
        ui_hints.starter_positions, ui_hints.sub_positions
        max-from-team constraint = 2 (can also be returned as config)
    R5 Transfers:
        gameweek.transfers_allowed, gameweek.transfers_used
        competitor.credits
    R6 Captain rules:
        roster.captain_player_id
        starter-only constraint

## 3) Matches

Type: Screen (tab) with subviews: Matches / Table / Stats

Payload endpoints
    Matches subview:
        GET /leagues/{league_id}/matches?gw={gw} (recommended: includes embedded details)
    Table subview:
        GET /leagues/{league_id}/table (real league standings)
    Stats subview:
        GET /leagues/{league_id}/stats/players (if/when you implement; currently planned)

Actions
    none (read-only)

Rules / config it cares about
    R3 Current GW navigation:
        gw_nav.prev_gw/next_gw/min_gw/max_gw
        gameweek.is_open, deadline
    R8.5–R8.7 Real league table ordering (display logic only)
    R7 Scoring inputs (for showing fantasy points in match details):
        details.players[].pins/setpoints/matchpoints/fantasy_points

## 4) Leagues

Type: Screen (tab) (overall/fan/private sections)

Payload endpoints
    GET /leagues/{league_id}/fantasy
    For private league detail (when opened):
        GET /leagues/{league_id}/private-leagues/{privateleague_id} (planned)

Actions
    Create private league:
        POST /leagues/{league_id}/private-leagues
    Invite/search:
        GET /leagues/{league_id}/private-leagues/{id}/invite/search?q=...
        POST /leagues/{league_id}/private-leagues/{id}/invite
    Apply/join/accept/leave (depending on your exact workflow):
        POST /.../apply
        POST /.../members/{competitor_id}/accept
        POST /.../leave

Rules / config it cares about
    R8 Ranking + tie display:
        overall.items[].rank/previous_rank/rank_change
        tie display convention
    R9 Fan/private league membership rules
        favorite team existence affects fan league section
        private league membership statuses: invited/pending/confirmed

## 5) More

Type: Menu modal (entry points only; no extra screens)

Payload endpoints
    none (it’s just navigation shortcuts)

Actions
    none

Rules / config it cares about
    none

# More menu screens
## 6) Profile

Type: Screen

Payload endpoints
    GET /me
    GET /me/teams (show teams across leagues)

Actions
    Update profile:
        PATCH /me
    Delete competitor/team:
        DELETE /leagues/{league_id}/team (with confirmation input)

Rules / config it cares about
    R12 Deletion rules (irreversible, confirmation)
    R11.4 Team name constraints (if you allow rename here or via team creation only)
    Language/newsletter preferences (not rules-spec-critical; profile settings)

## 7) Notifications list (if you add it)

Type: Screen

Payload endpoints
    GET /me/notifications

Actions
    optional: POST /me/notifications/read

Rules / config it cares about
    none (R13 is about generation; screen is display only)

## 8) Rules

Type: Screen (static or remote JSON)

Payload endpoints
    either bundled JSON in-app, or:
    GET /leagues/{league_id}/rules

Actions
    none

Rules / config it cares about
    language selection (profile/app setting)
    no gameplay rule enforcement here (display only)

## 9) Contact / Feedback

Type: Screen

Payload endpoints
    none

Actions
    POST /contact (sends email)

Rules / config it cares about
    rate limiting (429 handling; not core rules)

## 10) Settings

Type: Screen

Payload endpoints
    GET /me (for current language/newsletter)
    optional: GET /leagues/{league_id}/rules (if rules content differs by language)

Actions
    PATCH /me (lang/newsletter/profile prefs)
    OS-level push settings handled client-side

Rules / config it cares about
    none directly, aside from localization selection

# Modals (reusable UI components)
## 11) Player modal (owned/not-owned)

Type: Modal (opened from Team, Matches Stats, Market)

Payload endpoints
    GET /leagues/{league_id}/players/{player_id}

Actions
    If owned:
        captain: POST /leagues/{league_id}/team/captain
        mark sell / replace: handled client-side until transfer confirm
    If not owned:
        “buy/replace” only leads into transfer flow endpoints


Rules / config it cares about
    R3 actions disabled if GW closed
    R4.6 max2/team (for buy eligibility)
    R5 budget + transfer limits (for buy eligibility)
    R6 captain allowed only for starters (and owned)

## 12) Captain selection modal (optional shortcut)

Type: Modal

Payload endpoints
    uses already-loaded Team payload (/team) or can reuse GET /leagues/{league_id}/team

Actions
    POST /leagues/{league_id}/team/captain

Rules / config it cares about
    R6.2 must be starter
    R3.6 only while GW open

## 13) Transfer confirm modal

Type: Modal

Payload endpoints
    POST /leagues/{league_id}/transfers/quote (to show final summary)
    optionally reuse cached quote data

Actions
    POST /leagues/{league_id}/transfers/confirm

Rules / config it cares about
    R5.8 atomicity (server enforcement)
    R5.6 budget check
    R5.1 transfer limit
    R3 GW open check

## 14) Share team modal

Type: Modal (OS share sheet)

Payload endpoints
    none beyond Team payload already loaded (/team)

Actions
    none (client-side image generation)

Rules / config it cares about
    none

## 15) League selector modal (on Home)

Type: Modal / Dropdown

Payload endpoints
    GET /home provides league list; no additional call required

Actions
    none (just changes selected_league_id client-side and triggers reload /home?league_id=...)

Rules / config it cares about
    none

# Flows (multi-step)
## 16) Registration + OTP verification

Type: Flow

Payload endpoints
    none (mostly action driven)

Actions
    POST /auth/register
    POST /auth/otp/send
    POST /auth/otp/verify

Rules / config it cares about
    R10 OTP expiry/retry/cooldown (client shows timers/messages based on error codes)
        OTP_EXPIRED, OTP_RETRY_LIMIT, OTP_RESEND_COOLDOWN

## 17) Login

Type: Flow

Actions
    POST /auth/login
    POST /auth/token/refresh
    POST /auth/logout

Rules / config it cares about
    verified requirement: R10.1 (AUTH_EMAIL_NOT_VERIFIED)

## 18) Password reset

Type: Flow

Actions
    POST /auth/password/forgot
    POST /auth/password/reset (OTP + new password)


Rules / config it cares about
    OTP policy (same as registration)

## 19) Initial team creation

Type: Flow (squad builder → team name → favorite team optional → submit)

Payload endpoints
    GET /leagues/{league_id}/team/builder

Actions
    POST /leagues/{league_id}/team
    optional later change favorite:
        POST /leagues/{league_id}/team/favorite (if you keep it separate)

Rules / config it cares about
    R11 team creation required before participating
    R4 roster size (8), starters/subs (6/2), max2/team
    R5.5 initial budget 80.0
    R6 captain must be a starter