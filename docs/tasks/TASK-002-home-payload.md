# TASK-002 — Implement GET /home payload (+ league_id) with Auth + ETag/304 (v1)

**Goal:** Implement the Phase B Home payload endpoint(s) exactly per:
- `docs/spec/api-schemas-updated.md` (Home section)
- `docs/spec/phase-b-api-contracts.md` (Home section)
- `docs/spec/phase-b-screens.md` (Home screen)
- `docs/spec/api-errors-updated.md` (auth + league errors)
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` (Category A + ETag scope)
- `docs/spec/core-rules-updated.md` (R3 gameweek lifecycle)

**Paths:**
- `GET /home`
- `GET /home?league_id={league_id}`

**Auth:** required (Bearer access token from TASK-001). If token missing/invalid → 401.

**Caching:** Category A
- Response: `Cache-Control: private, must-revalidate`
- Response: `ETag: W/"..."`
- Request: support `If-None-Match`
- If unchanged: return **304 Not Modified** with no body (still include `ETag` header).

---

## 0) Non-goals (keep scope tight)
- Do NOT implement other endpoints yet (/team, /fantasy, /notifications, etc.).
- Do NOT refactor the whole routing architecture.
- Do NOT change DB schema in this task (map existing columns to contract fields).

---

## 1) Response shape (must match schemas)

Envelope:
```json
{
  "meta": {
    "server_time": "ISO-8601 UTC",
    "league_id": 1|null,
    "current_gw": 12|null,
    "last_updated": "ISO-8601 UTC",
    "etag": "W/\"home-...\""
  },
  "data": {
    "league_selector": { "...": "..." },
    "league_context": { "...": "..." }|null,
    "notifications_preview": { "...": "..." },
    "news_preview": { "...": "..." },
    "highlights": null|{ "...": "..." }
  }
}
```

Notes:
- If `league_id` omitted: `meta.league_id` and `meta.current_gw` MAY be null; `data.league_context` MUST be null.
- `data.highlights` MAY be null (OK to return null for v1).

---

## 2) Data requirements & mapping

### 2.1 League selector list (always present)
Return:
- `selected_league_id`:
  - if query `league_id` present and accessible → that id
  - else `null`
- `leagues[]`: list of leagues visible in the system
  - include competitor snapshot if the user has a team in that league

**DB sources (typical):**
- `leagues` for list + name
  - watch out: legacy column might be ``leagues`.`league name`` (with a space). Use backticks and alias to `name`.
- `competitor` LEFT JOIN on (profile_id, league_id) for competitor snapshot (`competitor_id`, `teamname`)
- `gameweeks` for current gw + status per league:
  - determine current gw row per league (see §2.5)

`logo_url`:
- If the DB has no league logo field, return `""` (empty string) for now.

### 2.2 Notifications preview (always present)
Return:
- `unread_count`
- `items[]` latest 1–3 notifications:
  - `notification_id`, `type`, `title`, `created_at`

Use the `notification` table. If your DB stores title/message differently, map best-effort:
- type: use `notification_type` or equivalent
- title: use `title` if present, else a short derived label (do not break schema)

Unread definition:
- unread if `read_at IS NULL` (preferred), else fall back to legacy `mark_read = 0` if present.

### 2.3 News preview (always present)
Return:
- `mode`: `"global"` when no league_id, `"league"` when league_id provided
- `items[]` latest 1–3 news:
  - `news_id`, `title`, `published_on`, `image_url`

Use `news` table with `live=1`.
Language:
- if `profile.lang_id` exists, join `languages.short` and use that to filter `news.lang_id` (or your existing mapping).
- If uncertain, just filter by `news.lang_id = profile.lang_id`.

`published_on` must be ISO-8601; if the column is DATE, convert to `YYYY-MM-DDT00:00:00Z`.

### 2.4 League context (only when league_id is provided)
Return:
- `league_context.gameweek`: `gw`, `deadline`, `is_open`, `gamedate`
- `league_context.your_team`:
  - `competitor_id`, `teamname`
  - `rank`, `previous_rank`, `rank_change`
  - `total_points`, `weekly_points`

If the user has NO competitor in that league:
- `league_context` should still exist, but `your_team` can be null OR you may return `409 NO_COMPETITOR` if that is your chosen global approach.
- Choose one approach and keep it consistent (see Core Rules B2).
- For Home, recommended: keep `league_context` with `your_team=null` so the app can show CTA “Create your team”.

Ranking/points sources:
- `teamranking` for `rank` by (competitor_id, current_gw)
- previous rank from (competitor_id, current_gw - 1)
- weekly_points from `teamresult.weeklypoints` for current_gw
- total_points = SUM(teamresult.weeklypoints) up to current_gw

### 2.5 Current GW determination (R3)
Implement a helper:
- Prefer the highest GW row with `open=1` for that league
- If none open, use the max GW available
- `is_open` = (open==1) AND (now <= deadline)

Deadline mapping:
- If `gameweeks.deadline` is a DATE, interpret as end-of-day server time (`23:59:59`) for display and comparisons.
- Convert to ISO-8601 UTC string for the response.

---

## 3) ETag + last_updated rules

**Category A** requires ETag and 304.

### 3.1 Suggested ETag marker (good-enough)
For `GET /home` (no league):
- marker = max timestamp among:
  - max(notification.updated_at or created_at) for user
  - max(competitor.updated_at) for user
  - max(news.published_on) for user's language
  - max(gameweeks row for each league) (if no updated_at, incorporate max(gameweek) + open/deadline string hash)

For `GET /home?league_id=...`:
- include above AND include:
  - current_gw value for that league
  - max(teamranking/teamresult) for user+league (if those tables lack updated_at, incorporate max(gameweek)+sum points+rank values into hash)

**ETag format:**
- Weak ETag: `W/"home-u{profile_id}-{league_id or 0}-{current_gw or 0}-{marker}"`

`meta.last_updated` should be the max timestamp you used for the marker (ISO-8601 UTC). If you only have DATE, convert to midnight UTC.

### 3.2 304 handling
If request has `If-None-Match` matching the computed ETag:
- return 304
- no JSON body
- still include `ETag` and `Cache-Control`

---

## 4) Error behavior

- Missing/invalid access token → **401** with your standard error envelope:
  - `AUTH_REQUIRED` or `AUTH_INVALID_TOKEN` (use the code set you already implemented in TASK-001)
- If `league_id` is provided but not accessible/doesn’t exist:
  - use your spec’s league error (typically 403/404). Keep consistent with `api-errors-updated.md`.

---

## 5) Smoke tests (minimum)

Add a script similar to TASK-001 smoke tests:
1) login → capture access token
2) GET /home → 200 + Cache-Control + ETag header present
3) GET /home with `If-None-Match` from #2 → 304
4) GET /home?league_id={valid} → 200 and `league_context` present
5) GET /home?league_id={invalid} → correct 403/404
6) GET /home without token → 401

---

## 6) Deliverables / acceptance criteria

- Endpoint(s) implemented and reachable via `/home`
- Response JSON matches schema keys exactly
- ETag + 304 behavior works
- Uses existing JWT auth verification (no new token type)
- Minimal diffs; no refactors outside what is needed
- Smoke script committed
