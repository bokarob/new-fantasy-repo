# TASK-014 — Implement POST /contact (Support message) (v1)

**Goal:** Submit support/feedback message (More → Contact). Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **5.7 POST /contact**
- `docs/spec/api-schemas-updated.md` → **POST /contact — Request/Response**
- `docs/spec/api-errors-updated.md` → **Account (Bundle C) → POST /contact**
- `docs/spec/caching-updated.md` → Category C

## Endpoint
### POST /contact
- Auth required (match contracts/errors)
- Category C:
  - `Cache-Control: no-store`
  - `meta.etag = null`

## Request
```json
{
  "subject": "Bug report",
  "message": "I cannot open the Team tab.",
  "context": { "app_version": "1.0.0", "league_id": 1 }
}
```
Rules:
- `message` required, trimmed non-empty
- `subject` optional
- `context` optional object; reject nested objects/arrays (only scalar leaf values)

## Response
Success: `data.ok=true` with meta fields and `etag:null`.

## Errors
- `400 BAD_REQUEST` invalid/missing JSON
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `422 VALIDATION_ERROR` message empty/too long or bad context
- `500 INTERNAL_ERROR`

## Handling (v1)
Recommended:
- log to PHP error log with profile_id + subject + message + context + UTC time
No new DB schema in this task.

## Routing
- Root rule: `^contact$ -> contact/index.php [QSA,L]`
- Handler: `contact/index.php`

## Smoke test
`scripts/contact-smoke.ps1`:
1) login → token
2) POST /contact valid → 200 ok:true + no-store
3) POST missing message → 422 VALIDATION_ERROR
4) no token → 401

## Acceptance
- Endpoint reachable
- Validations enforced
- Category C headers correct
- Smoke passes
