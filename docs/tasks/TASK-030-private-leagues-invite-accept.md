# TASK-030 — POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept (v1)

**Goal:** Accept a private league invite. Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.10.2 Accept**
- `docs/spec/api-schemas-updated.md` → accept response
- `docs/spec/api-errors-updated.md` → INVITE_NOT_FOUND, INVITE_NOT_PENDING
- caching/matrix: Category C; revalidate list + detail; revalidate /notifications

## Endpoint
### POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept
- Auth required
- `Cache-Control: no-store`, `meta.etag=null`

invite_id parsing:
- deterministic format `pl{privateleague_id}-c{competitor_id}` (string)
- parse and validate both numbers.

## Behavior
- Validate invite exists for caller competitor_id, in requested league_id.
- If not found → 404 INVITE_NOT_FOUND
- If status not pending → 409 INVITE_NOT_PENDING
- Else set status member_confirmed (confirmed=1 legacy) + set accepted_at/confirmed_at if columns exist.

(Recommended) Notify admin via notification.

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/invites/(.+)/accept$ -> leagues/private-leagues/invites/accept/index.php?league_id=$1&invite_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/invites/accept/index.php`

## Smoke
`scripts/pl-invite-accept-smoke.ps1`:
1) Ensure there is a pending invite (create admin + invite in seed or via TASK-028)
2) POST accept → 200 ok:true
3) GET list → league appears in leagues[]
4) Re-accept same invite → 409 INVITE_NOT_PENDING (or idempotent ok:true if you choose, but follow spec)
