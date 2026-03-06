# Mobile Integration Index

## 1) Purpose

This is the mobile start-here doc for the current backend.
Specs remain the source of truth.
This file is only an orientation and integration guide for a mobile developer or coding agent.

---

## 2) Source-of-truth map

- `docs/spec/phase-b-screens.md` + `docs/spec/phase-c-screens.md` - screens
- `docs/spec/phase-b-api-contracts.md` + `docs/spec/phase-c-api-contracts.md` - contracts
- `docs/spec/api-schemas-updated.md` - schemas
- `docs/spec/api-errors-updated.md` - errors
- `docs/spec/caching-updated.md` - caching categories and revalidation rules
- `docs/spec/endpoint-matrix-updated.md` - endpoint matrix and refresh targets
- `docs/spec/core-rules-updated.md` - gameplay and league rules

---

## 3) Runtime / local workflow

- Backend stack: PHP + Apache (XAMPP), MySQL/MariaDB, local DB `fantasy_app`
- Auth basics: Bearer JWT access token, opaque refresh token, most app endpoints require auth
- Auth routing: `/auth/*` is handled by `auth/.htaccess`, which rewrites subpaths into `auth/index.php`
- Local auth defaults in this repo: root `.htaccess` sets `JWT_SECRET`, `APP_ENV=local`, and `AUTH_FIXED_OTP=123456`
- Reset DB: `powershell -ExecutionPolicy Bypass -File scripts\reset-db.ps1`
- Run regression: `powershell -ExecutionPolicy Bypass -File scripts\run-regression.ps1`
- Run all: `powershell -ExecutionPolicy Bypass -File scripts\run-all.ps1`

---

## 4) Implemented API inventory

### Auth
- `POST /auth/register` - register
- `POST /auth/otp/send` - resend OTP
- `POST /auth/otp/verify` - verify OTP
- `POST /auth/login` - login
- `POST /auth/token/refresh` - refresh
- `POST /auth/logout` - logout
- `POST /auth/password/forgot` - forgot password
- `POST /auth/password/reset` - reset password

### Home
- `GET /home` - selector payload
- `GET /home?league_id={league_id}` - league payload

### Team
- `GET /leagues/{league_id}/team` - roster payload
- `GET /leagues/{league_id}/team/builder` - builder payload
- `POST /leagues/{league_id}/team` - create team
- `POST /leagues/{league_id}/team/captain` - set captain
- `POST /leagues/{league_id}/team/substitute` - substitute players

### Transfers
- `GET /leagues/{league_id}/market/players` - market list
- `POST /leagues/{league_id}/transfers/quote` - transfer quote
- `POST /leagues/{league_id}/transfers/confirm` - confirm transfer
- `GET /leagues/{league_id}/transfers` - transfer history

### Rankings
- `GET /leagues/{league_id}/fantasy` - rankings

### Rules
- `GET /leagues/{league_id}/rules` - rules payload

### Notifications
- `GET /notifications?filter={all|unread}&cursor={cursor?}&limit={limit?}` - notifications list
- `POST /notifications/{notification_id}/read` - mark read
- `POST /notifications/read-all` - mark all read

### Matches / Table / Stats
- `GET /leagues/{league_id}/matches?gw={gw?}` - matches list
- `GET /leagues/{league_id}/matches/{match_id}` - match detail
- `GET /leagues/{league_id}/table` - league table
- `GET /leagues/{league_id}/stats/players?team_id={team_id?}&sort={sort?}&limit={limit?}&offset={offset?}&week_gw={week_gw?}` - player stats

### Account
- `GET /me` - profile
- `PATCH /me` - update profile
- `GET /me/teams` - my teams
- `POST /contact` - contact form

### Private leagues
- `GET /leagues/{league_id}/private-leagues` - list + invites
- `POST /leagues/{league_id}/private-leagues` - create
- `GET /leagues/{league_id}/private-leagues/{privateleague_id}` - detail
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave` - leave
- `GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q={q}&limit={limit?}` - invite search
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite` - invite
- `GET /leagues/{league_id}/private-leagues/invites` - invites inbox
- `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept` - accept invite
- `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline` - decline invite
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove` - admin remove
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename` - admin rename
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/delete` - admin delete

---

## 5) Mobile integration order

- M1: auth + home + team
- M2: transfers + rankings + rules
- M3: notifications + matches/table/stats
- M4: private leagues

---

## 6) Integration notes

- Success envelope: `meta` + `data`
- Error envelope: `error.code` + `error.message`, sometimes `error.rule` and `error.details`
- Caching: Category A = ETag + `If-None-Match` + `304`; Category B = short-lived reads; Category C = writes with `Cache-Control: no-store` and `meta.etag = null`
- Revalidate after writes: after any successful Category C action, refresh the affected Category A reads from `docs/spec/caching-updated.md` and `docs/spec/endpoint-matrix-updated.md`
- Transfer quote special case: validation failures return `200` with `is_valid=false` and `violations[]`
- Do not hardcode gameplay constants if `/leagues/{league_id}/rules` or payloads already expose them
