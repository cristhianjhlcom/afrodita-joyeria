# Afrodita Joyeria - Implementation Checklist

This file tracks completed and pending work for the admin-first eCommerce implementation.
Update it at the end of each phase/session.

## Project Goal
Build an admin-first jewelry eCommerce platform synced from the main store, with:
- Main store as source of truth for catalog, inventory, and official orders
- This project as an independent storefront/admin projection
- Livewire + Flux admin interface

## Completed
- [x] Documented integration requirements for main store
  - `docs/main-store-requirements.md`
- [x] Initialized repository and baseline setup committed
- [x] Added eCommerce domain schema, models, factories, and seeders
  - Brands, categories, products, variants, images, orders, order items, sync runs, brand whitelist
- [x] Added sync infrastructure
  - `main-store:sync` command
  - Sync jobs (brands, categories, products, variants, images, inventory, orders)
  - Main store API client + sync service
  - Scheduler every 5 minutes
- [x] Added admin access controls
  - Admin middleware + protected admin routes
- [x] Added admin module pages (Livewire + Flux)
  - Dashboard, Brands, Categories, Products, Inventory, Orders Mirror
- [x] Added dedicated admin layout with grouped sidebar
  - Overview / Catalog / Operations
- [x] Fixed MySQL migration ordering issues
  - `orders` before `order_items`
  - `product_variants` before `product_images`
- [x] Created dev admin user
  - `dev-admin@afrodita.local`

## In Progress
- [x] Phase 2: deeper admin operations and UX hardening
- [x] Phase 3: public storefront implementation + main-store API contract alignment

## Pending / Next Phases
- [x] Add product detail page with variant/image drill-down actions
- [x] Add sync run detail page and error inspection UI
- [x] Add explicit stale-data warnings (e.g. last successful sync threshold)
- [x] Add retry/trigger actions per resource (brands/categories/products/etc.)
- [x] Add policies/gates for finer permissions (if needed beyond admin/customer)
- [x] Add integration tests for admin filtering and pagination behavior
- [x] Add performance guardrails (indexes review, query optimization checks)
- [x] Optional: notification/alerting for repeated sync failures

## Environment / Ops Checklist
- [x] `.env` configured for MySQL
- [x] `MAIN_STORE_BASE_URL` configured
- [x] `MAIN_STORE_TOKEN` configured
- [x] Queue worker running in dev (`php artisan queue:work` or composer dev)
- [x] Scheduler running in environment
- [x] Migrations up to date

## Session Log Template
Use this format for each session:

### Session YYYY-MM-DD
- Goal:
- Changes made:
- Files touched:
- Tests run:
- Result:
- Next action:

## Current Branch Snapshot
- Active branch: `feat/main-store-sync-v1-alignment`
- Key recent commits:
  - `4654f01` feat: add dedicated admin layout with grouped sidebar
  - `a984681` fix: run product_variants migration before product_images foreign key
  - `11b913e` fix: run orders migration before order_items foreign key
  - `0378a74` feat: add livewire admin catalog and operations pages
  - `8d4de17` feat: add main-store sync pipeline and admin access controls
  - `e8d87b0` feat: add ecommerce domain schema models and factories
  - `86016e2` chore: add initial laravel project setup
  - `57566a7` docs: add main store integration requirements for catalog sync

### Session 2026-03-13
- Goal: Align main-store sync and order push with Sync API v1 updates.
- Changes made:
- Added order-items sync resource/job and updated sync pipeline for new fields.
- Mapped variant merchant fields, sale windows, province cost, and string image ids.
- Updated order push payload to new POST contract and added order fetch client helper.
- Added migrations for new sync fields and updated checklists.
- Files touched:
- `app/Services/MainStore/MainStoreSyncService.php`
- `app/Services/MainStore/MainStoreApiClient.php`
- `app/Services/Storefront/OrderPushService.php`
- `app/Console/Commands/SyncMainStoreCommand.php`
- `app/Jobs/SyncOrderItemsJob.php`
- `app/Models/ProductVariant.php`
- `app/Models/Province.php`
- `app/Models/OrderItem.php`
- `config/services.php`
- `database/migrations/2026_03_13_064855_add_sync_v1_fields_to_product_variants_table.php`
- `database/migrations/2026_03_13_064859_add_cost_to_provinces_table.php`
- `database/migrations/2026_03_13_064901_add_external_id_to_order_items_table.php`
- `tests/Feature/Console/SyncMainStoreCommandTest.php`
- `tests/Feature/MainStore/OrderPushCommandsTest.php`
- `docs/main-store-requirements.md`
- `docs/project-checklist.md`
- Tests run:
- `php artisan test --compact tests/Feature/Console/SyncMainStoreCommandTest.php tests/Feature/MainStore/OrderPushCommandsTest.php tests/Feature/Storefront/CheckoutFlowTest.php`
- Result: Passing.
- Next action:
- Review main store API implementation and run the full test suite when ready.

### Session 2026-02-19
- Goal: Continue Phase 2 with first operations UX improvement for admin catalog workflows.
- Changes made:
- Added admin product detail page with variant and image drill-down tables.
- Added products table `View` action to navigate to detail page.
- Added admin route `admin.products.show`.
- Updated admin sidebar active state to include product detail pages.
- Added feature tests for admin product detail rendering and listing action visibility.
- Files touched:
- `resources/views/pages/admin/⚡product-detail.blade.php`
- `resources/views/pages/admin/⚡products.blade.php`
- `resources/views/layouts/admin/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/Admin/AdminModulesAccessTest.php`
- `tests/Feature/Admin/ProductDetailViewTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminModulesAccessTest.php tests/Feature/Admin/ProductDetailViewTest.php tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be confirmed after final run for this session).
- Next action:
- Implement sync run detail page and error inspection UI.

### Session 2026-02-19 (Testing Data Prep)
- Goal: Prepare richer local data generation before external main-store API integration.
- Changes made:
- Added practical factory states for common scenarios:
- Brands/categories active vs inactive.
- Product status presets (published, in stock, out of stock).
- Variant stock/sale presets (on sale, low stock, out of stock, inactive).
- Order status presets.
- Sync run status presets (completed, failed, running).
- Added `DevelopmentTestingSeeder` to generate realistic local data:
- Dev admin + customers.
- Brand whitelist split (enabled/disabled).
- Category trees (parent/subcategory).
- Products + variants + images with stock/sale variety.
- Orders + order items linked to seeded variants.
- Sync run history.
- Added test coverage for the seeder output.
- Files touched:
- `database/factories/BrandFactory.php`
- `database/factories/BrandWhitelistFactory.php`
- `database/factories/CategoryFactory.php`
- `database/factories/ProductFactory.php`
- `database/factories/ProductVariantFactory.php`
- `database/factories/OrderFactory.php`
- `database/factories/SyncRunFactory.php`
- `database/seeders/DevelopmentTestingSeeder.php`
- `tests/Feature/Database/DevelopmentTestingSeederTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Database/DevelopmentTestingSeederTest.php`
- `php artisan test --compact tests/Feature/Admin/AdminModulesAccessTest.php tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing.
- Next action:
- Run `php artisan db:seed --class=Database\\\\Seeders\\\\DevelopmentTestingSeeder` in local for API-less UI/flow testing.

### Session 2026-02-19 (Routing + Local Bootstrap)
- Goal: Make local bootstrap smoother and align routing with admin-first flow.
- Changes made:
- Removed `/dashboard` route and standardized app home to `/`.
- Added simple public homepage placeholder for upcoming storefront routes.
- Removed legacy `welcome` view.
- Updated Fortify post-auth redirect home path to `/`.
- Updated UI links that still pointed to removed dashboard route.
- Added local-only hook to auto-run `DevelopmentTestingSeeder` after `php artisan migrate:fresh` when `--seed` is not provided.
- Updated auth/home tests to reflect new route behavior.
- Files touched:
- `routes/web.php`
- `resources/views/home.blade.php`
- `resources/views/welcome.blade.php` (deleted)
- `config/fortify.php`
- `resources/views/layouts/app/header.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/layouts/admin/sidebar.blade.php`
- `app/Providers/AppServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/RegistrationTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/ExampleTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/EmailVerificationTest.php tests/Feature/Admin/AdminAccessTest.php tests/Feature/Database/DevelopmentTestingSeederTest.php`
- Result: Passing.

### Session 2026-02-19 (Phase 2 - Sync Run Inspection)
- Goal: Add sync run detail and error inspection tooling for admin operations.
- Changes made:
- Added `admin.sync-runs.show` route for drill-down by sync run.
- Added dashboard table action to open sync run detail.
- Added Livewire page to inspect run summary, error messages, and raw `meta` payload.
- Added access tests for admin/customer authorization on sync run detail.
- Added feature test for detail rendering and dashboard action link.
- Files touched:
- `routes/web.php`
- `resources/views/pages/admin/⚡dashboard.blade.php`
- `resources/views/pages/admin/⚡sync-run-detail.blade.php`
- `tests/Feature/Admin/AdminModulesAccessTest.php`
- `tests/Feature/Admin/SyncRunDetailViewTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminModulesAccessTest.php tests/Feature/Admin/SyncRunDetailViewTest.php tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Add stale-data warning threshold on dashboard.

### Session 2026-02-19 (Phase 2 - Stale Sync Alerts)
- Goal: Add explicit stale-data warnings in admin dashboard using sync checkpoint thresholds.
- Changes made:
- Added `services.main_store.stale_threshold_minutes` config and environment variable.
- Added dashboard sync health computation for required resources:
- Missing successful sync runs detection.
- Stale sync checkpoint detection by threshold.
- Added warning callout for stale/missing syncs and success callout for healthy status.
- Added feature tests for stale and healthy dashboard states.
- Files touched:
- `.env.example`
- `config/services.php`
- `resources/views/pages/admin/⚡dashboard.blade.php`
- `tests/Feature/Admin/AdminDashboardSyncHealthTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminDashboardSyncHealthTest.php tests/Feature/Admin/AdminModulesAccessTest.php tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Add retry/trigger actions per resource from admin UI.

### Session 2026-02-19 (Phase 2 - Resource Trigger Actions)
- Goal: Add per-resource sync trigger/retry controls from admin dashboard.
- Changes made:
- Added per-resource sync control table to admin dashboard:
- Queue action for each resource.
- Retry action for resources whose latest run failed.
- Added command/resource mapping so `images` command is tracked against `variant-images` sync runs.
- Added server-side guard to ignore unsupported resource trigger input.
- Added feature tests for queue dispatch, unsupported resource guard, and retry visibility.
- Files touched:
- `resources/views/pages/admin/⚡dashboard.blade.php`
- `tests/Feature/Admin/AdminDashboardResourceSyncActionsTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminDashboardResourceSyncActionsTest.php tests/Feature/Admin/AdminDashboardSyncHealthTest.php tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Add integration tests for admin filtering and pagination behavior.

### Session 2026-02-19 (Phase 2 - Filter/Pagination Integration Tests)
- Goal: Add integration-level coverage for admin list filtering and pagination behaviors.
- Changes made:
- Added admin feature tests covering:
- Brands search filter and pagination.
- Products filtering by search + brand + subcategory.
- Inventory low-stock filter behavior.
- Orders search filter and pagination.
- Files touched:
- `tests/Feature/Admin/AdminFilteringPaginationTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminFilteringPaginationTest.php tests/Feature/Admin`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Evaluate performance guardrails (indexes/query checks).

### Session 2026-02-19 (UI Polish - Inventory Filter Control)
- Goal: Improve the UX of the low-stock filter input in inventory module.
- Changes made:
- Replaced raw checkbox control with inline `flux:field` + `flux:switch`.
- Improved responsive alignment and control readability.
- Files touched:
- `resources/views/pages/admin/⚡inventory.blade.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminFilteringPaginationTest.php`
- Result: Passing.

### Session 2026-02-19 (Phase 2 - Performance Guardrails)
- Goal: Improve query performance safety for admin/sync workflows.
- Changes made:
- Added dedicated migration with indexes for high-frequency query patterns:
- Brand/category name sorting/filter support.
- Product filtering by brand/subcategory + recency.
- Inventory low-stock + recency.
- Orders recency sorting.
- Sync run drill-down and checkpoint health queries.
- Optimized orders search query to use exact indexed match when numeric.
- Fixed inventory filter query grouping so search + low-stock constraints combine correctly.
- Added integration test coverage for combined inventory filters.
- Files touched:
- `database/migrations/2026_02_19_233814_add_performance_indexes_for_admin_queries.php`
- `resources/views/pages/admin/⚡orders.blade.php`
- `resources/views/pages/admin/⚡inventory.blade.php`
- `tests/Feature/Admin/AdminFilteringPaginationTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminFilteringPaginationTest.php tests/Feature/Admin tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Optional repeated sync failure alerting.

### Session 2026-02-19 (Phase 2 - Repeated Failure Alerts)
- Goal: Add alerting for repeated sync failures to support ops visibility.
- Changes made:
- Added configurable failure threshold:
- `services.main_store.failure_alert_threshold`
- `MAIN_STORE_FAILURE_ALERT_THRESHOLD`
- Added dashboard repeated-failure detection by resource:
- Counts consecutive failed runs from newest run backward.
- Displays danger callout when threshold is reached/exceeded.
- Added feature tests for alert shown/not shown scenarios.
- Files touched:
- `.env.example`
- `config/services.php`
- `resources/views/pages/admin/⚡dashboard.blade.php`
- `tests/Feature/Admin/AdminDashboardFailureAlertsTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminDashboardFailureAlertsTest.php tests/Feature/Admin/AdminDashboardSyncHealthTest.php tests/Feature/Admin/AdminDashboardResourceSyncActionsTest.php`
- `php artisan test --compact tests/Feature/Admin`
- Result: Passing.
- Next action:
- Decide whether to introduce finer-grained policies beyond admin/customer role checks.

### Session 2026-02-19 (Phase 2 - Policy/Gate Refinement)
- Goal: Introduce finer-grained authorization controls beyond plain admin middleware.
- Changes made:
- Added model policies for admin resources:
- `BrandPolicy`, `CategoryPolicy`, `ProductPolicy`, `ProductVariantPolicy`, `OrderPolicy`, `SyncRunPolicy`.
- Added explicit policy abilities for sensitive actions:
- `toggleWhitelist` on brands.
- `trigger` on sync runs.
- Added route-level policy middleware via `->can(...)` for all admin module routes.
- Added action-level Livewire authorization guards for:
- Brand whitelist toggles.
- Full/resource sync queue actions.
- Added tests for policy ability matrix and Livewire action authorization hardening.
- Files touched:
- `app/Policies/BrandPolicy.php`
- `app/Policies/CategoryPolicy.php`
- `app/Policies/ProductPolicy.php`
- `app/Policies/ProductVariantPolicy.php`
- `app/Policies/OrderPolicy.php`
- `app/Policies/SyncRunPolicy.php`
- `routes/web.php`
- `resources/views/pages/admin/⚡brands.blade.php`
- `resources/views/pages/admin/⚡dashboard.blade.php`
- `tests/Feature/Admin/AdminPolicyAuthorizationTest.php`
- `tests/Feature/Admin/AdminLivewireActionAuthorizationTest.php`
- Tests run:
- `php artisan test --compact tests/Feature/Admin/AdminPolicyAuthorizationTest.php tests/Feature/Admin/AdminLivewireActionAuthorizationTest.php tests/Feature/Admin tests/Feature/Console/SyncMainStoreCommandTest.php`
- Result: Passing (to be re-confirmed after final test run).
- Next action:
- Phase 2 completion review and handoff prep for Phase 3.

## Phase 2 Summary (Completed)
- Admin area is fully functional and hardened:
- Dashboard, brands, categories, products, product detail, inventory, orders mirror, sync run detail.
- Operational controls implemented:
- Full sync trigger, per-resource queue/retry, stale-data warning, repeated-failure alerts.
- Security and access hardened:
- Admin middleware + granular policies + route ability checks + Livewire action guards.
- Data and local bootstrap prepared:
- Rich factories, development seeder, and local auto-seed workflow after `migrate:fresh`.
- Performance guardrails added:
- Query/index improvements and filtering correctness fixes.
- Coverage status:
- Admin feature tests and regression suite passing.

## Next Session Handoff (Other Project Requirements)
For the next feature phase, prepare these items in the main store project so we can guide and connect both systems safely:
- API contract and auth:
- Base URL, auth mechanism/token scope, token rotation rules.
- Confirm endpoint versioning strategy (e.g., `/api/v1/sync/...`).
- Resource endpoint definitions (request + response examples):
- Brands, categories, products, variants, images, inventory, orders.
- Pagination and sync semantics:
- Cursor/next-cursor format, page size limits, `updated_since` behavior/timezone.
- Failure and retry semantics:
- Error payload structure, status codes, rate limits, retry windows.
- Data identity and mapping:
- External IDs guaranteed uniqueness per resource.
- Soft-delete/discontinued behavior and expected downstream handling.
- Schema references:
- Current migrations/models for all synced tables and relation keys.
- Operational requirements:
- Expected sync frequency and max data volume per resource.
- Any webhook/event options to complement polling sync.

## Pause Note
- Work paused by request.
- Resume point: start Phase 3 planning with the “Next Session Handoff” list above, then implement first public storefront slice.
- Phase 2 completion review and backlog prioritization.
