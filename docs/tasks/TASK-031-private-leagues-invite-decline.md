# TASK-031 — POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline (v1)

**Goal:** Decline a private league invite. Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.10.3 Decline**
- `docs/spec/api-schemas-updated.md` → decline response
- `docs/spec/api-errors-updated.md` → INVITE_NOT_FOUND, INVITE_NOT_PENDING
- caching/matrix: Category C; revalidate list; revalidate /notifications

## Endpoint
### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline
- Auth required
- `Cache-Control: no-store`, `meta.etag=null`

Behavior:
- Validate invite exists for caller competitor_id, in league_id
- 404 INVITE_NOT_FOUND if missing
- 409 INVITE_NOT_PENDING if not pending
- Else set status declined OR delete membership row (choose one; keep consistent)
Recommended: set status declined to keep history.

(Recommended) Notify admin via notification.

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/invites/(.+)/decline$ -> leagues/private-leagues/invites/decline/index.php?league_id=$1&invite_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/invites/decline/index.php`

## Smoke
`scripts/pl-invite-decline-smoke.ps1`:
1) Ensure a pending invite exists
2) POST decline → 200 ok:true
3) GET invites → does not include it
