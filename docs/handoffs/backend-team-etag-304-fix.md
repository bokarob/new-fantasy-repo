# Backend Team ETag 304 Fix

## Summary

This fixes the backend conditional GET bug on `GET /leagues/{league_id}/team` where unchanged revalidation returned `200 OK` instead of `304 Not Modified`.

The fix is endpoint-local in `leagues/team/index.php`. No mobile client changes were made.

## Root Cause

`/leagues/{league_id}/team` already computed and returned a correct weak ETag, but its `If-None-Match` comparison logic was narrower than the working Category A endpoints.

Before the fix, `team_if_none_match_matches()`:

- only checked `$_SERVER['HTTP_IF_NONE_MATCH']`
- required exact string equality with the generated ETag
- did not look at `REDIRECT_HTTP_IF_NONE_MATCH`
- did not fall back to `getallheaders()`
- did not normalize weak-vs-strong formatting or quoted variants

In the local Apache/PHP setup used by the smoke tests, the revalidation header path/format did not always match that exact-string-only check, so the 304 branch was skipped and the endpoint fell through to the normal `200` body response.

## Code Path Changed

Changed file:

- `leagues/team/index.php`

Changed function:

- `team_if_none_match_matches(string $etag): bool`

The function now follows the same matching pattern already used by working cacheable endpoints such as:

- `leagues/team/builder/index.php`
- `leagues/market/players/index.php`
- `leagues/players/index.php`

## How ETag Comparison Works Now

The `/team` endpoint now:

- reads `If-None-Match` from `HTTP_IF_NONE_MATCH`, `REDIRECT_HTTP_IF_NONE_MATCH`, and `If-None-Match`
- falls back to `getallheaders()` if needed
- supports `If-None-Match: *`
- normalizes weak ETag prefixes (`W/`)
- trims quote-only formatting differences
- compares comma-separated candidate values safely

This keeps the existing ETag value generation unchanged while making conditional GET detection align with the documented Category A contract.

## Regression Coverage Added

Updated existing smoke script:

- `scripts/transfer-confirm-smoke.ps1`

New assertion added after successful transfer confirm:

1. `GET /team` after confirm returns `200`
2. ETag changes
3. roster changes
4. immediate unchanged revalidation with `If-None-Match: <new-etag>` returns `304`

Existing unchanged revalidation coverage remained in:

- `scripts/team-smoke.ps1`

## Reproduction Before vs After

### Before

For both seeded users:

1. `GET /leagues/{league_id}/team`
2. capture returned `ETag`
3. repeat request with `If-None-Match: <etag>`
4. actual result: `200 OK`
5. expected result: `304 Not Modified`

### After

Guardrail seed `seed.user2@example.com`:

1. `GET /leagues/10/team` returned `200` with ETag
2. unchanged revalidation returned `304`

Happy-path seed `seed.user3@example.com`:

1. `GET /leagues/10/team` returned `200` with ETag
2. unchanged revalidation returned `304`
3. `POST /leagues/10/transfers/confirm` returned `200`
4. next `GET /leagues/10/team` returned `200` with a changed ETag and changed roster
5. next unchanged revalidation with the new ETag returned `304`

## Commands Run

Reset to deterministic seeded state:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/reset-db.ps1
```

Syntax check:

```powershell
php -l leagues/team/index.php
```

Guardrail unchanged revalidation:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/team-smoke.ps1 -Email seed.user2@example.com
```

Happy-path unchanged revalidation:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/team-smoke.ps1 -Email seed.user3@example.com
```

Happy-path post-transfer refresh + revalidation:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/transfer-confirm-smoke.ps1 -Email seed.user3@example.com
```

## Verification Results

Observed after the fix:

- `seed.user2@example.com`: `/team` unchanged revalidation returned `304`
- `seed.user3@example.com`: `/team` unchanged revalidation returned `304`
- `seed.user3@example.com`: transfer confirm returned `200`
- `seed.user3@example.com`: next `/team` fetch returned a changed ETag
- `seed.user3@example.com`: subsequent unchanged `/team` revalidation returned `304`

## Follow-Up Notes

This fix was intentionally kept endpoint-local because the task was narrowly scoped to `/leagues/{league_id}/team`.

There are other endpoint-local `If-None-Match` helpers in the repo, and some use simpler matching logic than the more robust helper shape. They are not changed here because broad cache-helper refactoring was out of scope for this task.
