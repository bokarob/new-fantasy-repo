# Mobile Technical Architecture

Status: Draft
Owner: Róbert Bóka
Last updated: 2026-03-07

---

## 1. Purpose

This document defines the technical architecture of the Fantasy mobile application.

Its purpose is to capture the cross-cutting client-side decisions that are required before and during implementation, including:
- chosen mobile framework
- project structure
- networking layer shape
- auth/session handling
- local cache and revalidation strategy
- navigation structure
- league-context handling
- error/offline behavior
- implementation order

This document does **not** redefine backend contracts.

The backend/API specification documents remain the source of truth for:
- endpoint definitions
- request/response schemas
- error codes and HTTP conventions
- caching categories and refresh rules
- gameplay and league rules

This document describes how the mobile app consumes and applies those backend specifications in a Flutter client.

---

## 2. Source-of-truth map

This document does not replace backend/API specification files.

### 2.1 Authoritative backend/spec files

The following files are authoritative for backend behavior and mobile integration rules:

- `docs/spec/api-overview.md`
  - API design principles, endpoint types, envelope conventions, and general client expectations

- `docs/spec/auth-model.md`
  - authentication model, access token / refresh token behavior, OTP flow, and auth lifecycle rules

- `docs/spec/phase-b-screens.md`
- `docs/spec/phase-c-screens.md`
  - mobile screen inventory, flows, and screen-level functional expectations

- `docs/spec/phase-b-api-contracts.md`
- `docs/spec/phase-c-api-contracts.md`
  - endpoint-level contracts, actions, payload expectations, and screen integration details

- `docs/spec/api-schemas-updated.md`
  - response schemas, shared objects, and reusable payload shapes

- `docs/spec/api-errors-updated.md`
  - error catalog, error codes, and error response structure

- `docs/spec/caching-updated.md`
  - cache categories, ETag behavior, conditional requests, and revalidation rules

- `docs/spec/endpoint-matrix-updated.md`
  - screen-to-endpoint mapping, cache category usage, and write-to-refresh relationships

- `docs/spec/core-rules-updated.md`
  - gameplay rules, league behavior, ranking logic, and server-authoritative rule decisions

### 2.2 Companion/reference files

The following files are reference/orientation documents, but are not authoritative over the spec files above:

- `docs/mobile-integration-index.md`
  - mobile start-here guide and implementation order summary


### 2.3 Conflict rule

If this document conflicts with an authoritative backend/spec file, the backend/spec file wins.

If a companion or planning file conflicts with an authoritative backend/spec file, the authoritative backend/spec file wins.

If implementation code conflicts with an authoritative backend/spec file, the spec must be reviewed and either:
- the implementation corrected, or
- the spec explicitly updated and re-approved.

---

## 3. Recorded architecture decisions

This section records the baseline architecture decisions for the mobile application.
These decisions are assumed by the later sections of this document unless explicitly revised.

### AD-001 Framework

**Decision:** The mobile app will be implemented in Flutter.

**Rationale:**
- Flutter provides strong UI consistency across iOS and Android.
- The project does not need to align with an existing React-based mobile/web UI codebase.
- The backend/API contracts are already defined, so the mobile client primarily needs a clean consumer architecture rather than frontend code reuse.
- A unified cross-platform visual and interaction model is preferred for this product.

**Consequences:**
- The mobile app uses the Flutter + Dart toolchain.
- Shared UI is implemented in Flutter rather than through divergent native platform components.
- Mobile architecture should prioritize clear state, data, and navigation boundaries over web-stack alignment.

### AD-002 Backend is authoritative

**Decision:** The backend API is the single source of truth for gameplay, validation, and persisted state.

**Rationale:**
- Backend contracts, rules, and error behavior are already defined and documented.
- The app must remain consistent with server-authoritative game logic.
- Client-side validation is helpful for UX, but must never be treated as final authority.

**Consequences:**
- Local validation is UX-only.
- If server state and local assumptions differ, the server response wins.
- After successful writes, affected read models must be revalidated according to backend refresh rules.
- The client must not invent or persist client-authoritative game state.

### AD-003 Auth/session model

**Decision:** The mobile app follows the documented access-token / refresh-token auth model.

**Rationale:**
- The backend auth lifecycle is already specified.
- Mobile session restoration must be reliable without weakening security.
- The refresh flow should be centralized and consistent across all authenticated requests.

**Consequences:**
- Access tokens are attached to authenticated API requests.
- Refresh tokens are stored only in secure device storage.
- Token refresh is handled centrally by the networking/auth layer.
- Refresh failure results in session invalidation and return to logged-out state.

### AD-004 Cache strategy baseline

**Decision:** Category A endpoints use local response caching with ETag-based revalidation.

**Rationale:**
- The backend cache model is already defined.
- Mobile UX benefits from fast reopen, pull-to-refresh, and limited offline read capability.
- The app should reuse server-supported conditional requests instead of inventing custom freshness logic.

**Consequences:**
- The client stores cache entries for supported endpoints together with their cache keys and ETags.
- Revalidation uses conditional requests where defined by backend rules.
- Cached data improves responsiveness, but does not become authoritative truth.
- Writes do not directly mutate cached truth unless explicitly defined; they trigger revalidation of affected reads.

### AD-005 League context model

**Decision:** The app maintains one active league context at a time for league-scoped surfaces.

**Rationale:**
- Many screens and endpoints are league-scoped.
- A single active context simplifies navigation, state ownership, cache keying, and refresh behavior.
- Multi-league support is required, but simultaneous mixed-context rendering should be avoided.

**Consequences:**
- League-scoped screens read from the active league context.
- Cache keys for league-scoped endpoints include league identity.
- Switching league updates dependent state and triggers refresh/revalidation where required.
- Deep links and notification targets may change the active league context when valid.

### AD-006 Online-first mobile behavior

**Decision:** Version 1 of the mobile app is online-first, with cached read support but no offline-authoritative write behavior.

**Rationale:**
- The backend is authoritative.
- The first mobile version should optimize for correctness and predictable sync behavior.
- Full offline write support would add complexity without being necessary for the initial product.

**Consequences:**
- Cached reads may be shown when appropriate.
- Write actions require network availability.
- The app must not pretend that an offline write succeeded.
- Conflict resolution is handled by server response and follow-up revalidation, not by client merge logic.

---

## 4. Scope and non-goals

### In scope
- authenticated mobile app for iOS + Android
- integration with implemented API endpoints
- online-first UX with cached reads for Category A endpoints
- deep links inside the app
- league-scoped navigation

### Out of scope for v1
- full offline write support
- client-authoritative data mutation
- background sync beyond simple refresh rules
- push notification implementation details (can be added later)
- complex tablet/desktop layout work

---

## 5. App architecture overview

### 5.1 Purpose

This section gives a high-level view of how the mobile app is structured end to end.

It explains:
- the main architectural layers
- how data flows through the app
- where state is owned
- how networking, cache, auth, and navigation fit together

This section is intentionally high level.
Detailed rules for networking, auth, cache, navigation, and state are defined in their dedicated sections.

### 5.2 Architecture summary

The mobile app uses a layered, feature-first architecture built around these principles:

- backend remains the authoritative source of truth
- UI does not call HTTP directly
- repositories coordinate API access, cache, and revalidation behavior
- app state is separated into session, league context, feature data, mutation state, and transient UI state
- navigation is centralized through the authenticated/unauthenticated app shell
- local cache improves responsiveness, but does not become authoritative truth

The app is online-first, with cached read support for selected endpoints and centralized session/auth handling.

### 5.3 High-level layers

The app is organized into the following layers:

#### A. Presentation layer

Responsible for:
- screens
- widgets
- route entry points
- loading / empty / error / ready rendering
- user interactions

This layer:
- reads state from controllers/providers
- sends user actions to controllers/providers
- does not perform raw API calls
- does not own backend rules

Examples:
- Home screen
- Team screen
- Notifications list
- Match detail screen
- Profile screen

#### B. Application/state layer

Responsible for:
- app-level state ownership
- feature state ownership
- mutation/action state
- derived state
- orchestration between UI and repositories

This layer contains:
- session state
- league context state
- feature controllers/providers
- route-aware coordination where needed

This layer:
- knows which data should be loaded
- knows when revalidation should happen
- does not perform low-level HTTP directly
- does not render UI

Examples:
- session controller
- league context controller
- home state provider
- team controller
- notifications controller

#### C. Data layer

Responsible for:
- repository contracts and implementations
- feature API services
- request building
- response parsing
- DTO mapping
- cache-aware reads
- write-triggered revalidation

This layer:
- talks to the shared network client
- talks to cache/storage helpers
- returns typed results/models to the application layer
- centralizes backend-facing behavior

Examples:
- team repository
- home repository
- notifications repository
- private leagues repository

#### D. Core infrastructure layer

Responsible for:
- HTTP client configuration
- auth token injection
- refresh flow
- error normalization
- local cache persistence
- secure storage access
- environment/config handling
- routing bootstrap

This layer provides reusable technical building blocks to the rest of the app.

Examples:
- Dio API client
- auth coordinator
- secure token store
- cache store
- environment config
- router setup

### 5.4 Data flow overview

The standard read flow is:

1. route is entered
2. required auth / league context is resolved
3. screen/controller requests data from repository
4. repository reads cached payload if applicable
5. repository performs network revalidation if required
6. repository returns effective result
7. controller updates state
8. UI renders loading / ready / refreshing / empty / error state

The standard write flow is:

1. user triggers action in UI
2. controller starts mutation state
3. repository sends write request
4. backend confirms success or returns failure
5. on success, repository/controller revalidates affected reads
6. UI updates based on the refreshed authoritative state

### 5.5 Architectural diagram

A simple mental model:


UI / Screens / Widgets
        ↓
Controllers / Providers / App State
        ↓
Repositories
        ↓
Feature API Services + Cache Access
        ↓
Core Network / Auth / Storage Infrastructure
        ↓
Backend API + Local Persistent Cache


More explicitly:


Presentation
  → Application / State
    → Repository
      → API client / cache / secure storage
        → Backend


### 5.6 App-wide shared state domains

The main shared state domains are:

- **Session state**
  - authenticated vs logged out
  - restore/refresh/logout lifecycle

- **League context**
  - currently active league
  - restore/switch/clear behavior

- **Feature state**
  - screen-level effective data
  - loading/refresh/error/empty/ready lifecycle

- **Mutation state**
  - short-lived action state for writes

- **Transient UI state**
  - local widget/screen state such as filters, tab selection, form inputs, modal visibility

These domains must remain clearly separated so that:
- auth invalidation clears the correct data
- league changes reload the correct scopes
- cached data does not get confused with live state
- UI state does not become hidden business truth

### 5.7 State ownership model

State is owned at the lowest level that is still safely shared.

Rules:
- app-global concerns stay in app-level controllers/providers
- feature data stays in feature-level controllers/providers
- transient UI state stays local where possible
- repositories own data access logic, not UI state
- persistent cache stores snapshots, not live widget state

Important examples:
- session state is app-level
- active league is app-level
- Team payload state is feature-level and keyed by league
- current sort dropdown selection is usually local UI state
- cached `/team` response is persisted data, not direct screen state

### 5.8 Context and routing integration

Navigation and context resolution are part of the architecture, not an afterthought.

Before loading a screen, the app resolves:
- whether the route requires authentication
- whether the route requires an active league
- whether a deep link or notification target should change the active league
- whether the target entity is still valid

This keeps routing, auth, and league context aligned and prevents screens from implementing their own ad hoc context logic.

### 5.9 Cache and backend-authoritative behavior

The app uses local cache to improve responsiveness, but the backend remains authoritative.

Implications:
- cached payloads may be shown while revalidating
- ETag-based revalidation is preferred over client-side freshness assumptions
- writes do not become authoritative until confirmed by the server
- when local assumptions conflict with backend state, backend state wins
- stale or invalid scoped cache must not remain visible under another user or league context

### 5.10 Feature-first implementation model

The codebase is organized feature-first, but the runtime behavior still follows the same cross-cutting architecture.

Each feature typically contains:
- presentation
- application/state
- data

Shared technical concerns remain centralized in app/core layers.

This allows:
- milestone-by-milestone implementation
- feature-local ownership of screens and repositories
- centralized handling of auth, cache, routing, and environment concerns

### 5.11 Concurrency and lifecycle expectations

The architecture must protect against common mobile/runtime issues.

Key expectations:
- refresh token handling is centralized
- concurrent `401` responses do not trigger parallel refresh storms
- stale responses from an old league/scope must not overwrite newer state
- app restart does not assume authenticated trust until restore succeeds
- logout/profile deletion clear local authenticated state promptly

### 5.12 Non-goals

This architecture overview does not define:
- detailed visual design
- exact widget tree structure per screen
- backend implementation details
- final CI/CD or signing setup
- advanced offline sync or offline write queueing

---

## 6. Chosen stack

### 6.1 Framework

- **Flutter**
- **Language:** Dart

This project will be implemented as a Flutter application for iOS and Android.

### 6.2 Supporting library choices

#### State management + dependency injection
- **flutter_riverpod**

**Why chosen:**
- good fit for async-heavy, API-driven screens
- works well for session state, league context, cached payload state, and derived UI state
- keeps business/data logic outside widget trees
- can also serve as lightweight dependency injection for repositories and services

#### Routing / navigation
- **go_router**

**Why chosen:**
- good fit for authenticated/unauthenticated shells
- supports deep links and nested navigation
- suitable for league-scoped routes and modal/detail flows
- aligns well with Flutter Router-based navigation

#### Networking / HTTP client
- **dio**

**Why chosen:**
- strong interceptor support for auth header injection, refresh handling, logging, and ETag behavior
- suitable for a centralized API client layer
- better fit than a minimal HTTP client for this project’s retry/revalidation requirements

#### Secure storage
- **flutter_secure_storage**

**Why chosen:**
- appropriate place for refresh-token storage
- maps to secure platform storage on iOS and Android
- aligns with the documented auth model

#### Local persistence / cache storage
- **hive_ce** + **hive_ce_flutter**

**Why chosen:**
- good fit for v1 local cache needs: cache key -> response body + ETag + metadata
- simpler than introducing a relational/offline-first database for the first version
- suitable for storing cached Category A payloads and lightweight local app metadata

**Not chosen for v1:**
- a full relational local database is not required yet
- if future requirements expand into complex offline sync/query behavior, this choice can be revisited

#### JSON/data models
- **freezed**
- **json_serializable**

**Why chosen:**
- explicit typed models for API payloads
- safer immutable state handling
- cleaner parsing and serialization for shared response envelopes and feature DTOs

### 6.3 Architectural style around the stack

The app should use a feature-first structure with clear layering:

- `presentation`
- `application` / `state`
- `data`

Recommended flow:

- screens/widgets depend on view models / controllers / providers
- providers depend on repositories and services
- repositories coordinate API client + cache storage
- API DTOs remain separate from UI-specific state where useful

### 6.4 Immediate consequences for implementation

These choices imply:

- auth refresh logic is implemented centrally in the Dio layer
- league context is exposed as app-level state via Riverpod
- route guards and deep-link resolution are implemented in go_router
- Category A cache entries are persisted locally through Hive CE
- refresh token is stored only in secure storage
- typed DTO/model generation is part of normal development flow

### 6.5 Deferred choices

The following can stay open until later:

- crash reporting tool
- analytics tool
- push notification SDK wiring
- image caching package
- exact logging package choice

---

## 7. Project structure

### 7.1 Goals

The project structure should:

- keep feature code easy to find
- separate shared infrastructure from feature-specific code
- support league-scoped and user-scoped screens cleanly
- keep networking, cache, auth, and routing centralized
- avoid a giant “common” folder with unclear ownership
- make vertical-slice implementation easy milestone by milestone

The structure should optimize for maintainability and implementation clarity, not for theoretical purity.

### 7.2 Recommended top-level structure

Use a **feature-first structure** with a small shared app/core layer.

Recommended layout:

```text
lib/
  app/
  core/
  shared/
  features/
```

Meaning:

- `app/`
  - application bootstrap and root wiring

- `core/`
  - low-level infrastructure and technical primitives

- `shared/`
  - reusable UI/domain helpers that are not owned by one feature

- `features/`
  - all product features, grouped by product area

### 7.3 Top-level folder responsibilities

#### `app/`

Owns:
- app bootstrap
- environment initialization
- root providers
- root router
- theme
- app shell
- app-level dependency registration

Suggested contents:


lib/app/
  app.dart
  bootstrap.dart
  router/
  theme/
  init/


#### `core/`

Owns:
- networking infrastructure
- auth/session infrastructure
- cache infrastructure
- storage adapters
- error/failure base types
- config/environment models
- logging/utilities that are truly cross-app

Suggested contents:


lib/core/
  config/
  network/
  auth/
  cache/
  storage/
  errors/
  utils/


Important rule: `core/` should contain technical building blocks, not feature business logic.

#### `shared/`

Owns:
- reusable widgets
- common presentation components
- app-wide simple models that do not belong to one feature
- lightweight UI helpers/constants

Suggested contents:

lib/shared/
  widgets/
  models/
  formatting/
  extensions/


Important rule: `shared/` is not a fallback dump folder. If something clearly belongs to one feature, keep it inside that feature.

#### `features/`

Owns:
- all product functionality
- feature-specific state/controllers
- repositories
- DTOs/models owned by that feature
- screens/routes owned by that feature

Suggested contents:


lib/features/
  auth/
  home/
  team/
  transfers/
  rankings/
  matches/
  notifications/
  account/
  private_leagues/
  rules/


### 7.4 Recommended structure inside each feature

Each feature should use a consistent internal split.

Recommended pattern:


lib/features/<feature_name>/
  data/
  application/
  presentation/


#### `data/`

Owns:
- API services for that feature
- DTOs / response models
- repository implementations
- cache-key helpers if feature-specific
- mappers between DTOs and app/domain models

#### `application/`

Owns:
- Riverpod providers/controllers/notifiers
- feature state models
- mutation state
- derived state
- use-case-style orchestration if needed

#### `presentation/`

Owns:
- screens/pages
- widgets owned by the feature
- local UI helpers owned by the feature
- route entry widgets

### 7.5 Example feature layouts

#### Example: `team`


lib/features/team/
  data/
    team_api.dart
    team_repository_impl.dart
    team_dto.dart
    team_builder_dto.dart
  application/
    team_controller.dart
    team_state.dart
    team_builder_controller.dart
    captain_action_controller.dart
  presentation/
    screens/
      team_screen.dart
      team_builder_screen.dart
    widgets/
      roster_grid.dart
      roster_list.dart
      captain_picker.dart


#### Example: `notifications`


lib/features/notifications/
  data/
    notifications_api.dart
    notifications_repository_impl.dart
    notification_dto.dart
  application/
    notifications_controller.dart
    notifications_state.dart
    mark_read_controller.dart
  presentation/
    screens/
      notifications_screen.dart
    widgets/
      notification_list_item.dart
      notifications_filter_bar.dart


### 7.6 Shared app-level modules

Some concerns are shared across many features and should not live inside a single feature.

#### Session

Owns:
- session controller/provider
- auth bootstrap
- logout/session invalidation orchestration

Suggested location:


lib/core/auth/

or

lib/app/session/


#### League context

Owns:
- active league provider/controller
- restore/switch/clear logic
- deep-link target reconciliation

Suggested location:


lib/app/league_context/


Reason: league context is app state, not one feature’s sub-state.

#### Routing

Owns:
- go_router setup
- route guards
- deep-link resolution entry
- authenticated shell

Suggested location:


lib/app/router/


### 7.7 Repository placement rule

Repositories should usually be owned by the feature that consumes them.

Examples:
- Home repository → `features/home`
- Team repository → `features/team`
- Rankings repository → `features/rankings`
- Notifications repository → `features/notifications`

Exceptions:
- auth/session token infrastructure → shared app/core layer
- very small shared lookup/helper services only if genuinely cross-feature

This prevents one giant `repositories/` folder becoming a second monolith.

### 7.8 API service placement rule

Do not create one giant all-endpoints API class.

Instead:
- one shared low-level API client in `core/network`
- feature-focused API service classes inside each feature

Example:
- `core/network/api_client.dart`
- `features/team/data/team_api.dart`
- `features/private_leagues/data/private_leagues_api.dart`

### 7.9 Model placement rule

Use model ownership based on responsibility.

#### DTOs

Keep near the feature API layer.

Location:


features/<feature>/data/


#### App/domain models

Keep in:
- the feature, if used mainly by that feature
- `shared/models`, only if reused across multiple features in a stable way

Examples:
- `NotificationItem` may live in the notifications feature
- a small reusable `PagedResult<T>` helper may live in shared/core

Avoid premature “global domain model” extraction.

### 7.10 Widget placement rule

Use this rule:

- feature-owned widget → inside feature
- app-wide reusable widget → `shared/widgets`
- shell/navigation widget → `app/`

Examples:
- `RosterGrid` → `features/team/presentation/widgets`
- `UnreadBadge`
  - feature-local if only used in notifications/home context
  - shared only if genuinely reused broadly
- `AppScaffoldShell` → `app/`

### 7.11 Test structure

Tests should broadly mirror the code structure.

Recommended layout:


test/
  app/
  core/
  features/
    auth/
    home/
    team/
    transfers/
    rankings/
    matches/
    notifications/
    account/
    private_leagues/


Priorities:
- repository tests
- auth/session restore tests
- league-context tests
- cache key / cache revalidation tests
- route guard/deep-link tests
- key screen/controller tests

### 7.12 Naming conventions

Recommended conventions:

- folders: `snake_case`
- files: `snake_case.dart`
- providers/controllers/state classes: clear feature-prefixed names where helpful
- avoid generic names like:
  - `service.dart`
  - `model.dart`
  - `helper.dart`

Prefer:
- `team_repository.dart`
- `league_context_controller.dart`
- `notifications_state.dart`

### 7.13 Allowed exceptions

The structure should be consistent, but not rigid to the point of absurdity.

Allowed exceptions:
- very small features may collapse files until complexity grows
- a tiny feature may not need all `data/application/presentation` subfolders on day one
- later refactoring may extract cross-feature modules once real reuse is proven

Do not over-engineer the initial scaffold.

### 7.14 Non-goals

This structure does not require:
- a fully separate package per feature
- one giant shared domain layer
- code generation for everything
- strict clean-architecture ceremony in every file

---

## 8. Environment and configuration

### 8.1 Goals

Environment/configuration setup must support:

- simple local development against the current backend
- clean separation of local, staging, and production API targets
- safe handling of secrets and tokens
- predictable app behavior across builds
- minimal duplication of server-owned configuration

The mobile app should treat environment config as infrastructure/runtime setup, not as a place to store gameplay truth.

### 8.2 Supported environments

The app should support at least these environments:

- **local**
  - developer machine / emulator / physical device against local backend

- **staging** (optional initially, recommended later)
  - pre-release testing against a stable hosted backend

- **production**
  - live user-facing backend

The exact number of environments may evolve, but the architecture should assume at least local + production from the start.

### 8.3 Environment-specific values

The following values may vary by environment:

- API base URL
- API version prefix (if needed by config later, currently not versioned)
- app display suffix for non-production builds (optional)
- logging verbosity
- debug/diagnostic features
- developer-only toggles
- crash/analytics enablement

Examples:
- local:
  - local backend URL
  - verbose logging enabled
  - developer diagnostics enabled
- staging:
  - staging API URL
  - moderate logging
  - analytics optional / limited
- production:
  - production API URL
  - restricted logging
  - no developer diagnostics

### 8.4 Values that must not be hardcoded as app config truth

The following should not be treated as client-owned configuration if they already come from the backend:

- roster size
- starters/substitutes count
- transfer limits
- initial budget
- season locked state
- OTP cooldown/retry policy if delivered by API/config
- other gameplay or rules values that are already exposed by `/leagues/{league_id}/rules` or `/config`

Reason:
- these are backend-authoritative values and must remain consistent with server behavior

The app may use them for display and UX guidance after fetching them from the API.

### 8.5 Config ownership model

Configuration should be split into three groups:

#### A. Build/runtime environment config
Owned by the app build/runtime setup.

Examples:
- API base URL
- environment name
- whether logging is verbose
- whether developer tools are enabled

#### B. Secure runtime secrets
Not bundled as normal app config.

Examples:
- tokens generated during auth
- any future third-party secrets that should not live in source config

Rules:
- auth tokens are never hardcoded
- refresh token is stored only in secure storage
- access token lives in memory only

#### C. Server-authored display/business config
Fetched from backend payloads.

Examples:
- league rules
- global gameplay constants
- supported language list if later delivered by API
- any future app-usable config exposed by `/config`

### 8.6 Recommended Flutter configuration approach

Use a simple compile-time environment selection approach.

Recommended pattern:
- one app codebase
- environment selected by build flavor / Dart define
- environment object created at app startup

Suggested fields in app environment config:
- `environmentName`
- `apiBaseUrl`
- optional `apiPathPrefix`
- `enableVerboseLogging`
- `enableDevTools`
- `enableCrashReporting`
- `enableAnalytics`

Examples:
- local now: `https://example.test/api`
- staging: `https://staging.example.com/api`
- if versioning is introduced later, it should be added centrally through the configured API root/path, not hardcoded in feature code

Avoid spreading environment conditionals throughout feature code.
Feature code should depend on injected configuration/services, not raw string constants.

### 8.7 Local development expectations

The local/XAMPP is only the current development host, not a required backend shape for mobile integration.
The local environment should support the current backend workflow.

Expected assumptions:
- backend may run locally via XAMPP / Apache / MySQL
- auth flow may use local development defaults such as fixed OTP where configured in backend local setup
- local mobile builds should be able to point to the developer’s local API target cleanly

Important practical note:
- emulator/simulator/device connectivity to local backend must be handled through environment-specific base URL selection, not ad hoc code edits

This section does not prescribe the exact local hostnames/IPs yet; that can be added in implementation notes.

### 8.8 API path/version handling

The mobile app must not hardcode API versioning assumptions in feature code.

Current state:
- the API is currently unversioned

Rules:
- all requests use the configured API base URL/path for the target environment
- if versioning is introduced later (for example `/v1`), it must be applied centrally through environment/configuration, not by changing feature code across the app
- the mobile client should remain tolerant of additive response fields as defined by backend contracts

### 8.9 Logging and diagnostics by environment

Recommended defaults:

#### Local
- verbose request/response logging allowed
- developer diagnostics may be enabled
- cache inspection/reset tools may be available in debug builds only

#### Staging
- reduced logging
- developer diagnostics optional
- no sensitive token logging

#### Production
- minimal safe logging only
- no raw payload/token logging
- diagnostics hidden from normal users

Across all environments:
- never log access tokens
- never log refresh tokens
- never include secrets in contact/support context payloads

### 8.10 Feature flags and toggles

Version 1 should keep feature flags minimal.

Allowed examples:
- enable/disable debug tools
- enable staging-only diagnostics
- temporary rollout guard for a not-yet-finished mobile feature

Not recommended:
- duplicating server business-rule switches in client-side flags
- hiding contract differences behind client-only environment flags

If a feature materially changes API behavior, the backend/spec should define it first.

### 8.11 Build flavors / targets

The app should support distinct build targets for at least:

- debug local
- debug/staging
- release production

Recommended goals:
- clear app identity during testing
- minimal risk of accidentally shipping a local/staging target as production
- simple CI/CD expansion later

Platform-specific signing/certificate details belong in the build/release section, not here.

### 8.12 App version exposure

The app should expose its version/build number in a user-visible place such as Settings/About.

The app version may also be included in safe support/contact context payloads where useful.

Rules:
- version/build metadata is safe to expose
- secrets/tokens are not
- diagnostics included in support payloads must remain opt-in and sanitized

### 8.13 Reset and recovery behavior

For development and troubleshooting, the app may support safe local reset actions in debug builds only, such as:

- clear cached payloads
- clear active league context
- clear local session state and force re-login

These actions must not:
- bypass backend auth
- alter server data
- exist as hidden production behavior for normal users

### 8.14 Non-goals

This section does not define:
- CI/CD pipeline details
- signing/certificates/provisioning
- store release process
- analytics vendor selection
- crash-reporting vendor selection
- exact local network addressing instructions per emulator/device

Those belong to later implementation or release documentation.

---

## 9. Networking layer

### 9.1 Goals

The networking layer must provide:

- typed and centralized API access
- consistent parsing of success and error envelopes
- centralized authentication header handling
- centralized token refresh handling
- ETag-aware conditional revalidation for cacheable reads
- consistent mapping of backend errors into app-level error types
- clear separation between raw HTTP concerns and feature repositories

The networking layer must not allow screens or widgets to call HTTP directly.

### 9.2 High-level shape

The networking layer is composed of the following parts:

- **Dio client**
  - single shared HTTP client instance with base configuration

- **Auth coordinator**
  - owns current access token in memory
  - coordinates refresh flow
  - updates/clears session state

- **Secure token store**
  - persists refresh token only
  - does not expose storage concerns to feature code

- **API service layer**
  - endpoint-focused request methods
  - parses envelopes
  - converts raw responses into typed DTOs

- **ETag/cache metadata helper**
  - provides stored ETag for eligible reads
  - stores returned ETag after successful responses
  - resolves `304 Not Modified` against local cached payloads

- **Repositories**
  - feature-facing data access layer
  - combine API service + cache + app rules
  - expose clean methods to the rest of the app

### 9.3 Layer responsibilities

#### Dio client
Responsible for:
- base URL
- timeouts
- default headers
- request/response interceptor chain
- low-level transport error capture

Not responsible for:
- business rules
- UI messaging
- screen refresh policy decisions

#### API service layer
Responsible for:
- calling concrete endpoints
- serializing query/body params
- parsing response envelopes
- mapping JSON into DTOs
- throwing normalized API exceptions

Not responsible for:
- cache persistence policy
- league-context ownership
- UI state updates

#### Repositories
Responsible for:
- selecting cache vs network flow
- revalidation decisions for supported reads
- mapping DTOs into domain/app models where needed
- triggering follow-up revalidation after writes
- exposing a clean contract to providers/controllers

Repositories are the main source of truth for app data access.

### 9.4 Request metadata model

Each request should be described with explicit metadata so the networking layer behaves predictably.

Recommended request metadata fields:

- `authRequired`
- `cachePolicy`
- `cacheKey`
- `allowAuthRetry`
- `leagueScope`
- `logSafeName`

Example intent:
- public auth endpoints → `authRequired=false`
- authenticated reads → `authRequired=true`
- Category A reads → `cachePolicy=etagRevalidate`
- writes → `cachePolicy=networkOnly`

This metadata should travel with the request through the API service / repository boundary rather than being inferred ad hoc in interceptors.

### 9.5 Interceptor chain

The Dio client should use a small, predictable interceptor chain.

#### 1. Auth header interceptor
Behavior:
- if `authRequired=true` and access token exists, attach `Authorization: Bearer <token>`
- if request is public, do not attach auth header
- do not read refresh token here

#### 2. ETag request interceptor
Behavior:
- for requests using `cachePolicy=etagRevalidate`, look up stored ETag by `cacheKey`
- if an ETag exists, attach `If-None-Match`
- do nothing for writes or non-cacheable reads

#### 3. Logging / diagnostics interceptor
Behavior:
- log request and response metadata in debug environments
- never log tokens or sensitive user data
- prefer endpoint aliases / safe labels where possible

#### 4. Auth refresh interceptor
Behavior:
- handle `401` responses caused by expired/invalid access token
- invoke centralized refresh flow
- retry the original request once if refresh succeeds
- if refresh fails, clear session and propagate auth-expired state

Refresh logic must be serialized so concurrent `401` responses do not trigger multiple refresh calls in parallel.

#### 5. Error normalization interceptor
Behavior:
- normalize transport and backend failures into app-level exception types
- preserve backend error code and HTTP status where available
- keep `304 Not Modified` out of generic error handling

### 9.6 Response handling rules

#### Success envelope
The client should expect the standard success envelope and parse:
- `meta`
- `data`

The networking layer should preserve `meta` when useful for:
- server time
- league context
- current GW
- last-updated markers
- ETag-related metadata if present

#### Error envelope
The client should normalize backend error responses into a shared exception shape containing at least:
- HTTP status
- backend error code
- backend message
- optional rule/details payload

This avoids feature code parsing raw JSON errors repeatedly.

#### 304 Not Modified
`304 Not Modified` is not an error.

For requests using `cachePolicy=etagRevalidate`:
- if a cached body exists for the same `cacheKey`, return that cached body as the effective result
- if no cached body exists, treat this as an unexpected cache inconsistency and fall back to a normal error path or forced reload policy

### 9.7 Auth refresh flow

The networking layer must implement the documented refresh-on-401 pattern.

Rules:
- only authenticated requests may trigger refresh
- the original request is retried at most once
- refresh uses the refresh token from secure storage
- successful refresh updates in-memory access token and stored refresh token if rotated
- failed refresh clears session and transitions app state to logged out / re-auth required

The refresh endpoint itself must never recursively trigger another refresh attempt.

### 9.8 Cache-aware read strategy

For Category A endpoints, repositories should follow this baseline pattern:

1. read cached payload by `cacheKey` if present
2. optionally show cached payload immediately in UI
3. perform network request with ETag revalidation
4. if server returns `200`, replace cached body + ETag
5. if server returns `304`, keep cached body
6. surface the resulting effective payload to the caller

This gives:
- fast reopen behavior
- pull-to-refresh support
- limited offline read utility
- server-authoritative freshness

### 9.9 Write strategy

Writes are network-only.

Rules:
- no write should be considered successful until confirmed by the server
- successful writes do not directly become authoritative cached truth unless explicitly designed that way
- after successful writes, repositories must trigger targeted revalidation of affected read models according to the backend refresh rules
- if a write fails due to stale state or rule conflict, repositories should surface the error and revalidate affected payloads where appropriate

### 9.10 Repository contract style

Repositories should expose feature-friendly methods such as:

- `loadHome(...)`
- `refreshHome(...)`
- `loadTeam(leagueId, ...)`
- `getTransferQuote(...)`
- `confirmTransfer(...)`

Repositories should not expose raw HTTP response objects to presentation code.

Preferred repository outputs:
- typed result objects
- typed domain/app models
- normalized exceptions/failure results

Feature code should never handle:
- `If-None-Match`
- bearer token injection
- refresh retry logic
- raw envelope parsing

### 9.11 Error taxonomy used by the app

The networking/data layer should map failures into a small shared taxonomy, for example:

- `NetworkFailure`
- `TimeoutFailure`
- `AuthFailure`
- `ValidationFailure`
- `RuleConflictFailure`
- `ForbiddenFailure`
- `NotFoundFailure`
- `RateLimitFailure`
- `ServerFailure`
- `UnknownFailure`

Each failure should preserve backend code/message when available.

### 9.12 Special-case endpoint behavior

The networking layer must support contract-level special cases without forcing every feature to re-implement them.

Examples:
- endpoints that use standard envelopes but have special semantics in `data`
- quote/check endpoints that may return HTTP success with validity flags instead of hard errors
- cacheable league-scoped reads whose cache keys vary by league, GW, paging, or query filters

These cases should be handled in API services and repositories, not in widget code.

### 9.13 Concurrency and cancellation

The client should support:
- request cancellation when screens are disposed or superseded
- serialized token refresh
- protection against stale late-arriving responses overwriting newer screen state

Repositories/providers should prefer latest-request-wins behavior for user-driven refresh/search/filter interactions.

### 9.14 Non-goals of the networking layer

The networking layer should not:
- own navigation decisions directly
- show toasts/snackbars directly
- store UI-specific state
- contain gameplay rule logic
- silently swallow backend contract violations

---

## 10. Auth and session handling

### 10.1 Auth model baseline

The mobile app follows the documented token-based auth model:

- JWT access token
- opaque refresh token

Rules:
- access token is sent as `Authorization: Bearer <access_token>`
- refresh token is used only for `/auth/token/refresh`
- access token is treated as short-lived
- refresh token is treated as durable session-restoration credential

The mobile client does not persist server-side session state beyond the token model and minimal local session metadata.

### 10.2 Token storage policy

Token storage must follow these rules:

- **access token**
  - stored in memory only during the active app session
  - may be replaced after refresh
  - must be cleared on logout or session invalidation

- **refresh token**
  - stored only in secure device storage
  - never stored in normal shared preferences / plain local cache
  - updated if the backend rotates refresh tokens on refresh
  - removed on logout or session invalidation

- **optional local session metadata**
  - may include non-sensitive values such as:
    - last authenticated profile id
    - last selected league id
    - last successful restore timestamp
  - must not include secrets

### 10.3 Session states

The app should model authentication/session lifecycle explicitly.

Recommended session states:

- `loggedOut`
- `restoring`
- `authenticated`
- `refreshing`
- `sessionExpired`

Notes:
- `restoring` is used during app startup when a stored refresh token exists and session recovery is in progress
- `refreshing` is a transient internal state during token renewal
- `sessionExpired` may be used briefly to show a clear transition before routing to login, or can collapse directly into `loggedOut`

### 10.4 App startup / cold start behavior

On app launch:

1. initialize secure storage, networking, and session controller
2. check whether a refresh token exists in secure storage
3. if no refresh token exists:
   - transition to `loggedOut`
   - show unauthenticated routes
4. if refresh token exists:
   - transition to `restoring`
   - call `/auth/token/refresh`
5. if refresh succeeds:
   - store new tokens as required
   - transition to `authenticated`
   - continue into authenticated app bootstrap
6. if refresh fails:
   - clear stored refresh token
   - clear user cache/session data
   - transition to `loggedOut`

The app should not assume that a previously authenticated user is still valid until restore succeeds.

### 10.5 Post-auth bootstrap behavior

After a session becomes authenticated, the app should load the minimum required bootstrap data for the signed-in experience.

Recommended order:
1. restore or determine active league context
2. load the initial authenticated payload needed for app entry
3. allow feature screens to load their own data afterward

In practice, the app should enter the authenticated shell only after auth is valid.
Feature payload loading should not race ahead while auth is unresolved.

### 10.6 Login flow

#### Standard login
Login uses:

- `POST /auth/login`

On success:
- receive tokens
- store refresh token in secure storage
- store access token in memory
- transition to `authenticated`
- enter authenticated app shell
- trigger initial authenticated bootstrap

If login fails:
- surface normalized backend error
- keep user on login screen

Special handling:
- `AUTH_EMAIL_NOT_VERIFIED` should route the user into the OTP verification path rather than treating it as a generic login failure

### 10.7 Registration + OTP verification flow

Registration is a multi-step flow using:

- `POST /auth/register`
- `POST /auth/otp/send`
- `POST /auth/otp/verify`

Expected behavior:
- register creates the unverified account and initiates OTP delivery
- the app then moves into OTP verification UI
- resend uses the resend endpoint with backend cooldown/rate-limit rules
- verify completes registration

If OTP verify returns tokens immediately:
- store them using the normal authenticated flow
- transition directly into `authenticated`

If OTP verify only returns verification success without tokens:
- route to login and require standard login afterward

OTP timers/messages in the UI are advisory only and should be driven by backend responses and configured rules.

### 10.8 Password reset flow

Password reset is a separate unauthenticated flow using:

- `POST /auth/password/forgot`
- `POST /auth/password/reset`

Rules:
- this flow must not assume the user is authenticated
- OTP/cooldown/retry handling follows the same OTP policy family as registration
- success should route the user back to login unless the backend explicitly returns tokens in the future

### 10.9 Authenticated request behavior

For authenticated endpoints:

- attach access token automatically
- if request returns `401` due to invalid/expired access token:
  - run centralized refresh flow
  - retry the original request once if refresh succeeds
  - if refresh fails, invalidate session and route to login

Constraints:
- only one refresh flow may run at a time
- the same request must not be retried more than once
- the refresh endpoint itself must never trigger recursive refresh

### 10.10 Foreground / resume behavior

When the app returns to foreground:

- do not refresh tokens proactively just because time passed
- allow normal authenticated requests to trigger refresh if needed
- revalidate cacheable payloads according to their screen/repository rules
- if a foreground request reveals session expiry and refresh fails, force logout

This keeps auth logic centralized and avoids parallel ad hoc token checks across screens.

### 10.11 Logout flow

Logout uses:

- `POST /auth/logout`

On explicit logout:
1. call logout endpoint when feasible
2. regardless of server response, clear local session state
3. remove refresh token from secure storage
4. clear in-memory access token
5. clear user-scoped and league-scoped cached data
6. reset active league context
7. transition to `loggedOut`
8. route to login / unauthenticated entry

Reasoning:
- local logout must complete even if the network is unavailable or the server token is already invalid

### 10.12 Forced session invalidation

The session must be invalidated when any of the following occurs:

- refresh token is missing during restore
- refresh request fails with invalid/expired token
- backend indicates auth is no longer valid and refresh cannot recover it
- profile deletion succeeds
- logout completes locally

On forced invalidation:
- clear all cached user/session data
- clear active league context
- remove tokens
- return to unauthenticated navigation

### 10.13 Multi-device behavior

The mobile architecture assumes that the same user may be signed in on multiple devices simultaneously unless backend policy changes later.

Implications:
- one device logging in does not automatically assume all others are invalid
- refresh token rotation is handled per session/token, not as a global client assumption
- the mobile app should react to actual backend auth failures rather than inventing single-device restrictions

### 10.14 Security rules

The app must follow these minimum security rules:

- never log access or refresh tokens
- never include tokens in crash reports
- never persist refresh token outside secure storage
- always use HTTPS in non-local environments
- clear session/caches on profile deletion
- treat client-side auth state as provisional until validated by backend behavior

### 10.15 Non-goals

This section does not define:
- social login
- biometric login as an auth replacement
- device management UI
- advanced background token refresh scheduling
- web session behavior

---

## 11. Local cache and persistence strategy

### 11.1 Purpose

Local persistence exists to improve responsiveness, enable fast screen reopen, and support limited offline read behavior for selected endpoints.

It does not make the client authoritative.

Rules:
- the backend remains the source of truth
- cached payloads are snapshots, not final truth
- cache freshness is validated through backend-defined revalidation rules
- successful writes are confirmed only by the server

### 11.2 Persistence layers

The mobile app uses three distinct persistence layers:

#### A. In-memory session state
Used for:
- current access token
- active session state
- active league context
- short-lived screen/application state

Properties:
- cleared when app process is killed
- not used for durable restore on its own

#### B. Secure storage
Used for:
- refresh token only

Properties:
- secure device-backed storage
- not used for normal cached API payloads

#### C. Local app cache / metadata store
Used for:
- cached Category A response bodies
- stored ETags
- lightweight app metadata needed across launches

This store is optimized for simple key-value persistence, not relational offline sync.

### 11.3 What may be persisted

The app may persist:

- cache entries for supported Category A endpoints
- ETags associated with those cache entries
- cache metadata
- selected non-sensitive app metadata such as:
  - last active league id
  - last successful bootstrap markers
  - feature-local lightweight UI preferences if later needed

The app must not persist:
- access token
- refresh token outside secure storage
- raw secrets in normal cache storage
- client-authoritative gameplay state
- fake/pending offline write results presented as committed truth

### 11.4 Cache entry structure

Each cached response entry should store at least:

- `cacheKey`
- `responseBody`
- `etag`
- `savedAt`
- `scopeType`
- `scopeValues`
- `schemaVersion`

Recommended scope metadata examples:
- `userId`
- `leagueId`
- `gw`
- `page/filter/query params`

Purpose:
- avoid serving the wrong cached payload under a different context
- support selective invalidation
- allow future schema-safe migrations

### 11.5 Cache key rules

Cache keys must be deterministic and include the smallest context required to uniquely identify the payload.

Rules:
- same endpoint + same effective context = same cache key
- different league context must produce different cache keys
- different GW/filter/paging/query context must produce different cache keys
- auth state must not be ignored when keying user-scoped payloads

Examples:

- `/home`
- `/notifications?filter=all&cursor=abc123`
- `/me`
- `/me/teams`
- `/leagues/{league_id}/fantasy`
- `/leagues/{league_id}/team`
- `/leagues/{league_id}/matches?gw={gw}`
- `/leagues/{league_id}/table`
- `/leagues/{league_id}/stats/players`
- `leagues/private-leagues/{league_id}`
- `leagues/private-leagues/{league_id}/members?page=1`

Repositories are responsible for generating and using the correct cache keys consistently.

### 11.6 What gets cached in v1

Version 1 caching is limited to backend-supported read payloads, primarily Category A endpoints.

Baseline rule:
- cache Category A GET payloads that are useful for fast reopen, refresh, or limited offline viewing
- do not cache action endpoints as authoritative state
- do not cache auth endpoints marked `no-store`

This includes user-scoped and league-scoped reads where backend rules already support ETag revalidation.

Category B/C behavior remains network-first unless a later section explicitly expands it.

### 11.7 Freshness and revalidation

The client does not rely on fixed TTL as the source of truth for Category A payloads.

For cacheable reads:
- store the last successful response body
- store the returned ETag
- on refresh/reopen, send `If-None-Match` when an ETag exists
- if server returns `200`, replace the cached body and ETag
- if server returns `304`, keep the cached body and treat it as current
- if network fails and cached data exists, the app may show cached data with stale/offline UX treatment

Revalidation should be preferred over blind local freshness assumptions.

### 11.8 Read behavior by scenario

#### App cold start
- restore session first
- once authenticated, load initial app data
- repositories may use cached payloads to improve perceived speed where appropriate
- authenticated cache must never bypass auth restore

#### Screen open / reopen
- if cached Category A payload exists, it may be shown immediately
- repository then revalidates according to screen rules
- UI should distinguish initial loading vs refresh where useful

#### Pull-to-refresh
- always revalidate from network
- include ETag when available
- keep showing current data until refreshed result resolves

#### Foreground resume
- do not refresh every payload blindly
- revalidate based on screen/repository rules for active surfaces

### 11.9 Offline behavior

The app is online-first.

Rules:
- cached supported reads may be shown offline if available
- writes require network availability
- the app must not fabricate successful offline writes
- when offline and no cached payload exists, show normal empty/error offline UX
- when offline and cached payload exists, show cached data with clear offline/stale indication where useful

Offline support in v1 is read-only and opportunistic, not a full sync model.

### 11.10 Invalidation and targeted clearing rules

The app should prefer targeted invalidation/revalidation over global cache wipes, except where session/user identity changes.

#### On successful write actions
Repositories should:
- revalidate affected read models according to backend refresh rules
- clear directly invalid cache entries when the old payload is no longer valid to show
- avoid mutating cached truth optimistically unless explicitly designed

Examples:
- team update / captain change / transfer confirm
  - revalidate affected home/team/fantasy-related reads
- mark notification read
  - revalidate notification list/unread indicators
- private league membership actions
  - revalidate affected lists/details
- league-scoped team deletion (future / post-MVP, if backend endpoint is implemented)
  - clear cached `/leagues/{league_id}/team` payload for that league
  - revalidate related league/user summaries

#### On logout
- clear all user-scoped cache
- clear all league-scoped cache tied to the current user
- clear active league context
- preserve only safe app-level non-user metadata if needed

#### On profile deletion (future / post-MVP, if backend endpoint is implemented)
- clear all cached user data
- clear all cached league-scoped data for that user
- clear session storage and local session metadata
- return to unauthenticated state

### 11.11 Scope-aware clearing rules

The cache layer should support invalidation by scope, not only by exact key.

Useful invalidation scopes:
- exact cache key
- all entries for a league
- all entries for a user
- all entries for a feature family
- full authenticated cache clear

This is important because many payloads are:
- user-scoped
- league-scoped
- league + GW scoped
- filtered or paged

### 11.12 Persistence versioning

Cached data must be versioned so breaking model changes do not leave the app with unusable stored payloads.

Recommended fields:
- cache schema version
- app build version or migration marker if needed

Rules:
- if a stored cache entry is incompatible with current parsing rules, discard it
- if a release introduces major payload-model changes, the app may wipe or migrate cache storage
- cache corruption should fail safely by dropping the bad entry, not breaking the app

### 11.13 Storage boundaries

To keep responsibilities clear:

- secure storage owns refresh token persistence
- cache storage owns cached API payloads and non-sensitive metadata
- repositories decide when to read/write/invalidate cache
- widgets/screens do not access raw cache storage directly

This preserves a single flow:
UI → provider/controller → repository → API/cache

### 11.14 Non-goals for v1

This persistence strategy does not include:
- offline write queueing
- conflict resolution between local and remote writes
- client-authoritative roster/gameplay state
- relational local query engine for complex joins
- storing every endpoint response indiscriminately

### 11.15 Practical default policy

Version 1 should use the following default approach:

- cache supported Category A reads
- persist ETags with those reads
- revalidate on refresh/reopen as defined by repositories
- clear user/league scoped data on logout, profile deletion, and similar identity/scope breaks
- keep write behavior network-only
- expand persistence only when a clear product need appears

---

## 12. App state model

### 12.1 Purpose

The app state model defines the main state domains the mobile app owns and how they relate to each other.

Its goals are to:
- keep state ownership clear
- avoid duplicating the same truth in multiple places
- separate durable app state from temporary screen state
- support predictable refresh, cache, and navigation behavior
- ensure league-scoped screens react consistently to session and context changes

The app should prefer a small number of well-defined state domains over many loosely connected local states.

### 12.2 Core principle

State should be owned at the lowest level that is still shared safely.

Guiding rules:
- app-wide concerns belong in app-level state
- feature data belongs in feature/application state
- screen-only UI concerns stay local to the screen where possible
- repositories own data access logic, not UI state
- cached payloads are persisted data inputs, not widget state

The app must avoid duplicating the same logical state in:
- widget-local state
- provider/controller state
- repository memory cache
- persistent cache

Only one layer should be the active owner of each important state concept.

### 12.3 Main state domains

The app should treat the following as the main top-level state domains:

1. **Session state**
2. **League context state**
3. **Feature data state**
4. **Mutation/action state**
5. **Transient UI state**

### 12.4 Session state

Session state is app-global.

It represents the current authentication lifecycle and current signed-in identity.

Recommended fields:
- `status`
- `accessTokenInMemory`
- `isRestoring`
- `isRefreshing`
- optional minimal authenticated user identity summary
- optional `lastAuthError`

Recommended statuses:
- `loggedOut`
- `restoring`
- `authenticated`
- `refreshing`
- `sessionExpired`

Rules:
- session state is the source of truth for whether authenticated routes may be entered
- session state must not be derived from cached feature payloads
- refresh token itself is not exposed as normal app state
- session invalidation clears dependent authenticated state

### 12.5 League context state

League context state is app-global for authenticated navigation.

Recommended fields:
- `activeLeagueId`
- `lastValidLeagueId`
- optional `availableLeagueIds` or selector-derived summary
- optional `source` of the current selection (`restored`, `home`, `profile`, `notification`, `deep_link`)
- optional `isSwitching`

Rules:
- there is only one active league context at a time in normal v1 UX
- league-scoped features consume this shared state
- league context remains valid even if the user has no team in that league
- clearing session clears league context
- changing active league invalidates/reloads dependent feature states

League context is not screen-local state.

### 12.6 Feature data state

Feature data state represents the current effective data shown by a feature.

Examples:
- Home payload
- Team payload
- Rankings payload
- Notifications list
- Matches / Table / Stats payloads
- Profile payload
- Private league detail payload

Feature data state should usually be keyed by the minimum relevant scope, for example:
- user
- league
- league + GW
- filter/query/page

Each feature data state should support a standard async lifecycle.

Recommended shape:
- `idle`
- `loading`
- `ready`
- `refreshing`
- `empty`
- `error`

Recommended fields per feature state:
- `status`
- `data`
- `error`
- `isFromCache`
- `lastUpdated`
- `scopeKey`

Rules:
- a feature may show cached data while refreshing
- `refreshing` should not discard already visible valid data
- `empty` is distinct from `error`
- scope changes must create or reload the appropriate state, not silently reuse old data

### 12.7 Mutation/action state

Write actions should not be merged into read state in an ad hoc way.

Instead, the app should model mutation state separately.

Examples:
- login submission
- create team
- captain change
- transfer quote
- transfer confirm
- mark notification read
- invite member
- profile update
- delete team (future / post-MVP)
- delete profile (future / post-MVP)

Recommended shape:
- `idle`
- `submitting`
- `success`
- `failure`

Recommended fields:
- `status`
- `error`
- optional `result`
- optional `requestFingerprint`

Rules:
- mutation state is short-lived
- successful mutations trigger revalidation of affected reads
- mutation success does not automatically replace authoritative feature state unless explicitly designed
- failed mutations must not leave fake local truth behind

### 12.8 Transient UI state

Transient UI state should remain local whenever possible.

Examples:
- selected tab inside Matches
- current sort option in Stats
- expanded accordion sections
- active form field values
- modal open/closed state
- local search text before submission
- scroll position

Rules:
- transient UI state should not be promoted to global app state unless multiple routes/features truly share it
- losing transient UI state during route disposal is usually acceptable
- transient UI state must not become a hidden source of business truth

### 12.9 Derived state

Some UI values should be computed from existing state rather than stored separately.

Examples:
- whether the current route requires an active league
- unread badge count
- whether Team should show builder vs current roster
- whether Rules can be opened directly
- whether current screen is showing cached-or-fresh data
- whether current league context is valid for a target route

Rules:
- derive when cheap and deterministic
- avoid storing values that can easily become inconsistent with their inputs
- store only if there is a strong performance or lifecycle reason

### 12.10 Recommended ownership model

Recommended ownership by state type:

#### App-level controllers/providers
Own:
- session state
- league context state
- root navigation-affecting decisions

#### Feature-level controllers/providers
Own:
- feature data state
- mutation/action state for that feature
- feature-specific derived state

#### Widgets/screens
Own:
- transient UI state
- form controllers
- local input focus/selection state
- modal visibility where not shared

#### Repositories
Own:
- no widget-facing UI state
- data retrieval and persistence logic only

### 12.11 State reset rules

The app must reset state predictably on major lifecycle events.

#### On logout / forced session invalidation
- clear session state
- clear active league context
- clear authenticated feature data state
- clear mutation state tied to authenticated identity
- return to unauthenticated shell

#### On profile deletion
- same as logout
- also clear persisted caches and session metadata

#### On league change
- preserve session state
- update league context
- invalidate or reload league-scoped feature states
- keep global/user-scoped state where still valid

#### On app restart
- restore secure/local persisted state as allowed
- do not assume authenticated feature state is still valid until auth restore succeeds

### 12.12 Standard async UX contract

Across features, the app should use consistent async behavior.

#### Initial load
- no valid data yet
- show loading state

#### Refresh with existing data
- keep current data visible
- show refresh indicator/subtle loading state
- replace data when new result arrives

#### Empty success
- show empty state, not error

#### Failure without previous data
- show error state

#### Failure with previous cached/current data
- keep existing data visible
- surface failure non-destructively where appropriate

This consistency is especially important for Category A cached reads.

### 12.13 Cache interaction with state

Persistent cache and in-memory feature state are related but not the same thing.

Rules:
- repositories may hydrate feature state from cached payloads
- repositories then revalidate from network according to rules
- feature state should know whether visible data came from cache
- cached persistence should not be treated as always-loaded in-memory state
- clearing cache and clearing current UI state are related but distinct operations

### 12.14 Concurrency and stale result protection

The state model should protect against stale async results overwriting newer state.

Rules:
- when scope changes, previous in-flight results should be ignored or cancelled
- latest-request-wins should apply for user-driven refresh/filter changes
- league change must prevent old-league results from becoming visible in the new league context
- mutation completion should revalidate the currently relevant scope, not a stale one

### 12.15 Suggested feature grouping for state ownership

A practical v1 grouping could be:

- `sessionStateProvider`
- `leagueContextProvider`
- `homeStateProvider(leagueId?)`
- `teamStateProvider(leagueId)`
- `teamBuilderStateProvider(leagueId)`
- `rankingsStateProvider(leagueId)`
- `rulesStateProvider(leagueId)`
- `notificationsStateProvider(filter/page)`
- `matchesStateProvider(leagueId, gw)`
- `tableStateProvider(leagueId)`
- `statsStateProvider(leagueId, filters...)`
- `profileStateProvider`
- `myTeamsStateProvider`
- `privateLeaguesStateProvider(leagueId)`
- `privateLeagueDetailStateProvider(leagueId, privateLeagueId)`

Exact naming can change, but the ownership idea should stay consistent.

### 12.16 Non-goals

This state model does not require:
- one giant global store for every screen
- optimistic local-authoritative gameplay state
- per-widget reinvention of loading/error handling
- persistent storage of all transient UI choices

---

## 13. Navigation structure

### 13.1 Navigation goals

Navigation should provide:

- a stable authenticated app shell
- predictable access to the main product areas
- clear separation between global, user-scoped, and league-scoped routes
- support for deep links and notification targets
- minimal route duplication
- preserved tab state where useful

The navigation model should remain simple enough that league context changes do not create ambiguous screen ownership.

### 13.2 Top-level shells

The app has two top-level shells:

#### A. Unauthenticated shell
Used when session state is:
- `loggedOut`
- `restoring` (transient bootstrap/loading presentation)
- `sessionExpired` before redirect completes

Primary routes:
- Splash / Restore
- Login
- Register
- OTP Verify
- Forgot Password
- Reset Password

#### B. Authenticated shell
Used when session state is:
- `authenticated`

This shell owns the main bottom navigation and all authenticated child routes.

### 13.3 Authenticated root navigation

The authenticated shell uses **five bottom tabs**:

1. **Home**
2. **Team**
3. **Rankings**
4. **Matches**
5. **More**

Recommended route ids (example naming):
- `HomeTab`
- `TeamTab`
- `RankingsTab`
- `MatchesTab`
- `MoreTab`

Rationale:
- Home is the league-entry and overview surface
- Team is the primary gameplay/action surface
- Rankings is a core destination and the natural parent entry to private leagues
- Matches groups matches/table/stats in one sports/results destination
- More is the account/settings/support hub

This keeps the app aligned with the currently defined screen set without introducing a sixth persistent tab for a secondary destination such as Notifications or Private Leagues.

### 13.4 Tab ownership and scope

#### Home
- works without an active league
- becomes league-contextual when a league is selected
- owns the main league selector entry point

#### Team
- league-scoped
- requires an active league context
- if the user has no team in the active league, the tab should still open and show the team creation/builder path rather than hard-blocking navigation

#### Rankings
- league-scoped
- requires an active league context
- remains readable even when the user has no competitor in that league; use empty/CTA states rather than blocking the route
- acts as the parent entry point for private leagues in v1

#### Matches
- league-scoped
- requires an active league context
- contains internal subviews for:
  - Matches
  - Table
  - Stats

#### More
- user-scoped / mostly global
- contains account and support surfaces
- may contain league-dependent child screens such as Rules

### 13.5 Nested routes and subflows

The following routes should sit under the authenticated shell as nested push routes or modal routes.

#### Under Home
- Notifications
- optional future News detail
- optional league selector modal/sheet (if selector is extracted)

#### Under Team
- Team Builder / Create Team
- Transfer Market
- Transfer Confirm flow
- Player Detail modal

#### Under Rankings
- Private Leagues list
- Private League detail
- Invite Members
- optional invite accept/decline detail handling if not handled through Notifications

#### Under Matches
- Match Detail
- Player Detail modal

#### Under More
- Profile
- Settings
- Rules
- Contact / Support

### 13.6 Presentation style by route type

Recommended route presentation:

- **Bottom tabs**
  - persistent app shell destinations

- **Standard push routes**
  - Profile
  - Settings
  - Rules
  - Contact
  - Notifications
  - Private Leagues list
  - Private League detail
  - Match Detail

- **Modal / sheet routes**
  - Player Detail
  - Transfer confirmation
  - lightweight confirmations where appropriate

Guiding principle:
- use push routes for destinations that feel like full pages
- use modal/sheet presentation for short, contextual actions tied to an underlying screen

### 13.7 Route guards

Routes should be guarded by app state, but guards must not over-block valid empty states.

#### Auth guard
Required for all authenticated shell routes.

If authentication is missing or refresh fails:
- clear session state
- reset authenticated navigation stack
- route to Login

#### Active league guard
Required for league-scoped routes:
- Team
- Rankings
- Matches
- Private Leagues
- Match Detail
- Transfer Market
- Player Detail
- Rules

If no active league is selected:
- redirect to Home
- show/select league prompt if needed

#### Competitor-required guard
Use carefully.

For routes where “no team yet” is a valid product state, do **not** block navigation entirely.
Instead:
- Team should show builder/create-team flow
- Rankings should show its defined empty state / CTA
- league-scoped read-only surfaces may still open if allowed by backend rules

### 13.8 Initial authenticated landing behavior

After successful authentication or session restore:

- enter the authenticated shell
- default landing route is **Home**
- Home is responsible for restoring or selecting the active league context
- other league-scoped tabs should read from the shared active league context rather than each picking their own league independently

This keeps league selection centralized.

### 13.9 Tab state preservation

The app should preserve reasonable state within each root tab while the authenticated shell remains alive.

Examples:
- Home scroll position / selected preview state
- Team current view mode
- Rankings current section
- Matches current subview and selected GW
- More list scroll position

However:
- preserved view state must not override explicit refresh/revalidation rules
- league context changes may invalidate tab-local content and require reload

### 13.10 Deep link handling

Deep links should resolve into the authenticated shell plus the correct child route.

Supported target families include:
- notifications
- private leagues
- matches
- player detail
- profile/account-related destinations
- team/open-team actions

Resolution rules:
- authenticate first if needed
- validate target scope and permissions through normal endpoint loading
- if the target includes league identity and it is valid, switch active league context before opening the destination
- if the target entity is invalid or no longer accessible, show a non-blocking message and fall back to a safe route

For notification targets specifically:
- target validity is resolved at navigation time, not assumed from the notification payload itself
- if the destination request returns `403` or `404`, the app should remain usable and not get stuck in a broken route flow

### 13.11 Navigation resets after major state changes

The app should reset or rebalance navigation in these cases:

#### Logout / forced session invalidation
- discard authenticated shell stack
- route to unauthenticated shell

#### Profile deletion
- same as logout, plus clear all local cached data

#### Active league change
- do not destroy the whole shell
- re-evaluate currently visible league-scoped route
- if the current route cannot sensibly exist in the new league context, fall back to the owning root tab screen

#### Team deletion in active league
- remain inside authenticated shell
- if currently on Team or another team-dependent route for that league, return to the Team root and show the no-team/create-team state

### 13.12 Non-goals

This navigation structure does not require:
- a separate persistent Notifications tab
- a separate persistent Private Leagues tab
- multiple simultaneous league contexts
- platform-specific navigation trees for iOS vs Android

---

## 14. League-context handling

### 14.1 Purpose

League context is a first-class app state concept.

Its job is to ensure that league-scoped screens, repositories, and cache entries all resolve against the same active league unless a route explicitly overrides it.

This avoids:
- different tabs accidentally showing different leagues
- wrong cache entries being reused under another league
- deep links opening in an inconsistent state
- duplicated league-selection logic across screens

### 14.2 Core rule

The app maintains **one active league context at a time** for normal navigation.

Rules:
- league-scoped screens read from the shared active league context
- global/user-scoped screens do not require league context
- routes may temporarily override league context during deep-link resolution, but the result must become the new active league context if valid
- the app must not present multiple simultaneous active league contexts in normal v1 UX

### 14.3 What is league-scoped vs not

#### Global / user-scoped
These do not require an active league to exist:

- Login / Register / OTP / Password reset
- More
- Profile
- Settings
- Contact
- Notifications
- `GET /me`
- `GET /me/teams`

#### Optional / mixed scope
These can exist without an active league but become league-contextual when one is selected:

- Home
- Rules (if entered without preselected league, choose from current active league or prompt/select)

#### Strictly league-scoped
These require a valid `league_id`:

- Team
- Transfer Market
- Player Detail
- Rankings
- Matches / Table / Stats
- Match Detail
- Private Leagues list
- Private League detail
- Invite Members
- `GET /leagues/{league_id}/...` endpoints generally

### 14.4 Source of truth for active league

The app should keep a single shared `activeLeagueId` in app-level state.

This state should be owned centrally, not separately per tab.

Recommended stored fields:
- `activeLeagueId`
- `availableLeagueIds` or a derived source from Home / teams payloads
- `lastValidActiveLeagueId`
- optional `source` for diagnostics (`restored`, `home_selector`, `profile_open_team`, `notification`, `deep_link`)

The app may persist the last valid active league id across launches as non-sensitive metadata.

### 14.5 How active league is established

The active league may be established from any of the following sources:

#### A. Home league selector
This is the primary manual source of league switching.

Expected behavior:
- user selects a league in Home
- app updates `activeLeagueId`
- Home loads/revalidates the selected league payload
- other league-scoped tabs now resolve against the new active league

#### B. Session restore
After authentication restore/login, the app may restore the last valid active league id if:
- the user still has access to that league
- the league still exists in the current selector/team context

If the restored league is invalid:
- discard it
- fall back to Home/default league selection logic

#### C. Profile → Open team
Profile may open a team in a specific league.

Expected behavior:
- set `activeLeagueId` to the selected team’s league
- navigate to Team (or another intended league-scoped destination)

#### D. Notification / deep link target
If a notification or deep link carries `league_id`:
- validate/authenticate first
- set `activeLeagueId` to that league if accessible
- then open the target destination

#### E. Fallback default
If no active league is available but the user has accessible leagues:
- Home should prompt/select one using backend-provided selector data
- do not let each league-scoped screen invent its own default independently

### 14.6 Validity rules for league context

A league context is valid only if:
- the user is authenticated
- the league exists
- the user has access to the league
- the route/resource being opened is allowed in that league

Additional notes:
- having access to a league does not always imply having a team in that league
- no-team-in-league is a valid product state and must not automatically invalidate league context
- season locked is also a valid league state, not an invalid context

### 14.7 Context switching behavior

When the active league changes:

1. update shared `activeLeagueId`
2. keep the authenticated shell intact
3. notify dependent repositories/providers
4. re-evaluate the currently visible route
5. revalidate relevant league-scoped payloads for the new league
6. avoid showing stale content from the previous league under the new context

Important rules:
- old cached entries may remain stored, but must not be shown under the new league id
- tabs should reload against the new league context when they become visible or when their provider invalidation requires it
- switching league is not the same as logging out; user-scoped/global state remains intact

### 14.8 Route behavior under league changes

#### If current route can exist in the new league
Rebind to the new league and reload.

Examples:
- Team root
- Rankings root
- Matches root
- Rules

#### If current route is tied to an entity from the old league
Do not try to silently reinterpret it under the new league.

Examples:
- Match Detail for old league match
- Private League detail from old league
- Player Detail opened for old league context with league-specific actions

Recommended behavior:
- close/dismiss contextual modal routes where appropriate, or
- fall back to the owning root route in the new league context

### 14.9 Handling no active league

If a user is authenticated but no valid active league is currently selected:

- Home remains available
- league-scoped tabs should redirect to Home or show a controlled “Select a league” handoff
- the app should not crash, loop, or invent an arbitrary hidden default

If the backend returns no leagues:
- Home should show the documented “No leagues available” state
- other league-scoped tabs should remain inaccessible until a league exists or becomes accessible

### 14.10 Handling no-team state within a valid league

A user may have access to a league but no competitor/team in that league.

This is a valid state.

Rules:
- keep the active league valid
- Team should show create-team / builder flow
- Home may show “Create your team” CTA
- Rankings may still open and show its allowed empty/read-only state
- do not clear league context just because competitor is missing

### 14.11 Handling invalidated league context

The current active league may become invalid because:
- user loses access to the league
- deep-link target league is invalid
- selected team/league was deleted or removed
- backend returns `403` / `404` for the active league on a root league-scoped payload

Recommended recovery:
1. detect invalidity from authoritative backend response
2. clear current active league
3. attempt fallback to:
   - last other valid league, if known
   - otherwise Home with selection prompt
4. show a non-blocking message where helpful

The app should not keep retrying the same invalid league forever.

### 14.12 Cache interaction

League context and cache strategy are tightly linked.

Rules:
- every league-scoped cache key must include `league_id`
- league + GW / query / paging context must also be included where relevant
- switching leagues must never reuse another league’s payload as the visible result
- invalidating one league’s team payload must not remove all leagues unnecessarily unless the action truly affects them all

Examples:
- `/leagues/{league_id}/team`
- `/leagues/{league_id}/matches?gw={gw}`
- `/leagues/{league_id}/stats/players?week_gw={weekGw}&offset={offset}`
- `/leagues/{league_id}/private-leagues`

### 14.13 Deep links and notifications

Notification and deep-link targets may carry league identity.

Resolution rules:
- authenticate first
- read target payload parameters
- if `league_id` is present and valid, switch active league before navigation
- if `league_id` is missing for a league-scoped target, the app may use the current active league only if that makes the target unambiguous
- if the target cannot be opened, fall back safely and keep the app usable

The notification/deep-link payload itself must not be treated as final authority; the actual destination request validates access and existence.

### 14.14 Recommended implementation ownership

League context should be owned by a dedicated app-level controller/provider.

That owner should expose:
- current active league id
- set/switch league
- restore last league
- clear invalid league
- validate or reconcile against incoming target intents

Repositories should consume league context, not own it.

Widgets should react to league context, not implement league selection logic independently.

### 14.15 Non-goals

This section does not introduce:
- multi-window or split-screen simultaneous league viewing
- per-tab independent active league state
- background synchronization across all leagues
- hidden automatic league switching without user-visible cause

---

## 15. Screen integration conventions

### 15.1 Purpose

This section defines the standard implementation pattern for mobile screens.

It exists to ensure that screens:
- consume backend contracts consistently
- handle loading/refresh/error states in a uniform way
- respect league context and auth rules
- apply cache/revalidation rules correctly
- do not re-invent their own networking or state conventions

This section does not replace the Phase B / Phase C screen specifications.
Those documents remain the source of truth for:
- screen purpose
- payload endpoints
- actions
- rules/config dependencies
- edge states
- screen-specific refresh behavior

### 15.2 Standard screen contract

Every implemented screen should explicitly define:

- route / entry point
- whether it is:
  - global
  - user-scoped
  - league-scoped
- required context inputs
- primary payload endpoint(s)
- write actions (if any)
- cache category of its primary reads
- refresh/revalidation triggers
- edge/empty/error states
- offline behavior

This mirrors the existing Phase B / Phase C screen templates and keeps implementation aligned with the documented contracts.

### 15.3 Standard screen composition

A screen should usually be composed from these parts:

1. **Route entry**
   - resolves route params
   - checks auth / league context requirements
   - wires the correct provider/controller

2. **Screen controller/provider**
   - owns feature state for the route scope
   - loads payloads
   - triggers refresh
   - delegates actions to repositories/services

3. **Presentation widget tree**
   - renders loading / empty / error / ready states
   - renders cached-data-while-refreshing states where relevant
   - triggers user actions

4. **Repository layer**
   - handles API + cache
   - applies write → revalidation rules
   - returns typed results/failures

Widgets/screens must not call Dio or raw HTTP directly.

### 15.4 Required implementation checklist per screen

For every new screen, implementation should confirm:

#### A. Scope
- Is the screen global, user-scoped, or league-scoped?
- If league-scoped, where does `league_id` come from?
- Can the screen exist in a valid no-team state?

#### B. Payloads
- Which GET endpoint(s) provide the initial data?
- What query params affect the cache key?
- Does the payload depend on user, league, GW, paging, or filters?

#### C. Actions
- Which write endpoints can be triggered?
- What should happen immediately in the UI?
- Which reads must be revalidated after success?

#### D. State
- What is the feature state shape?
- What transient UI state stays local?
- What mutation state needs to be modeled separately?

#### E. UX states
- loading
- refreshing
- empty
- offline cached
- error without data
- error with previous data

#### F. Navigation dependencies
- can the screen open from a tab, push route, modal, notification, or deep link?
- does entering it require switching active league context first?

### 15.5 Route-level requirements

Every screen must declare one of these route types:

- **Tab root**
- **Push page**
- **Modal / sheet**
- **Flow step**

Recommended examples:
- Home, Team, Rankings, Matches, More → tab roots
- Notifications, Profile, Settings, Rules, Contact, Private League detail, Match Detail → push pages
- Player Detail, Transfer confirm → modal/sheet
- Registration / OTP / password reset → flow steps

This keeps route behavior predictable across the app.

### 15.6 Context resolution rules

Before loading data, a screen must resolve its required context.

#### Auth-required screens
- require authenticated session
- if auth restore/refresh fails, route to Login

#### League-scoped screens
- require valid `activeLeagueId` or a valid explicit route target
- if opened from a deep link/notification with `league_id`, reconcile that first
- if no valid league is available, fall back safely to Home/select-league flow

#### Entity-detail screens
Examples:
- Match Detail
- Player Detail
- Private League detail

These require:
- valid route entity id
- valid auth
- valid league context if league-scoped

If the entity cannot be resolved:
- show non-blocking error
- close modal or navigate back safely

### 15.7 Read integration pattern

For read screens using Category A endpoints, use this default pattern:

1. resolve context
2. build the correct scope key / cache key
3. load cached data if available
4. show cached data immediately when appropriate
5. revalidate via network with ETag
6. update feature state with:
   - fresh data on `200`
   - cached data on `304`
7. surface offline/error state appropriately if network fails

This pattern is especially important for Home, Team, Rankings, Notifications, Matches, Table, Stats, Player Detail, Profile, and Rules, which are all built on cacheable reads. 

### 15.8 Write integration pattern

For any screen with write actions:

1. validate minimal client-side input for UX only
2. submit write through repository/service
3. show submitting state
4. on success:
   - update local transient UI if helpful
   - trigger required revalidation of affected reads
5. on failure:
   - show normalized error
   - revalidate affected payloads when server-authoritative state may have changed

Examples already defined by the specs:
- captain change → refresh `/leagues/{league_id}/team`
- transfer confirm → refresh `/team`, `/home?league_id`, `/fantasy`, and contextual market/player detail reads
- mark notification read → refresh `/notifications` and optionally Home preview/badge
- `PATCH /me` → refresh `/me` (and `/home` if alias is displayed there)
- team deletion (future / post-MVP) → refresh `/me/teams`, `/home`, `/fantasy`, and clear cached `/team`
- profile deletion (future / post-MVP) → logout and clear local caches/session

### 15.9 Handling special endpoint semantics

Some endpoints need special screen behavior and must not be forced into a simplistic success/failure model.

Examples:
- transfer quote may return success with `is_valid=false` and violations instead of a hard error
- market/player detail may expose server-computed `availability` / `disabled_reasons`
- notification targets must be resolved at navigation time rather than trusted blindly

Screens should reflect these semantics directly instead of trying to convert them into fake generic states. 

### 15.10 Standard UI state rendering rules

Each screen should support these baseline render states:

#### Initial loading
- no valid data yet
- full loading UI

#### Ready
- payload successfully available
- render primary content

#### Refreshing
- keep current content visible
- show pull-to-refresh or subtle loading indicator

#### Empty
- successful response with no meaningful items/content
- show explicit empty state

#### Error without data
- show retry-capable error state

#### Error with previous data
- keep previous data visible
- show non-destructive error feedback

#### Offline cached
- show cached data if present
- disable unavailable writes
- indicate offline/stale state where useful

This matches the intended behavior of Category A screens with ETag revalidation and cached reopen support. 

### 15.11 Cross-screen consistency rules

To keep the app coherent:

- list screens should use consistent pull-to-refresh behavior
- detail screens should use consistent close/back behavior on 403/404
- all authenticated screens should rely on centralized 401 handling
- no screen should locally duplicate gameplay rules if the backend already exposes them
- no screen should keep showing data from the wrong league after context switch
- no screen should treat cached payloads as permanent truth

### 15.12 Minimal screen definition artifact

For implementation planning, each screen should have a small handoff entry containing:

- screen name
- route type
- scope type
- payload endpoint(s)
- action endpoint(s)
- provider/controller name
- repository name
- cache key inputs
- refresh triggers
- error fallback behavior

This handoff can be much smaller than the full Phase B / Phase C spec because it references the source documents instead of duplicating them.

### 15.13 Examples of applying the convention

#### Example: Home
- route type: tab root
- scope: optional league-scoped
- reads: `GET /home` or `GET /home?league_id=...`
- writes: none in v1
- refresh: tab open, league switch, team-affecting writes elsewhere
- special handling: can exist without active league. 

#### Example: Team
- route type: tab root
- scope: league-scoped
- reads: `GET /leagues/{league_id}/team`
- writes: captain, substitute, team create, transfer flow
- refresh: on open, after roster-changing writes, after stale-state rejection
- special handling: valid no-team/create-team state. 

#### Example: Notifications
- route type: push page
- scope: user-scoped
- reads: `GET /notifications`
- writes: mark read / read all
- refresh: app resume, write success, pull-to-refresh
- special handling: target navigation may switch active league. 

#### Example: Player Detail
- route type: modal
- scope: user + league + player
- reads: `GET /leagues/{league_id}/players/{player_id}`
- writes: optional captain, transfer flow entry
- refresh: after transfer confirm, after captain change when applicable
- special handling: close safely on 403/404. 

### 15.14 Non-goals

This section does not:
- replace detailed screen specs
- define final visual design
- define analytics in detail
- require every screen to look identical
- force every screen to have the same number of providers/controllers

---

## 16. Error handling and UX consistency

### 16.1 Purpose

This section defines how backend, network, auth, validation, and business-rule errors are translated into consistent mobile UX.

Its goals are to:

- keep error handling predictable across screens
- ensure backend-authoritative failures are surfaced clearly
- avoid each feature inventing its own error semantics
- separate technical failures from user-facing messages
- keep the app usable even when requests fail

This section does not replace the backend error catalog.
The backend error specification remains the source of truth for:
- HTTP status usage
- backend error codes
- backend error envelope structure
- rule/conflict semantics

### 16.2 Core principles

Error handling in the app should follow these principles:

- backend is authoritative
- technical errors should be normalized centrally
- user-facing messages should be clear and action-oriented
- previous valid data should remain visible when possible
- auth/session failures should be handled centrally
- screens should fail safely and remain recoverable

The app must not:
- silently swallow important failures
- show raw backend JSON to users
- keep obviously stale or invalid state without revalidation
- invent client-side success after a failed server action

### 16.3 Error categories used by the app

The app should normalize failures into a small shared taxonomy.

Recommended categories:

- `AuthFailure`
- `ValidationFailure`
- `RuleConflictFailure`
- `ForbiddenFailure`
- `NotFoundFailure`
- `RateLimitFailure`
- `NetworkFailure`
- `TimeoutFailure`
- `ServerFailure`
- `UnknownFailure`

Each normalized failure should preserve, where available:
- HTTP status
- backend error code
- backend message
- optional rule/details payload
- optional original request context

### 16.4 Ownership of error handling

#### Networking layer
Owns:
- transport error capture
- HTTP status handling
- backend envelope parsing
- normalization into shared failure types

#### Repositories
Own:
- mapping failures into feature-appropriate outcomes
- deciding when a failure should trigger revalidation
- preserving previous data where appropriate

#### Controllers/providers
Own:
- exposing error state to the UI
- separating load failures from mutation failures
- deciding whether an error is blocking or non-blocking for the current screen state

#### Screens/widgets
Own:
- rendering the correct UX state
- showing retry actions
- showing inline/form-level feedback where appropriate

Widgets must not parse raw backend errors directly.

### 16.5 UX treatment by error class

#### Auth failures
Examples:
- expired access token that cannot be refreshed
- invalid refresh token
- missing/invalid auth for protected route

UX treatment:
- handled centrally
- clear session state
- reset authenticated navigation
- route to Login
- optionally show one clear session-expired message

Screens should not individually handle logout flows for these cases.

#### Validation failures
Examples:
- invalid form fields
- invalid transfer inputs
- malformed request combinations rejected by backend

UX treatment:
- show inline field or form-level feedback when possible
- keep user on the current screen
- preserve entered values where safe
- do not treat as full-screen error state unless no better placement exists

#### Rule conflict failures
Examples:
- stale roster assumptions
- season locked
- player unavailable
- invite/action no longer valid
- transfer no longer possible

UX treatment:
- show clear action-oriented message
- keep the current screen usable
- revalidate affected payloads if server state may have changed
- avoid pretending local state is still authoritative

#### Forbidden failures
Examples:
- user lacks access to league/resource
- action not allowed for this role/state

UX treatment:
- if on a root screen, show safe fallback or redirect
- if on a detail/modal route, close or navigate back safely
- show concise explanation where helpful

#### Not found failures
Examples:
- deleted match/private league/player target
- expired notification target
- invalid route entity id

UX treatment:
- for detail routes/modals: close or navigate back safely
- for root content: show empty/not-found-safe state
- avoid trapping the user on a broken screen

#### Rate limit failures
Examples:
- OTP resend cooldown
- repeated action throttling

UX treatment:
- show clear retry-later messaging
- preserve current input state
- use backend response as authority for cooldown behavior
- do not invent independent client timers beyond advisory UX

#### Network / timeout failures
Examples:
- offline device
- request timeout
- temporary connectivity problems

UX treatment:
- if previous data exists, keep showing it
- if cached data exists, show cached/offline state
- if no data exists, show retryable error state
- disable writes that require connectivity

#### Server / unknown failures
Examples:
- unexpected 5xx
- malformed unexpected response
- unclassified failure

UX treatment:
- show generic recovery-friendly message
- keep app stable
- provide retry where meaningful
- do not expose internal technical details to the user

### 16.6 Load errors vs mutation errors

The app should distinguish clearly between:

#### Load errors
These affect screen data fetching.

Rules:
- if no valid data exists, show full-screen or major inline error state
- if previous data exists, keep it visible and show non-blocking error feedback
- allow retry

#### Mutation errors
These affect user-triggered actions.

Rules:
- do not replace the whole screen with an error state
- keep existing screen content visible
- show action-local error feedback
- keep form/input state where possible
- revalidate if the failure may indicate stale server state

This distinction is important for screens like Team, Transfers, Notifications, Profile, and Private Leagues.

### 16.7 Standard screen-level UX states

Every screen should support these baseline behaviors:

#### Initial load failure
- show clear retryable error state
- do not show fake empty state if the request actually failed

#### Refresh failure with existing data
- keep existing data visible
- show subtle non-blocking error message
- allow manual retry

#### Mutation failure
- keep current screen visible
- show localized error feedback
- do not discard valid loaded data

#### Offline cached state
- show cached data if available
- indicate offline/stale status where useful
- disable or guard writes that need network

#### Invalid detail target
- show brief message
- close or fall back safely

### 16.8 Message strategy

User-facing messages should be:

- concise
- actionable
- non-technical
- specific when the backend meaning is clear

Prefer:
- “Your session expired. Please sign in again.”
- “This action is no longer available. The screen was refreshed.”
- “You’re offline. Showing the last available data.”
- “This item is no longer available.”

Avoid:
- raw error codes shown directly to users
- stack-trace style messages
- vague “Something went wrong” everywhere when a clearer message exists

The app may still log backend codes internally for diagnostics.

### 16.9 Revalidation after errors

Some failures should trigger follow-up revalidation because they often indicate stale local assumptions.

Common examples:
- roster-related write rejected
- transfer confirm rejected
- captain/substitute action rejected
- invite action rejected because target state changed
- notification target fails because entity access changed

Rule:
- if the failure likely means the visible data may no longer match server state, revalidate the affected read payloads

Do not revalidate blindly after every error.
Use targeted refresh based on the feature and scope.

### 16.10 Detail-route failure behavior

Detail routes and modals require special consistency.

Examples:
- Player Detail
- Match Detail
- Private League detail

Rules:
- if the entity becomes inaccessible or missing (`403` / `404`), do not leave the user stranded
- modal routes should close safely
- push routes should navigate back or fall back to their parent route
- show a brief explanatory message where helpful

### 16.11 Form and inline error conventions

For forms and action-heavy screens:

- inline field errors for field-specific validation
- form-level error for cross-field/business validation
- button loading/disabled state during submit
- preserve input values on failure where safe
- clear resolved errors on user re-edit where appropriate

Applies especially to:
- login
- register / OTP
- forgot/reset password
- profile edit
- create team
- transfer actions
- invite actions
- contact form

### 16.12 Empty state vs error state

Empty state and error state must remain separate.

#### Empty state
Use when:
- request succeeded
- data is valid
- there is simply nothing to show

Examples:
- no notifications
- no private leagues
- no leagues available
- no stats rows for a filter

#### Error state
Use when:
- request failed
- the app could not determine the correct content
- retry may succeed

The app must not turn failed loads into fake empty states.

### 16.13 Consistency across tabs and features

To keep the app coherent:

- all tab roots should support pull-to-refresh in a consistent way where applicable
- all authenticated screens rely on centralized auth-expiry handling
- all detail screens use consistent missing/forbidden fallback behavior
- all mutation-heavy screens use localized action error feedback
- all screens preserve previous valid data when practical
- all screens respect backend-authoritative business-rule outcomes

### 16.14 Logging and diagnostics boundary

The app may keep richer diagnostic details internally than it shows to the user.

Allowed internally:
- backend error code
- HTTP status
- route/request context
- safe diagnostic breadcrumbs

Not allowed in user-visible UI:
- tokens
- raw backend payload dumps
- stack traces
- sensitive internal server details

### 16.15 Non-goals

This section does not:
- define the full backend error catalog
- replace per-screen empty/error copywriting
- define analytics/crash-reporting vendors
- require every failure to produce a toast
- require every error to be shown the same way visually

---

## 17. Security and privacy

### 17.1 Purpose

This section defines the baseline security and privacy rules for the mobile client.

Its goals are to:

- protect authentication credentials and user data
- keep sensitive information out of unsafe storage and logs
- ensure the app behaves safely across login, logout, refresh, and deletion flows
- minimize unnecessary persistence of personal or user-scoped data
- keep the mobile client aligned with backend-authoritative security behavior

This section defines client-side handling rules only.
It does not replace backend security controls, server-side authorization, or any future legal/privacy policy documentation.

### 17.2 Core principles

The mobile app must follow these principles:

- backend authorization is authoritative
- least sensitive persistence possible
- secrets must be isolated from normal app storage
- user-scoped cached data must be removable and scoped correctly
- diagnostics must never leak credentials
- the app should fail closed rather than expose protected data after session invalidation

The mobile app must not:
- persist secrets in unsafe storage
- trust client-side state over backend authorization
- keep showing protected data after logout/profile deletion
- log tokens or raw sensitive payloads
- invent local security rules that contradict backend behavior

### 17.3 Sensitive data classification for the app

For implementation purposes, the app should treat the following as sensitive:

#### High sensitivity
- access token
- refresh token
- password reset credentials
- OTP codes entered by the user
- any future device/session secrets

#### Medium sensitivity
- authenticated profile data
- contact/support message contents
- league-scoped user/team data
- notification contents where they reveal user activity
- cached authenticated payloads

#### Low sensitivity
- app version/build number
- non-user-specific environment label
- safe diagnostic state such as selected tab or current route name

Sensitivity level affects:
- storage
- logging
- diagnostics
- support payload inclusion
- reset/clear rules

### 17.4 Token handling rules

#### Access token
- stored in memory only
- attached automatically to authenticated requests
- never written to normal local persistence
- cleared on logout, session invalidation, and profile deletion

#### Refresh token
- stored only in secure device storage
- used only for refresh flow
- never logged
- never exposed to presentation/UI code
- removed on logout, refresh failure, session invalidation, and profile deletion

#### General token rules
- tokens must never be copied into crash logs
- tokens must never be included in contact/support payloads
- tokens must never be displayed in debug UI
- token access should be centralized through auth/session infrastructure only

### 17.5 Storage rules

The app uses three persistence levels with different security expectations:

#### A. In-memory state
Used for:
- active access token
- current session state
- current active league context
- current feature state

Security expectation:
- ephemeral
- cleared when app process ends or session is invalidated

#### B. Secure storage
Used for:
- refresh token only

Security expectation:
- device-secure storage only
- no general feature data stored here unless a future explicit decision requires it

#### C. Local cache / metadata store
Used for:
- cached Category A payloads
- ETags
- non-sensitive app metadata

Security expectation:
- must not contain auth secrets
- must be clearable by user/session scope
- should store only what is needed for app responsiveness and restore behavior

### 17.6 Network security rules

The app must treat transport security as mandatory outside local development.

Rules:
- use HTTPS for hosted test/staging/production environments
- do not allow production credentials or sessions over insecure transport
- environment configuration must not normalize insecure production usage
- auth/session logic should assume secure transport for non-local environments

Local development may use local/test setup as needed, but that is a development exception, not a production pattern.

### 17.7 Authorization boundary

The mobile app must never treat successful local state as proof of permission.

Rules:
- backend responses determine whether data or actions are allowed
- cached payload presence does not prove current access
- client-side guards improve UX, but backend authorization wins
- `403`, `404`, and auth failures must be handled as authoritative outcomes

Examples:
- league access can be lost even if old cached league data exists
- a notification target may no longer be accessible
- a team/action may become unavailable even if it was previously shown

### 17.8 Logging and diagnostics rules

The app may keep internal diagnostics, but must enforce strict redaction.

Must never be logged:
- access token
- refresh token
- passwords
- OTP codes
- raw authorization headers
- full sensitive request/response payloads when they contain protected user data

Allowed with care:
- endpoint alias or safe route name
- HTTP status
- backend error code
- cache key metadata that does not expose secrets
- app version/build number
- safe navigation/debug breadcrumbs

Debug logging must be environment-controlled and more restricted in production.

### 17.9 Support/contact privacy rules

If the app offers Contact / Support flows:

- user-entered message content should be treated as personal/sensitive input
- only necessary context should be attached
- no secrets/tokens may be attached
- diagnostic context must be sanitized and opt-in where applicable
- the app should avoid attaching raw cached payloads or full API responses automatically

Safe support context examples:
- app version
- platform
- current league id if relevant and non-secret
- current route/screen name
- timestamp
- safe backend error code if useful

Unsafe examples:
- tokens
- raw auth headers
- full personal payload dumps
- hidden internal server diagnostics

### 17.10 Cache privacy rules

Because cached API payloads may contain user-scoped or league-scoped data:

- cache entries must be keyed by correct user/league scope
- cached data from one user/session must not remain visible to another
- logout must clear authenticated cached data
- profile deletion must clear all cached authenticated data
- invalid league/user scope changes must not reuse old scoped cache visibly

The app should prefer scope-aware cache clearing over keeping stale personal data around.

### 17.11 Session invalidation and data clearing

The app must clear sensitive state promptly when trust in the session ends.

#### On logout
- clear in-memory access token
- remove refresh token from secure storage
- clear user-scoped and league-scoped cached data
- clear active league context
- reset authenticated navigation state

#### On refresh failure / forced session invalidation
- treat as lost authenticated trust
- clear the same sensitive/session state as logout
- return to unauthenticated flow

#### On profile deletion
- clear all local session data
- clear all cached user data
- clear league-scoped user data
- reset to logged-out state

### 17.12 Screen privacy behavior

Screens should avoid exposing more data than necessary.

Rules:
- protected screens require valid auth
- detail screens should close/fall back safely if access is lost
- screens should not continue showing protected stale data after session invalidation
- no screen should surface hidden internal identifiers unless product-required
- forms should preserve user input on validation failure, but not persist sensitive form content unnecessarily

Applies especially to:
- auth screens
- profile/account screens
- notifications
- private leagues
- contact/support
- any screen rendering user/team-specific data

### 17.13 Background and lifecycle considerations

The app should behave safely across lifecycle changes.

Rules:
- app restart must not assume authenticated trust until restore succeeds
- foreground resume should continue using centralized auth/session handling
- expired sessions discovered on resume/request must invalidate safely
- cached data shown after resume must still follow auth/scope rules

The app is not required in v1 to implement advanced background security features beyond the normal session model.

### 17.14 Privacy-by-default guidance

Where there is a choice, the app should prefer:

- less persistence over more persistence
- scoped cache entries over shared/global reuse
- sanitized diagnostics over full payload logging
- explicit revalidation over trusting stale local assumptions
- minimal support metadata over broad automatic attachments

### 17.15 Non-goals

This section does not define:
- full legal privacy policy text
- backend cryptographic implementation details
- certificate/signing pipeline details
- regulatory/compliance classification
- advanced anti-tampering or jailbreak/root detection strategy
- biometric UX decisions

---

## 18. Testing strategy

### 18.1 Purpose

This section defines the testing strategy for the mobile application.

Its goals are to:

- protect the core app architecture from regressions
- verify that mobile behavior matches backend contracts
- validate session/auth lifecycle behavior
- validate cache/revalidation behavior
- validate league-context and navigation correctness
- keep milestone delivery testable and safe to extend

The testing strategy should prioritize correctness of the shared architecture and core gameplay flows over exhaustive UI pixel testing.

### 18.2 Testing principles

Testing should follow these principles:

- test the highest-risk behaviors first
- test contract-driven behavior close to the repository/network boundary
- test milestone-critical user journeys end to end
- prefer a small number of strong integration tests over many weak duplicated tests
- keep screen/widget tests focused on state/render behavior, not backend contract duplication
- use the backend specs as the source of truth for expected client behavior

Highest priority areas for testing in v1:
- auth restore / refresh / logout
- active league context and switching
- Category A cache + ETag revalidation
- write-triggered refresh behavior
- route guards and deep-link fallbacks
- no-team / invalid-target / forbidden states

### 18.3 Test layers

The mobile app should use four main test layers:

#### A. Unit tests
Fast tests for isolated logic.

Primary targets:
- cache key generation
- ETag/cache metadata logic
- error normalization
- auth/session state transitions
- league-context state transitions
- DTO/domain mapping helpers
- small utility logic

#### B. Repository / service integration tests
Tests for API + cache + repository behavior using mocked/fake transport/storage.

Primary targets:
- auth refresh retry behavior
- `200` vs `304 Not Modified` handling
- cached-data fallback behavior
- write success triggering required revalidation
- write failure triggering appropriate refresh/reload behavior
- scope-aware cache invalidation

#### C. Widget / screen tests
Tests for state-driven rendering and interaction.

Primary targets:
- loading / empty / error / refreshing states
- no-team and read-only states
- localized mutation error display
- league switch updating visible content
- auth-expired transitions from protected screens
- detail-screen fallback behavior

#### D. End-to-end / flow tests
Tests for complete user flows across app layers.

Primary targets:
- login / restore / logout
- Home → Team → Transfer flow
- notification target navigation
- private league invite flows
- logout and local reset behavior
- profile deletion and local reset behavior (future / post-MVP)
- deep-link/open-target fallback behavior

### 18.4 Priority test matrix

The test strategy should prioritize this order:

#### Priority 1 — architecture-critical
- auth restore on app launch
- refresh-on-401 then retry-once
- refresh failure forcing logout
- active league switching
- wrong-league stale result protection
- Category A cache + `If-None-Match` + `304`
- logout clearing local state
- profile deletion clearing local state (future / post-MVP)

#### Priority 2 — core gameplay flows
- Home loading and league selection
- Team loading
- create team flow
- captain/substitute actions
- transfer quote/confirm flow
- Rankings refresh after gameplay writes
- Rules loading from backend, not hardcoded

#### Priority 3 — product completion flows
- notifications list + mark read
- matches/table/stats loading
- player detail modal
- profile update
- my teams
- delete team (future / post-MVP)
- private league create/invite/accept/leave flows

#### Priority 4 — lower-risk polish
- non-critical UI details
- optional telemetry hooks
- purely decorative presentation behavior

### 18.5 Unit test focus areas

Recommended unit test targets:

#### Session/auth
- session state transitions:
  - `loggedOut` → `restoring` → `authenticated`
  - `authenticated` → `refreshing` → `authenticated`
  - refresh failure → `loggedOut` / `sessionExpired`
- token-clearing logic on logout
- token-clearing logic on profile deletion (future / post-MVP)

#### League context
- restore last valid league
- set/switch active league
- reject invalid league restore
- clear league on logout
- deep-link target league reconciliation

#### Cache
- deterministic cache key generation
- user/league/GW/query scope inclusion
- schema-version mismatch invalidation
- selective scope-based clear behavior

#### Errors
- backend envelope → normalized failure mapping
- transport timeout/offline → expected failure types
- 401/403/404/409/429 mapping consistency

### 18.6 Repository/service integration test focus areas

These are the highest-value tests in the project.

#### Auth flow tests
- authenticated request sends bearer token
- `401 AUTH_INVALID_TOKEN` triggers refresh once
- successful refresh retries original request once
- failed refresh clears session and stops retry loop
- refresh endpoint itself is not recursively refreshed

#### Cache/revalidation tests
- Category A read with no cache → `200` stores body + ETag
- Category A read with ETag and `304` returns cached body
- Category A read with cache + network failure returns cached data result where allowed
- Category A read with missing cache + network failure returns failure
- league change does not reuse previous league cached payload visibly

#### Write/revalidation tests
Examples:
- captain change success revalidates team read
- transfer confirm success revalidates Team + Home + Rankings
- transfer confirm conflict refreshes Team
- mark notification read revalidates notifications list
- profile patch success revalidates `/me`
- team deletion clears cached team and revalidates dependent reads (future / post-MVP)
- profile deletion clears all session/user cache state (future / post-MVP)

These behaviors are already defined in the endpoint and screen docs and should be treated as core contract tests. 

### 18.7 Widget / screen test focus areas

Widget tests should focus on state-driven UX consistency rather than duplicating backend tests.

Recommended coverage:

#### Auth screens
- login validation errors shown inline
- OTP flow preserves input and shows cooldown/retry feedback
- auth failure messaging is localized to the flow

#### Home
- no leagues state
- league available but no competitor state
- selected league state
- cached data shown while refreshing
- refresh failure with previous data keeps content visible

#### Team
- roster shown normally
- no-team state shows builder/create CTA
- GW closed / season locked state disables actions
- mutation failure does not destroy existing screen data

#### Notifications
- empty notifications
- unread filter state
- mark-read action feedback
- invalid target fallback behavior

#### Detail screens
- player detail / match detail / private league detail
- forbidden or missing entity closes/falls back safely

### 18.8 End-to-end / flow test scenarios

The app should maintain a compact but strong set of milestone-level flow tests.

#### Foundation / M1
- app launch → restore session → open authenticated shell
- app launch without refresh token → login flow
- Home → switch league → Team updates to new league
- no-team league → Team builder/create-team flow
- captain/substitute success refreshes Team
- logout clears state and returns to unauthenticated shell

#### M2
- Team → transfer quote → confirm → Team/Home/Rankings refresh
- invalid transfer quote shows violations without breaking screen
- Rankings opens correctly after team creation/transfer changes
- Rules loads from backend payload

#### M3
- Notifications list loads and revalidates
- tapping notification with league target switches league and opens destination
- missing/forbidden notification target falls back safely
- Matches tab → match detail
- Stats → player detail

#### M4
- Profile loads and updates
- My Teams shows teams
- delete team flow updates dependent screens (future / post-MVP)
- profile deletion clears session/cache and exits authenticated shell (future / post-MVP)
- private league create/invite/accept/leave flow refreshes list/detail correctly

These flows align with the mobile implementation order and should be the main regression suite for milestone signoff.

### 18.9 Contract-alignment tests

Because the mobile app consumes a defined API contract, the test plan should explicitly verify contract alignment.

Recommended checks:
- success envelope parsing (`meta` + `data`)
- error envelope parsing (`error.code`, `error.message`, optional details)
- `304 Not Modified` handling for Category A reads
- expected write refresh targets from the endpoint matrix
- special endpoint semantics such as transfer quote returning `200` with `is_valid=false`

This helps catch client drift from the documented backend behavior. 

### 18.10 Deep link and navigation tests

Because navigation depends on auth and league context, it needs explicit tests.

Recommended scenarios:
- protected deep link while logged out → login then resolve
- deep link with valid `league_id` switches active league
- deep link without valid access falls back safely
- detail route opened from stale notification target handles `403` / `404`
- logout resets authenticated navigation stack
- profile deletion resets authenticated navigation stack (future / post-MVP)

### 18.11 Offline and degraded-mode tests

The app is online-first, but should still be tested for degraded behavior.

Recommended scenarios:
- cached Category A screen opened while offline
- uncached screen opened while offline
- pull-to-refresh while offline
- write action attempted while offline
- refresh failure with previous data keeps content visible
- app resume with expired session and no network behaves safely

### 18.12 Manual QA focus areas

Even with automated tests, these areas deserve explicit manual QA:

- multi-league accounts
- no-team league states
- season locked / GW closed states
- deep links from notifications
- private league admin vs member behavior
- auth expiry during active use
- local cache clearing after logout
- local cache clearing after profile deletion (future / post-MVP)
- environment switching between local/test/prod targets
- long lists / pagination behavior for notifications and stats

### 18.13 Test data and environment guidance

Testing should be possible against:
- mocked/fake data for fast automated tests
- local backend for development checks
- hosted test environment for milestone verification

Guidelines:
- automated tests should not depend heavily on live backend availability
- milestone signoff should include hosted-environment verification
- test data should cover:
  - user with no leagues
  - user with multiple leagues
  - user with no team in one league
  - admin/member private league roles
  - expired/invalid token cases
  - rankings not available / GW closed / season locked states

### 18.14 Regression gate per milestone

Each milestone should have a minimum regression gate before it is considered done.

Recommended gate:
- critical unit tests pass
- critical repository tests pass
- milestone flow tests pass
- no known regression in auth restore, league switching, or cache revalidation
- no known regression in logout clearing behavior
- no known regression in profile deletion clearing behavior once implemented

### 18.15 Non-goals

This section does not require:
- exhaustive visual snapshot coverage
- full backend contract retesting in the mobile app
- automation of every minor UI interaction
- real push notification infrastructure in the first test phase
- complex device-farm strategy before the core app is stable

---

## 19. Build, release, and environments

### 19.1 Purpose

This section defines how the mobile app is built and released across environments.

Its goals are to:

- support local development against the current backend setup
- support testing against the hosted test environment
- support eventual production release
- keep environment switching centralized and safe
- separate app versioning from future API versioning
- avoid environment-specific behavior leaking into feature code

This section records both current status and planned direction where appropriate.

### 19.2 Current status

Current project status:

- the backend is developed and tested primarily in a local environment
- a separate hosted test environment exists and should be supported as a normal mobile target
- a future production/live environment is expected
- the current API is **not versioned**
- the mobile app should therefore not hardcode versioned API paths in feature code

Implication:
- environment switching must be handled centrally through app configuration
- if API versioning is introduced later, it must be added centrally through configuration rather than by changing feature code across the app

### 19.3 Supported environments

The app should support at least these environments:

- **local**
  - development against local backend setup

- **staging**
  - hosted test environment for internal/pre-release testing

- **production**
  - live user-facing backend

Notes:
- the current hosted test environment is treated as the staging environment
- feature behavior should remain the same across environments unless environment configuration explicitly changes infrastructure-level behavior

### 19.4 Environment configuration model

Environment selection must be centralized.

Recommended environment config fields:

- `environmentName`
- `apiBaseUrl`
- optional `apiPathPrefix`
- `enableVerboseLogging`
- `enableDevTools`
- `enableCrashReporting`
- `enableAnalytics`

Rules:
- feature code must not hardcode environment URLs
- feature code must not assume versioned or unversioned API paths directly
- environment differences must be expressed through injected configuration, not scattered conditional logic

Examples:
- local may point to current developer backend
- staging points to hosted test backend
- production points to live backend

### 19.5 API path and version handling

Current status:
- the API is currently unversioned

Decision:
- the mobile app must support the current unversioned API structure
- the app must also remain future-ready for a versioned path structure later

Rules:
- requests should use the configured API root/path for the selected environment
- no feature should embed assumptions like `/v1/...` directly
- if versioning is introduced later, it should be applied centrally through environment/configuration

This means:
- app versioning starts from day one
- API versioning remains a future backend/platform decision

### 19.6 Build targets / flavors

The app should support separate build targets for at least:

- **debug local**
- **debug staging**
- **release production**

Recommended later extension:
- **release staging** for broader internal testing if needed

Goals:
- reduce risk of pointing a production build at the wrong backend
- make local/staging/prod behavior explicit
- allow safe tester distribution

### 19.7 App identity strategy

Recommended decision:
- use separate app identities for **staging** and **production**

Examples:
- separate Android application IDs
- separate iOS bundle identifiers

Reason:
- staging and production apps can coexist on the same device
- testers can validate staging safely without overwriting the production install
- the risk of distributing a non-production build as production is reduced

Local debug builds may follow the staging/development identity strategy.

### 19.8 Release channel strategy

Recommended release channels:

#### Local
- direct developer builds
- emulator/simulator/device testing
- no public distribution

#### Staging
- internal tester distribution
- pre-release validation against hosted test backend
- acceptance checks before public release

#### Production
- public store release
- production backend only
- restricted logging and no development tools

The exact platform-specific distribution tools/process can be finalized later.

### 19.9 Build and release process

Initial approach:
- manual or semi-manual builds are acceptable in the early phase

Later direction:
- CI/CD may be introduced after the core app architecture and milestone flows are stable

Rules:
- build/release process should not block initial development
- production release should still require clear environment identity and safe signing practices
- staging and production builds must be clearly distinguishable

### 19.10 Signing and publishing ownership

This area is important but may remain operationally open for now.

Topics to define later:
- who owns Android signing credentials
- who owns Apple signing/provisioning setup
- where signing credentials are stored securely
- who is allowed to publish staging builds
- who is allowed to publish production builds

Architecture rule:
- the app should be structured so these decisions can be added later without changing feature code

### 19.11 App versioning strategy

Recommended decision:
- use semantic app versioning for the mobile app from the beginning

Recommended format:
- `MAJOR.MINOR.PATCH`

Examples:
- `1.0.0`
- `1.1.0`
- `1.1.1`

Meaning:
- **MAJOR** for significant breaking app-level changes
- **MINOR** for new backward-compatible features
- **PATCH** for fixes and small improvements

This is separate from backend/API versioning.

### 19.12 Version/build metadata exposure

The app should expose safe version/build metadata in a user-visible location such as Settings/About.

Useful metadata:
- app version
- build number
- environment label in non-production builds

Rules:
- version/build metadata is safe to expose
- environment label should be visible in staging/debug builds
- secrets and internal credentials must never be exposed

### 19.13 Logging and diagnostics by release type

#### Local / debug
- verbose logging allowed
- developer tools/debug reset actions may be available
- no token logging

#### Staging
- reduced but still useful diagnostics
- safe environment label visible
- no token logging
- no unrestricted payload dumping

#### Production
- minimal safe logging
- no developer tools
- no verbose network diagnostics
- no sensitive payload logging

Across all release types:
- never log tokens
- never expose raw auth headers
- never include secrets in user-visible diagnostics

### 19.14 Crash reporting and analytics

Current status:
- vendor/tool choice is still open

Recommended approach:
- keep architecture ready for optional integration
- do not make analytics/crash-reporting a blocker for the first implementation milestones

Rules:
- analytics/crash reporting must respect the security/privacy rules
- tokens and sensitive payloads must never be included
- production instrumentation should be more constrained than debug diagnostics

### 19.15 Environment transition and verification

The app should be able to move between:
- local backend
- hosted staging backend
- production backend

with centralized configuration only.

Expected requirement:
- moving from local to staging should require environment/config changes, not architectural rewrites
- staging should behave as a realistic mobile test target, not a one-off workaround
- production release should reuse the same architecture and feature code as staging

### 19.16 Release readiness checks

Before a production release, at minimum confirm:

- production environment points to the correct backend
- staging and production builds are clearly separated
- logging is restricted appropriately
- auth/session flows behave correctly in production configuration
- cache clearing and logout still behave safely
- profile deletion clearing behavior is verified once that endpoint is implemented
- app version/build metadata is correct
- major milestone regression tests pass

### 19.17 Non-goals

This section does not define:
- detailed CI/CD implementation
- full app store publishing procedure
- exact signing credential storage process
- final analytics/crash-reporting vendor selection
- legal release/compliance documentation

---

## 20. Implementation order

Implementation should follow a vertical-slice approach:
- establish the shared app foundation first
- then deliver feature groups in milestones
- each milestone should end in a testable, navigable app state
- avoid building all screens first and integrating data later

The feature milestone order follows the current mobile integration guide, with one added foundation step.

### 20.1 Foundation milestone (M0)

Purpose:
Create the technical base required by all later milestones.

Scope:
- Flutter project scaffold
- environment configuration
- app shell bootstrap
- dependency wiring
- Dio client setup
- auth/session controller
- secure storage integration
- local cache store setup
- go_router root navigation
- Riverpod provider structure
- shared error model
- basic theming/layout primitives
- debug logging and developer reset tools (debug only)

Deliverables:
- app launches successfully
- authenticated vs unauthenticated shell routing works
- API base URL is environment-driven
- refresh token can be stored/read securely
- one sample authenticated request can be executed through the repository/API stack
- Category A cache read/write plumbing exists for at least one test endpoint

Exit criteria:
- no screen performs raw HTTP directly
- session restore path is wired
- route guards function
- cache key generation mechanism exists
- app can switch between local/test/prod config centrally

### 20.2 Milestone 1 (M1) — Auth + Home + Team

Scope:
- Login
- Registration + OTP verification
- Password reset
- Session restore / logout
- Home
- League selector behavior
- Team
- Initial team creation flow
- Captain/substitute actions
- Shared active league context

Why this comes first:
- auth/session is required by almost everything
- Home establishes the league selector and entry state
- Team is the primary league-scoped gameplay screen
- Home and Team together validate the core app shell, league context, Category A caching, and write-triggered revalidation

Primary endpoints:
- `/auth/register`
- `/auth/otp/send`
- `/auth/otp/verify`
- `/auth/login`
- `/auth/token/refresh`
- `/auth/logout`
- `/auth/password/forgot`
- `/auth/password/reset`
- `GET /home`
- `GET /home?league_id={league_id}`
- `GET /leagues/{league_id}/team`
- `GET /leagues/{league_id}/team/builder`
- `POST /leagues/{league_id}/team`
- `POST /leagues/{league_id}/team/captain`
- `POST /leagues/{league_id}/team/substitute`

Acceptance checks:
- user can authenticate and restore session after app restart
- Home can load with and without a selected league
- switching league updates Team correctly
- no-team state routes into team creation flow
- captain/substitute writes trigger Team refresh correctly
- logout clears session and cached user data

### 20.3 Milestone 2 (M2) — Transfers + Rankings + Rules

Scope:
- Transfer Market
- Player Detail modal (from Team / Market path at minimum)
- Transfer quote + confirm flow
- Rankings
- Rules

Why this comes next:
- transfers depend on Team and league context
- transfer confirm refreshes Home, Team, and Rankings
- Rankings is a natural follow-up once the user has a team
- Rules is simple and useful, and helps avoid hardcoded gameplay assumptions in the app

Primary endpoints:
- `GET /leagues/{league_id}/market/players`
- `GET /leagues/{league_id}/players/{player_id}`
- `POST /leagues/{league_id}/transfers/quote`
- `POST /leagues/{league_id}/transfers/confirm`
- `GET /leagues/{league_id}/fantasy`
- `GET /leagues/{league_id}/rules`

Acceptance checks:
- market list loads with filtering/sorting baseline
- player detail opens from Team/Market
- quote handles `is_valid=false` without breaking flow
- confirm updates Team and revalidates dependent reads
- Rankings loads and reflects post-transfer/team-creation refresh logic
- Rules loads from backend and is not hardcoded client-side

### 20.4 Milestone 3 (M3) — Notifications + Matches / Table / Stats

Scope:
- Notifications inbox
- mark-as-read / read-all
- notification target navigation
- Matches list
- Match detail
- Table
- Stats
- Player Detail modal entry from stats/matches

Why this comes after M2:
- these are important, but less critical than the core “play the game” loop
- notifications rely on established auth, navigation, and deep-link handling
- matches/stats benefit from already having league context, routing, and modal conventions in place

Primary endpoints:
- `GET /notifications`
- `POST /notifications/{notification_id}/read`
- `POST /notifications/read-all`
- `GET /leagues/{league_id}/matches`
- `GET /leagues/{league_id}/matches/{match_id}`
- `GET /leagues/{league_id}/table`
- `GET /leagues/{league_id}/stats/players`
- `GET /leagues/{league_id}/players/{player_id}`

Acceptance checks:
- notifications list caches and revalidates correctly
- opening a notification can switch league context before navigation when needed
- failed targets fall back safely
- matches/table/stats are reachable from the Matches tab
- player detail works consistently from matches/stats paths
- Home unread badge and Notifications unread count stay consistent

### 20.5 Milestone 4 (M4) — More / Account + Private Leagues

Deletion flows (`DELETE /leagues/{league_id}/team`, `DELETE /me`) are part of the target-state architecture but are currently deferred from MVP unless the backend endpoints are implemented before M4 delivery.

Scope:
- More hub polish/completion
- Profile
- Settings
- Contact / Support
- My Teams
- Team deletion
- Profile deletion
- Private Leagues list
- Private League detail
- Invite search / invite
- accept / decline invites
- leave / admin actions as defined for v1

Why this comes last:
- account screens are important but not core to first playable loop
- private leagues have broader cross-feature effects: rankings refresh, notifications, league-scoped social flows
- by this stage the app already has the navigation, auth, refresh, and league-context machinery needed for them

Primary endpoints:
- `GET /me`
- `PATCH /me`
- `GET /me/teams`
- `DELETE /leagues/{league_id}/team` *(future / post-MVP unless backend endpoint is implemented before M4 signoff)*
- `DELETE /me` *(future / post-MVP unless backend endpoint is implemented before M4 signoff)*
- `POST /contact`
- `GET /leagues/{league_id}/private-leagues`
- `POST /leagues/{league_id}/private-leagues`
- `GET /leagues/{league_id}/private-leagues/{privateleague_id}`
- `GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q={q}&limit={limit?}`
- `POST /leagues/{league_id}/private-leagues/{privateleague_id}/invite`
- `GET /leagues/{league_id}/private-leagues/invites`
- `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/accept`
- `POST /leagues/{league_id}/private-leagues/invites/{invite_id}/decline`
- related admin/member actions kept for v1 scope as implemented

Acceptance checks:
- profile/settings updates revalidate `/me`
- delete team flow updates dependent screens once backend endpoint is implemented
- profile deletion clears session/cache and exits authenticated shell once backend endpoint is implemented
- private league actions refresh private league lists/details and rankings as required
- invite-related notifications/deep links resolve correctly

### 20.6 Cross-milestone quality gates

The following must remain true after every milestone:

- auth refresh still works
- active league context remains consistent across tabs
- Category A reads still revalidate correctly
- write actions still trigger required read refreshes
- logout/profile deletion still clear local state safely
- no feature bypasses repository/networking conventions

### 20.7 Suggested implementation style within each milestone

Within a milestone, implement in this order:

1. route + screen shell
2. repository/API integration
3. loading/empty/error states
4. write actions
5. revalidation/invalidation behavior
6. polish / UX details
7. tests

This keeps every feature vertically integrated before moving on.

### 20.8 Explicit deferrals

The following are intentionally not required for the first implementation pass:

- push notification delivery wiring
- analytics/crash reporting vendor integration
- advanced offline support
- background sync jobs
- tablet-specific layout optimization
- animation/polish beyond core usability

---

## 21. Open questions / later decisions

This section tracks architecture, delivery, and operational decisions that are intentionally left open for now.

Its purpose is to:
- keep unresolved items visible
- separate confirmed architecture from later operational choices
- avoid losing important decisions that do not block current implementation
- support timely decision-making before release-critical milestones

Items in this section should be reviewed regularly and either:
- resolved and moved into the relevant architecture section, or
- kept open with an updated status and owner

### 21.1 Status labels

Recommended statuses:

- **Open**
  - not yet decided

- **Proposed**
  - preferred direction exists, but not yet formally confirmed

- **Later**
  - intentionally postponed because it is not needed yet

- **Resolved**
  - decision made and reflected in the relevant section of this document

Resolved items may remain listed temporarily for traceability, but should clearly point to the section where the final decision is recorded.

### 21.2 Current open questions

### OQ-001 Environment naming

**Status:** Resolved

**Proposal:**
- `local`
- `staging`
- `production`

**Notes:**
- the current hosted test environment is treated as `staging`

---

### OQ-002 Build flavor matrix

**Status:** Resolved

**Proposal:**
- `debug local`
- `debug staging`
- `release production`
- optional `release staging` later if needed

**Reason:**
- enough separation for development, test, and release without overcomplicating early setup

---

### OQ-003 App identity separation

**Status:** Resolved

**Proposal:**
- use separate staging and production app identities
- allow staging and production installs to coexist on the same device

**Examples:**
- separate Android application IDs
- separate iOS bundle identifiers

**Reason:**
- safer tester workflow
- reduces risk of mixing staging and production builds

---

### OQ-004 Signing and publishing ownership

**Status:** Open

**To decide:**
- who owns Android signing credentials
- who owns Apple signing/provisioning setup
- where credentials are stored securely
- who can publish staging builds
- who can publish production builds

**Why it matters:**
- required before production-grade release setup
- should be decided before external release preparation

---

### OQ-005 Release process maturity

**Status:** Later

**Current assumption:**
- early builds may be manual or semi-manual
- CI/CD can be introduced later

**Open point:**
- when to move from manual builds to automated pipeline support

---

### OQ-006 Crash reporting vendor

**Status:** Open

**To decide:**
- which crash reporting solution to use
- in which milestone it should be introduced
- what diagnostic data is allowed to be sent

**Constraint:**
- must follow the security/privacy rules already defined in this architecture

---

### OQ-007 Analytics vendor and scope

**Status:** Open

**To decide:**
- whether analytics is needed in the first implementation phase
- which vendor/tool to use
- which events are worth tracking
- what privacy constraints apply

**Constraint:**
- analytics must not become a blocker for core implementation milestones

---

### OQ-008 API versioning rollout

**Status:** Open

**Current status:**
- the API is currently unversioned

**Decision rule already agreed:**
- if API versioning is introduced later, the mobile app must adopt it centrally via configuration
- feature code must not hardcode versioned paths

**Open point:**
- whether and when backend API versioning should be introduced

---

### OQ-009 Staging release distribution method

**Status:** Open

**To decide:**
- how staging builds are distributed to testers
- whether staging should be installable alongside production from the start
- who is included in the tester group
- how staging build availability is communicated

---

### OQ-010 Build/release checklist ownership

**Status:** Open

**To decide:**
- who approves milestone test completion
- who approves staging signoff
- who approves production release readiness
- where the checklist is maintained

---

### OQ-011 Push notification implementation timing

**Status:** Later

**To decide:**
- in which milestone push delivery should be added
- whether push wiring belongs to MVP or post-MVP hardening
- what tester strategy is needed before production rollout

**Note:**
- navigation and notification target handling are already part of the architecture, even if push delivery wiring is added later

---

### OQ-012 Deep-link URL format

**Status:** Open

**To decide:**
- canonical deep-link URL structure
- how league-scoped targets encode `league_id`
- fallback behavior for incomplete or stale targets

**Why it matters:**
- needed before deep-link and notification target implementation is finalized

---

### OQ-013 Local developer environment guide

**Status:** Later

**To decide:**
- whether to create a separate setup guide for local Flutter + backend development
- whether emulator/device-specific connection instructions should be documented centrally

**Reason:**
- useful for onboarding, but not required to complete architecture decisions

---

### OQ-014 Release readiness checklist document

**Status:** Later

**To decide:**
- whether release readiness should live inside this architecture file or in a separate operational checklist
- what minimum gates are required for staging and production release

---

### OQ-015 Post-MVP architecture review point

**Status:** Later

**To decide:**
- after which milestone the team should review whether:
  - current cache strategy remains sufficient
  - project structure still fits the codebase
  - analytics/crash reporting should be added
  - CI/CD should be introduced
  - API versioning should be prioritized

### 21.3 Review rule

This section should be reviewed:
- before starting a new major milestone
- before staging release setup
- before production release preparation

When an item is resolved:
1. update its status
2. record the final decision in the relevant architecture section
3. remove or archive the open question if it no longer needs tracking

### 21.4 Non-goals

This section does not:
- replace formal project planning
- replace release checklists
- replace implementation task tracking
- require all open questions to be resolved before coding starts

---

## 22. Appendix

### Appendix A: endpoint scope table
Small summary table:
- global
- user-scoped
- league-scoped
- league + GW-scoped

### Appendix B: cache key examples
Concrete examples for the most important endpoints.

### Appendix C: write -> revalidation examples
Examples:
- captain change
- transfer confirm
- mark notification read
- profile update
- team deletion (future / post-MVP)
