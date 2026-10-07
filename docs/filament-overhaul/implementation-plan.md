# Filament Overhaul — Implementation Plan

## Phase 1 — Install/configure Filament without changing behavior

**Goal:** Add the Filament panel infrastructure while preserving every existing route and UI.

**Create/modify:** composer dependency/lockfile, Filament panel provider, bootstrap/provider registration as required, documentation.

**Dependencies:** Confirm Laravel 12/PHP 8.3 compatibility and lock a compatible Filament 5 release.

**Tests:** Composer validation, Laravel boot, existing test suite, route snapshot/list comparison, Vite build.

**Acceptance:** Existing API and Vue SPA still work; Filament login/panel boots independently; no legacy file deleted.

**Rollback:** Revert only Filament package/provider commits and composer lock changes.

## Phase 2 — Design system, theme, navigation and layout

**Goal:** Establish the BluePrint HR Filament visual system and domain navigation.

**Create/modify:** panel theme, navigation configuration, shared components/icons/labels.

**Dependencies:** Phase 1.

**Tests:** Panel access by role; responsive smoke checks; accessibility checks.

**Acceptance:** Authenticated users see coherent role-aware navigation without losing any prior API/SPA route.

**Rollback:** Revert theme/navigation commits; leave Phase 1 infrastructure intact.

## Phase 3 — Read-only dashboards, widgets and infolists

**Goal:** Reproduce existing dashboard information without altering business logic.

**Create/modify:** dashboard page, KPI widgets, employee/payroll/leave/audit infolists.

**Dependencies:** Phase 2; existing controllers/services remain source of truth until domain services are safely shared.

**Tests:** Metric parity, tenant isolation, employee self-service scoping.

**Acceptance:** Dashboard figures match existing API output for the same fixture data.

**Rollback:** Disable only the new panel dashboard route; retain legacy dashboard.

## Phase 4 — Tables, filters, actions and exports

**Goal:** Introduce Filament tables for employees, organization, leave and payroll.

**Create/modify:** Filament resources, tables, filters, bulk actions, exports.

**Dependencies:** Phase 3.

**Tests:** CRUD parity, pagination/search/filter parity, export format/authorization, tenant isolation.

**Acceptance:** Standard HR lists are faster to navigate and preserve existing actions/contracts.

**Rollback:** Keep legacy UI and API as fallback; remove only new resource registrations.

## Phase 5 — Forms, workflows, uploads and approvals

**Goal:** Implement robust Filament forms and workflow actions.

**Create/modify:** forms, wizards, actions, relation managers, upload handling where repository data supports it.

**Dependencies:** Phase 4.

**Tests:** Validation, transactions, status transitions, file authorization, leave balance concurrency, payroll processing.

**Acceptance:** Complex workflows cannot bypass existing policies/business rules.

**Rollback:** Re-route workflow entry points to the legacy API/UI; retain additive schema changes only when backward-compatible.

## Phase 6 — Notifications, global search and reporting

**Goal:** Add discovery and collaboration capabilities without replacing existing audit/event semantics.

**Create/modify:** notification center, database notifications where appropriate, global search, report actions.

**Dependencies:** Phase 5.

**Tests:** Notification authorization, read/unread behavior, search tenant scoping, report permissions.

**Acceptance:** Search and notifications expose only records the user can access.

**Rollback:** Remove new notification/search UI registrations while preserving existing audit/report routes.

## Phase 7 — Frontend alignment/migration

**Goal:** Gradually reduce duplicate admin UI only after proven feature parity.

**Create/modify:** Vue styling/token mapping and, only when verified, routing/entry-point integration.

**Dependencies:** Phases 1–6.

**Tests:** Full legacy regression suite, Vite build, browser smoke tests.

**Acceptance:** Every retired legacy screen has a verified Filament replacement and a documented URL compatibility strategy.

**Rollback:** Restore legacy route/view entry point; do not delete source until a later cleanup release.

## Phase 8 — Testing, accessibility, performance, security and documentation

**Goal:** Production hardening.

**Create/modify:** regression tests, authorization tests, performance/index review, accessibility documentation, architecture docs.

**Dependencies:** All prior phases.

**Tests:** Full PHPUnit suite, static analysis, Pint, npm build/audit, migration smoke, browser tests, security review.

**Acceptance:** No feature/role regression; all critical HR workflows tested; docs explain extension conventions and rollback.

**Rollback:** Release-level rollback to previous application image/commit; migrations must use additive forward-fix strategy for production data.

## Package/dependency strategy

- Keep Laravel 12 and PHP 8.3.
- Adopt Filament 5.x because current compatibility evidence supports Filament 5 on Laravel 12/PHP 8.3; Filament 5 uses Livewire 4. citeturn4search2turn4search5
- Do not upgrade Laravel, PHP, Vue, Vite or Bootstrap as part of the Filament adoption.
- Do not add Spatie Permission or another authorization package unless the existing policy model proves insufficient. Existing `Role` enum + policies remain authoritative.
- Avoid third-party Filament plugins until core functionality is stable. Prefer native Filament features.
- Pin/lock dependency versions through Composer lock and verify security advisories before merging.

## Rollback principles

1. No destructive migration for the UI overhaul.
2. New columns/tables, if later required, must be additive and nullable/defaulted where possible.
3. Existing API routes remain registered.
4. Existing Blade/Vue files remain until replacement verification is documented.
5. Each phase is a separate commit/PR-sized change.
6. Production rollback uses the previous known-good application image/commit; database rollback is never assumed safe for destructive schema changes.
