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
- [ ] Phase 2: deeper admin operations and UX hardening

## Pending / Next Phases
- [x] Add product detail page with variant/image drill-down actions
- [ ] Add sync run detail page and error inspection UI
- [ ] Add explicit stale-data warnings (e.g. last successful sync threshold)
- [ ] Add retry/trigger actions per resource (brands/categories/products/etc.)
- [ ] Add policies/gates for finer permissions (if needed beyond admin/customer)
- [ ] Add integration tests for admin filtering and pagination behavior
- [ ] Add performance guardrails (indexes review, query optimization checks)
- [ ] Optional: notification/alerting for repeated sync failures

## Environment / Ops Checklist
- [ ] `.env` configured for MySQL
- [ ] `MAIN_STORE_BASE_URL` configured
- [ ] `MAIN_STORE_TOKEN` configured
- [ ] Queue worker running in dev (`php artisan queue:work` or composer dev)
- [ ] Scheduler running in environment
- [ ] Migrations up to date

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
- Active branch: `docs/main-store-sync-requirements`
- Key recent commits:
  - `4654f01` feat: add dedicated admin layout with grouped sidebar
  - `a984681` fix: run product_variants migration before product_images foreign key
  - `11b913e` fix: run orders migration before order_items foreign key
  - `0378a74` feat: add livewire admin catalog and operations pages
  - `8d4de17` feat: add main-store sync pipeline and admin access controls
  - `e8d87b0` feat: add ecommerce domain schema models and factories
  - `86016e2` chore: add initial laravel project setup
  - `57566a7` docs: add main store integration requirements for catalog sync

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
