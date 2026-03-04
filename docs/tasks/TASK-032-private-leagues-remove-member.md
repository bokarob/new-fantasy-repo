# TASK-032 — POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove (Admin) (v1)

**Goal:** Admin removes a member from private league. Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.5 Remove (Admin)**
- `docs/spec/api-schemas-updated.md` → remove response
- `docs/spec/api-errors-updated.md` → NOT_ADMIN, MEMBER_NOT_FOUND, CANNOT_REMOVE_SELF
- caching/matrix: Category C; revalidate PL detail + list

## Endpoint
### POST /leagues/{league_id}/private-leagues/{privateleague_id}/members/{competitor_id}/remove
- Auth required
- Admin-only: else 403 NOT_ADMIN
- `Cache-Control: no-store`, `meta.etag=null`

Rules:
- cannot remove self → 409 CANNOT_REMOVE_SELF
- competitor_id must be a member (confirmed or pending) → else 404 MEMBER_NOT_FOUND

Persistence:
- delete membership row (or mark removed)

Routing:
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/members/([0-9]+)/remove$ -> leagues/private-leagues/members/remove/index.php?league_id=$1&privateleague_id=$2&competitor_id=$3 [QSA,L]`
- handler: `leagues/private-leagues/members/remove/index.php`

Smoke:
`scripts/pl-remove-member-smoke.ps1`:
- Requires 2 users/competitors or a seeded second competitor.
- Invite or add second competitor, then remove and confirm detail list changes.
