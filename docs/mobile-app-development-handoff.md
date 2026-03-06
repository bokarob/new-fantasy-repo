# Mobile App Development Handoff

## 1) Purpose

This document is the mobile-development handoff for the Fantasy app project.

It is meant to let a mobile coding agent or developer start implementation without re-reading the entire project history. It summarizes:

- what backend/API scope is already implemented
- which project documents are the source of truth
- how to run the backend locally in a repeatable way
- how auth, caching, errors, and revalidation work
- how the mobile app should integrate screen-by-screen
- which items are still lower priority / optional

This handoff is based on:
- the frozen Phase A–C specification documents in the project folder
- the Phase D implementation work completed in this project
- the current local smoke/regression workflow

---

## 2) Current project status

### Spec status
Phase A–C specification work is treated as complete/frozen for v1:
- screen definitions
- API contracts
- shared schemas
- error catalog
- caching / ETag strategy
- endpoint matrix
- core rules
- versioning approach

### Backend status
The backend implementation has progressed through the major Phase D API surface, including smoke scripts and a master regression runner.

Implemented endpoint groups include:

#### Auth
- register
- OTP send / verify
- login
- refresh token
- logout
- forgot password
- reset password

#### Home
- GET `/home`
- GET `/home?league_id=...`

#### Team management
- GET `/leagues/{league_id}/team`
- GET `/leagues/{league_id}/team/builder`
- POST `/leagues/{league_id}/team` (initial team creation)
- POST `/leagues/{league_id}/team/captain`
- POST `/leagues/{league_id}/team/substitute`

#### Transfers / market
- POST `/leagues/{league_id}/transfers/quote`
- POST `/leagues/{league_id}/transfers/confirm`
- GET `/leagues/{league_id}/transfers`
- GET `/leagues/{league_id}/market/players`

#### Rankings
- GET `/leagues/{league_id}/fantasy`

#### Rules
- GET `/leagues/{league_id}/rules`

#### Account / more
- GET `/me`
- PATCH `/me`
- GET `/me/teams`
- POST `/contact`

#### Notifications
- GET `/notifications`
- POST `/notifications/{id}/read`
- POST `/notifications/read-all`

#### Matches / table / stats
- GET `/leagues/{league_id}/matches`
- GET `/leagues/{league_id}/matches/{match_id}`
- GET `/leagues/{league_id}/table`
- GET `/leagues/{league_id}/stats/players`

#### Private leagues
Core flow:
- GET `/leagues/{league_id}/private-leagues`
- POST `/leagues/{league_id}/private-leagues`
- GET `/leagues/{league_id}/private-leagues/{privateleague_id}`
- POST `/leagues/{league_id}/private-leagues/{privateleague_id}/leave`
- GET `/leagues/{league_id}/private-leagues/{privateleague_id}/invite/search`
- POST `/leagues/{league_id}/private-leagues/{privateleague_id}/invite`
- GET `/leagues/{league_id}/private-leagues/invites`
- POST `/leagues/{league_id}/private-leagues/invites/{invite_id}/accept`
- POST `/leagues/{league_id}/private-leagues/invites/{invite_id}/decline`

Admin / optional:
- POST remove member
- POST rename private league
- POST delete private league

### Testing status
There is now:
- individual smoke scripts per endpoint group
- a master regression runner
- a deterministic DB reset + seed workflow for regression

This means the backend is in a good state for mobile integration.

---

## 3) Source of truth map for mobile work

A mobile developer should not invent shapes or logic. Use these files:

### Product / screen source of truth
- `phase-b-screens.md`
- `phase-c-screens.md`

These describe:
- screens
- user flows
- empty/error states
- which endpoints each screen needs

### Endpoint contract source of truth
- `phase-b-api-contracts.md`
- `phase-c-api-contracts.md`

These define:
- endpoint behavior
- examples
- intended request/response semantics

### Shared payload shapes
- `api-schemas-updated.md`

This is the canonical place for:
- response envelopes
- shared item schemas
- endpoint request/response structures

### Error handling
- `api-errors-updated.md`

This defines:
- error envelope
- endpoint-specific error codes
- when an endpoint returns a hard error vs a 200 with violations (example: transfer quote)

### Caching / revalidation
- `caching-updated.md`
- `endpoint-matrix-updated.md`

These define:
- Category A / B / C endpoint behavior
- when to use ETag revalidation
- which payloads should be refreshed after actions

### Gameplay / rules logic
- `core-rules-updated.md`

This is authoritative for:
- roster rules
- captain rules
- transfer rules
- gameweek open/closed behavior
- ranking logic
- private league behavior

### Orientation / implementation context
- `phase-d-implementation-plan.md`
- `Milestone_planning-1.md`
- `phase-d-codex-workstyle.md`

These are useful as “how to work in this project” context documents.

---

## 4) Local backend workflow

## Environment assumptions
Local stack is currently:
- PHP + Apache via XAMPP
- MySQL / MariaDB
- local DB name: `fantasy_app`

Typical local auth-related env/config used during development:
- `JWT_SECRET`
- `APP_ENV=local`
- `AUTH_FIXED_OTP=123456` (dev-only, optional)
- other auth debug flags only if needed

## Deterministic regression workflow
Use these scripts:

### Reset + seed user-scoped test data
- `scripts/reset-db.ps1`

This resets only user/competitor-related state and keeps reference content intact:
- keeps leagues, gameweeks, players, teams, prices, player results, matches, etc.
- preserves `profile_id=1`
- reseeds a deterministic regression state

### Run all smoke scripts
- `scripts/run-regression.ps1`

This runs all smoke scripts in a stable order and prints a summary.

### One-step workflow
- `scripts/run-all.ps1`

This runs:
1. reset DB
2. full regression runner

### Regression seed contents
The deterministic user seed leaves:
- 4 profiles total
- 3 competitors in `league_id=10`
- 1 private league
- 1 confirmed member
- 1 pending invite
- unread notifications
- fantasy ranking data
- teamresult rows
- leaguetable rows

This is specifically designed so that regression can run without manual DB preparation.

---

## 5) Core backend conventions mobile must respect

## Auth model
- Access token: JWT
- Refresh token: opaque token, rotated on refresh
- Access token required for basically all app endpoints after login
- Refresh flow exists and should be used by the app session layer

## Important compatibility note
Password hashing is intentionally still legacy-compatible in backend implementation:
- `md5(password + email)`
This is a backend compatibility detail only; mobile just sends plaintext password over HTTPS to the login endpoint.

## Envelope conventions
Success responses use:
- `meta`
- `data`

Error responses use:
- `error.code`
- `error.message`
- optional `error.rule`
- optional `error.details`

The mobile app should build a shared network layer around these two envelope shapes.

## Caching categories
### Category A
Used for read payloads that support:
- `Cache-Control: private, must-revalidate`
- `ETag`
- `If-None-Match` revalidation
- `304 Not Modified`

Mobile should store last ETag per request key and revalidate aggressively for these endpoints.

### Category B
Short-TTL read endpoints (used more sparingly, e.g. invite search).
Treat as short-lived and do not over-cache.

### Category C
Write/action endpoints:
- `Cache-Control: no-store`
- `meta.etag = null`

After a Category C success, mobile should refresh the relevant affected Category A payloads.

## Revalidation mindset
Do not blindly refetch everything. Use the endpoint matrix.

Examples:
- after captain/substitute/team create/transfer confirm:
  - refresh `GET /leagues/{league_id}/team`
  - often also refresh `/home?league_id=...`
- after notification read / read-all:
  - refresh `/notifications`
  - optionally refresh `/home`
- after private league membership changes:
  - refresh private league list/detail
  - sometimes notifications

---

## 6) Recommended mobile integration order

Even though the backend is broad now, the mobile app should still integrate in a staged order.

## Stage 1 — app shell + session
Build:
- auth flow
- token storage
- refresh flow
- logout
- basic app shell with bottom tabs

Screens:
- login / register / forgot password
- app bootstrap / session restore

## Stage 2 — Home + Team
Build:
- home screen
- team screen
- builder / create team flow
- captain and substitute actions

Why:
- this gives a working user loop fast
- team payload and home payload are already stable and well-smoke-tested

## Stage 3 — Transfers
Build:
- market list
- quote/confirm flow
- transfer history

Important:
- quote is special because rule failures come back as `200` with `is_valid=false`
- confirm uses hard errors for enforcement failures

## Stage 4 — Rankings / Rules / Account
Build:
- rankings
- rules screen
- profile/settings
- my teams
- contact

This completes the “main utility” pieces.

## Stage 5 — Notifications + Matches / Table / Stats
Build:
- notifications inbox + read actions
- matches list/detail
- real table
- player stats

This brings content depth and engagement.

## Stage 6 — Private leagues
Build:
- private league list
- create
- detail
- invite flow
- accept/decline
- leave
- admin actions as needed

This is the most stateful/social feature, so it’s good after core flows are stable.

---

## 7) Screen-to-endpoint integration map (practical)

## Home tab
Primary endpoint:
- `GET /home`
- `GET /home?league_id=...`

Needs:
- league selector
- notifications preview
- league context
- news preview

## Team tab
Primary endpoint:
- `GET /leagues/{league_id}/team`

Actions:
- captain
- substitute
- transfers quote
- transfers confirm

Builder/onboarding:
- `GET /leagues/{league_id}/team/builder`
- `POST /leagues/{league_id}/team`

## Transfers flow
Read:
- `GET /leagues/{league_id}/market/players`
- `GET /leagues/{league_id}/transfers`

Actions:
- quote
- confirm

## Rankings tab
- `GET /leagues/{league_id}/fantasy`

## Matches tab
- `GET /leagues/{league_id}/matches`
- `GET /leagues/{league_id}/matches/{match_id}`
- `GET /leagues/{league_id}/table`
- `GET /leagues/{league_id}/stats/players`

## More / Account
- `GET /me`
- `PATCH /me`
- `GET /me/teams`
- `POST /contact`
- `GET /leagues/{league_id}/rules`
- `GET /notifications`
- notification read actions

## Private leagues
- list
- create
- detail
- invites
- invite search
- accept / decline
- leave
- optional admin actions

---

## 8) Mobile data-layer recommendations

## Normalize request keys
For list/detail payloads, use stable cache keys based on:
- path
- league_id
- gw
- filters/sort/paging
- user-scoped context when relevant

Examples:
- `home:selectedLeague=10`
- `team:league=10`
- `market:league=10:q=:team=:sort=price_asc:offset=0:limit=50`
- `notifications:filter=all:cursor=:limit=20`

## Store ETags with cache entries
For Category A responses, persist:
- payload data
- ETag
- fetched timestamp
- request key

On next open, send `If-None-Match`.

## Centralize auth failure handling
If backend returns:
- `401 AUTH_INVALID_TOKEN`
- `401 AUTH_REQUIRED`

Then:
1. try refresh (if refresh token exists)
2. retry original request once
3. if refresh fails, send user to login

## Centralize error presentation
Map:
- validation / rule errors → inline UI
- auth errors → session flow
- network/5xx errors → retry / generic banner
- special endpoint-specific codes (e.g. `TEAM_ALREADY_EXISTS`, `INITIAL_BUDGET_EXCEEDED`, `CAPTAIN_NOT_STARTER`) → feature-specific messages

## Do not hardcode gameplay constants in UI
Even if the backend currently uses constants like:
- roster_size = 8
- starters = 6
- subs = 2
- max_from_same_team = 2
- transfers per GW = 2
- initial budget = 80.0

Prefer to read these from payloads/rules where available.

---

## 9) Known implementation assumptions / practical notes

## Data-dependent smoke behavior
Historically, some smoke scripts only failed because the DB state was wrong (team already exists, no pending invite, etc.).
This is now addressed by:
- reset-db
- deterministic user seed
- run-all workflow

## Table endpoint
If `leaguetable` is not populated, the table endpoint may legitimately return “not available” depending on implementation mode.
For mobile:
- handle 409 gracefully if no table exists yet
- this is not necessarily a network failure

## Empty-state awareness
Some endpoints can validly return empty arrays instead of errors:
- notifications
- player stats
- private leagues list
- invites list
- me/teams

The app should distinguish:
- empty data state
- unavailable state (409)
- error state

## Optional features
Some private league admin features may be lower-priority in UI even if backend exists:
- rename
- delete
- remove member

These can be integrated later without blocking MVP.

---

## 10) Suggested mobile technical direction

The project is currently backend-first and contract-driven. For mobile, keep the same philosophy:

- one typed network layer
- one auth/session layer
- one cache/revalidation layer
- features grouped by tab / user journey
- avoid per-screen bespoke request logic

A good mobile app structure would have:
- `auth/`
- `home/`
- `team/`
- `transfers/`
- `rankings/`
- `matches/`
- `notifications/`
- `account/`
- `privateLeagues/`
- shared:
  - `api/`
  - `session/`
  - `cache/`
  - `models/`
  - `errorHandling/`

The exact tech stack is still open, but whichever stack is chosen, the project should keep:
- schema-first integration
- deterministic endpoint wrappers
- ETag-aware data access

---

## 11) Recommended immediate next step for mobile start

The cleanest first mobile implementation milestone would be:

### Milestone M1
- auth flow
- `/home`
- `/leagues/{league_id}/team`
- `/leagues/{league_id}/team/builder`
- `/leagues/{league_id}/team` create
- captain / substitute
- basic app navigation + account stub

Why:
- it proves login/session + league selection + gameplay core
- it avoids needing every feature before showing a usable app
- it uses the most mature/tested backend flows first

### Milestone M2
- market list
- transfer quote / confirm
- rankings
- rules
- `/me`

### Milestone M3
- notifications
- matches / detail / table / stats

### Milestone M4
- private leagues

---

## 12) Useful files to hand to a mobile coding agent first

If a separate agent starts the mobile app, the best initial context bundle is:

1. `phase-c-screens.md`
2. `phase-b-screens.md`
3. `api-schemas-updated.md`
4. `phase-b-api-contracts.md`
5. `phase-c-api-contracts.md`
6. `api-errors-updated.md`
7. `caching-updated.md`
8. `endpoint-matrix-updated.md`
9. this handoff document

That is enough to:
- understand the app shape
- know what endpoints exist
- know how they behave
- implement the first screens correctly

---

## 13) Final note

At this point the backend is no longer the main blocker.
The project is ready to transition from “spec + API implementation” into “mobile integration + UX refinement”.

The most important discipline to keep is:
- treat the spec docs as the source of truth
- update docs if implementation changes semantics
- keep regression green
- integrate mobile in small screen-based slices rather than trying to wire the whole app at once
