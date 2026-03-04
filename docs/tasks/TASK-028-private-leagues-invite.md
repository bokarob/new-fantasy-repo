# TASK-028 — POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite (Send invite) (v1)

**Goal:** Invite a competitor into private league. Admin-only. Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.9 Invite**
- `docs/spec/api-schemas-updated.md` → invite request/response
- `docs/spec/api-errors-updated.md` → NOT_ADMIN, ALREADY_MEMBER, ALREADY_INVITED
- notifications rules: invite triggers notification to invitee (recommended)
- caching/matrix: Category C; revalidate PL detail + list; revalidate /notifications for invitee

## Endpoint
### POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite
- Auth required
- Admin-only (`403 NOT_ADMIN`)
- `Cache-Control: no-store`, `meta.etag=null`

Body:
```json
{ "competitor_id": 9002 }
```

## Errors
- 400 BAD_REQUEST
- 401 auth errors
- 403 NOT_ADMIN
- 404 PRIVATE_LEAGUE_NOT_FOUND
- 409 ALREADY_MEMBER
- 409 ALREADY_INVITED
- 500 internal

## Persistence (transaction)
1) Validate PL exists and caller is admin.
2) Validate target competitor exists in same base league (R9.3).
3) Check membership row:
   - confirmed → 409 ALREADY_MEMBER
   - pending → 409 ALREADY_INVITED
4) Insert membership row with status pending (and timestamps).
5) (Recommended) Create a notification for invitee profile_id with target.kind=private_league_invite.

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/invite$ -> leagues/private-leagues/invite/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/invite/index.php`

## Smoke
`scripts/pl-invite-smoke.ps1`:
1) login as admin, create PL
2) search for a competitor to invite
3) POST invite → 200 ok:true
4) GET detail → pending_members includes invited competitor
