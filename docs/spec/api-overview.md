# API Overview – Fantasy 9pin

## Purpose
This document describes the philosophy and high-level structure of the Fantasy 9pin API.
It complements the Core Rules Specification and defines how data and actions are exposed
to clients (web and mobile).

---

## Core Principles

- The API is the single source of truth.
- All game-critical rules are enforced server-side.
- Clients must not rely on local validation for correctness.
- Payload endpoints are preferred over many small endpoints.
- API responses are designed to support both web and mobile clients.

---

## Data Scoping

- Global data is independent of league context.
- User-scoped data applies across leagues.
- League-scoped data always requires a league_id.
- Gameweek-specific data is derived from the current gameweek unless explicitly overridden.

---

## Endpoint Types

The API exposes three main types of endpoints:

1. Screen payload endpoints  
   - Return all data required to render a main screen.
   - Example: /home, /leagues/{league_id}/team

2. Action endpoints  
   - Perform a single atomic action.
   - Example: confirm transfer, set captain

3. Lookup endpoints  
   - Support UI interactions such as search or filtering.
   - Example: market player list, invite autocomplete

---

## Versioning & Compatibility

- Backward-compatible changes are preferred.
- Breaking changes require a version bump.
- New fields may be added at any time.
- Existing fields must not change meaning.

---

## Caching & Performance

- Payload endpoints may return ETags.
- Clients may use conditional requests (If-None-Match).
- Server-side caching is allowed as long as rule correctness is preserved.

---

## Error Handling

- Errors are returned in a consistent format.
- Where applicable, errors reference the violated rule ID
  from the Core Rules Specification.

---

## Authentication

- All league-scoped endpoints require the user to participate in the league.
- Admin-only actions (private league management) require admin privileges.
- Authorization is always validated server-side.

## Naming Conventions

- Endpoint paths use nouns and hierarchy (e.g. /leagues/{league_id}/team).
- IDs are numeric unless stated otherwise.
- Monetary values are numbers with 1 decimal.
- Dates are ISO 8601 in UTC.
- Boolean flags use true/false.

## Idempotency

Action endpoints should be idempotent where possible.
Repeating the same request must not cause duplicate side effects.

## Pagination

List endpoints use offset/limit pagination unless stated otherwise.

