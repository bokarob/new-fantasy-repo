API Versioning & Compatibility Policy (v1)
Goals
    Allow the backend to evolve without breaking installed mobile apps.
    Keep development simple: avoid duplicating logic, version only when necessary.
    Make client behavior predictable: “if it worked yesterday, it still works today” within a version.

Versioning Method
    The API uses URI (path) versioning:
        All endpoints are served under a version prefix: /v1/...
    The initial public contract is v1.

Compatibility Rules Within a Version (e.g., v1)
    Changes are classified as non-breaking vs breaking.

Non-breaking changes (allowed in v1 anytime)
No version bump required:
    Add new fields to responses (clients must ignore unknown fields).
    Add new endpoints.
    Add optional request fields (must have safe defaults if omitted).
    Add new error codes (as long as existing behavior remains valid).
    Fix bugs or improve performance without changing the meaning of existing fields.
    Return more items in a list (if pagination rules remain compatible).

Principle: existing fields must keep their meaning. 


Breaking changes (require a new major version, e.g., v2)
A new version is required if you do any of the following:
    Remove a field or stop sending it.
    Rename a field.
    Change a field’s type (number → string, etc.).
    Change a field’s meaning (e.g., rank_change becomes absolute instead of delta).
    Make an optional field required.
    Change an endpoint’s URL or HTTP method in a way clients must update.
    Change error semantics such that valid client flows would fail (e.g., an endpoint that used to return 200 + rules_check now returns hard errors for the same condition).

How New Versions Work (v2+)
    New versions are introduced in parallel:
        /v1/... continues to exist
        /v2/... is added for the new contract
    Internally, implementations should share business logic where possible:
        same rule engine / DB / services
        different “mappers” or response shaping per version when needed

Deprecation Policy (practical, minimal)
    When v2 launches, v1 is considered deprecated but supported for a defined window.
    Suggested default support window:
        at least one full season, or 6–12 months (choose what feels realistic for your cadence)
    Deprecation approach:
        1: Announce deprecation in release notes / admin message
        2: (Optional) add response header: Deprecation: true and/or Sunset: <date>
        3: After the window, v1 may be retired

Client Expectations
    Clients must:
        ignore unknown response fields
        handle new error codes using the existing error envelope
        treat the server as authoritative and not assume local validation is final
    Mobile clients should pin to a major version (e.g., v1) until the app updates to v2.

Recommended Convention
    Base URL examples:
        GET /v1/home
        GET /v1/leagues/{league_id}/team
        POST /v1/auth/login