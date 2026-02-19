# Main Store Platform Requirements for Afrodita Joyeria Sync

## 1. Purpose
This document defines the requirements that the **main store platform** must implement so this project can operate as an independent storefront/admin app while keeping a reliable connection with the main business system.

This project will:
- Show and manage synced catalog data (brands, categories, subcategories, products, variants, images).
- Monitor inventory state for product variants.
- Mirror orders/carts in read-only mode (phase 1).

The main store remains the **source of truth** for:
- Catalog records
- Inventory quantities
- Official orders lifecycle

## 2. Integration Principles
- Integration mode: **API pull** from this project to the main store.
- Authentication: **Sanctum token** (server-to-server).
- Freshness target: sync every **5 minutes** (acceptable range 5-15 minutes).
- Sync style: full bootstrap + incremental updates by timestamp cursor.
- Brand constraint: only configured brand IDs are imported into this project.

## 3. Authentication and Security Requirements
The main store must provide:
- API token creation for machine-to-machine integrations.
- Token abilities/scopes:
  - `catalog-sync:read`
  - `inventory-sync:read`
  - `orders-sync:read`
- HTTPS-only endpoints.
- Rate limiting for sync endpoints with headers:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `Retry-After` when throttled.

## 4. API Versioning and Base Path
Required versioned namespace:
- `/api/v1/sync/*`

All endpoints must support:
- `updated_since` (ISO 8601 datetime, UTC)
- cursor pagination: `cursor`, `per_page`
- deterministic ordering: `updated_at`, then `id`

Response envelope (required):

```json
{
  "data": [],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "synced_at": "2026-02-19T12:00:00Z"
  }
}
```

## 5. Required Endpoints
The main store must expose the following read endpoints:
- `GET /api/v1/sync/brands`
- `GET /api/v1/sync/categories`
- `GET /api/v1/sync/subcategories`
- `GET /api/v1/sync/products`
- `GET /api/v1/sync/variants`
- `GET /api/v1/sync/variant-images` (or merged into products/variants payload)
- `GET /api/v1/sync/inventory`
- `GET /api/v1/sync/orders`
- `GET /api/v1/sync/order-items`

## 6. Data Contracts

### 6.1 Products (main store schema provided)
Main store source table:

```php
$table->id();
$table->string('name', 70);
$table->string('slug')->unique();
$table->text('description')->nullable();
$table->enum('status', [
    Product::DRAFT,
    Product::INACTIVE,
    Product::PUBLISHED,
    Product::IN_STOCK,
    Product::OUT_OF_STOCK,
    Product::PRE_ORDER,
    Product::BACKORDERED,
    Product::DISCONTINUED,
    Product::SOLD_OUT,
    Product::COMMING_SOON,
])->default(Product::DRAFT);
$table->unsignedBigInteger('subcategory_id');
$table->foreign('subcategory_id')->references('id')->on('subcategories');
$table->unsignedBigInteger('brand_id');
$table->foreign('brand_id')->references('id')->on('brands');
$table->timestamps();
```

Required payload fields for products endpoint:
- `id` (external product id)
- `name`
- `slug`
- `description` (nullable)
- `status` (enum value)
- `subcategory_id`
- `brand_id`
- `created_at`
- `updated_at`
- `deleted_at` (nullable, required if soft delete is supported)

### 6.2 Variants (main store schema provided)
Main store source table:

```php
Schema::create('variants', function (Blueprint $table) {
    $table->id();
    $table->string('sku')->unique()->nullable();
    $table->string('code')->unique()->nullable();
    $table->integer('price')->unsigned()->nullable();
    $table->integer('sale_price')->unsigned()->nullable();
    $table->string('color')->nullable();
    $table->string('hex')->nullable();
    $table->string('size')->nullable();
    $table->unsignedBigInteger('product_id');
    $table->foreign('product_id')->references('id')->on('products');
    $table->timestamps();
});
```

Required payload fields for variants endpoint:
- `id` (external variant id)
- `product_id`
- `sku` (nullable)
- `code` (nullable)
- `price` (integer minor units)
- `sale_price` (integer minor units, nullable)
- `color` (nullable)
- `hex` (nullable)
- `size` (nullable)
- `created_at`
- `updated_at`
- `deleted_at` (nullable)

### 6.3 Brands
Required fields:
- `id`
- `name`
- `slug`
- `is_active`
- `updated_at`
- `deleted_at` (nullable)

### 6.4 Categories and Subcategories
Required fields:
- Categories:
  - `id`
  - `name`
  - `slug`
  - `is_active`
  - `updated_at`
  - `deleted_at` (nullable)
- Subcategories:
  - `id`
  - `category_id`
  - `name`
  - `slug`
  - `is_active`
  - `updated_at`
  - `deleted_at` (nullable)

### 6.5 Product/Variant Images
Required fields:
- `id`
- `product_id`
- `variant_id` (nullable)
- `url` (publicly reachable HTTPS URL)
- `sort_order`
- `is_primary`
- `alt` (nullable)
- `updated_at`
- `deleted_at` (nullable)

### 6.6 Inventory
Inventory is required as a dedicated resource (because stock is not stored in variants schema).

Required fields:
- `variant_id`
- `stock_on_hand`
- `stock_reserved`
- `stock_available`
- `updated_at`

Optional but recommended:
- `location_code`
- `last_movement_at`
- `movement_reason`

### 6.7 Orders and Order Items (Read-Only Mirror)
Required fields for orders:
- `id`
- `customer_id`
- `status`
- `currency`
- `subtotal`
- `discount_total`
- `shipping_total`
- `tax_total`
- `grand_total`
- `placed_at`
- `updated_at`

Required fields for order items:
- `id`
- `order_id`
- `variant_id`
- `sku`
- `name_snapshot`
- `qty`
- `unit_price`
- `line_total`
- `updated_at`

## 7. Sync Rules and Behavior
The main store API must support these behaviors:
- Initial full sync when `updated_since` is omitted.
- Incremental sync when `updated_since` is sent.
- Idempotent reads and stable pagination.
- Soft-deleted records must be retrievable with deletion marker (`deleted_at`).
- Referential consistency:
  - Product references valid `brand_id` and `subcategory_id`.
  - Variant references valid `product_id`.

## 8. Error Contract
Error payload shape:

```json
{
  "error": {
    "code": "string_code",
    "message": "Human readable message",
    "details": {},
    "trace_id": "uuid-or-correlation-id"
  }
}
```

Expected status handling:
- `401`: invalid or missing token
- `403`: missing ability/scope
- `404`: unknown endpoint/resource
- `422`: invalid query params (`updated_since`, cursor)
- `429`: rate limited
- `500+`: server errors

## 9. Performance and Operational Requirements
- Endpoint average response time: target < 1.5s for standard page sizes.
- Max `per_page` allowed: at least 100 records.
- API uptime target: 99.5% or higher.
- Include `synced_at` in metadata for observability.
- Provide a change log for schema/contract updates.

## 10. Backward Compatibility and Change Management
- Contract must remain backward compatible within `/api/v1`.
- New fields can be added without removing or renaming existing fields.
- Breaking changes require a new API version (`/api/v2`) and migration window.

## 11. Implementation Notes for Main Store Team
- Prefer Laravel API Resources for endpoint payload standardization.
- Use Sanctum middleware and scoped abilities.
- Use indexed `updated_at` for performant incremental sync queries.
- Ensure deterministic sort (`updated_at`, `id`) for cursor-based paging.

## 12. Acceptance Checklist
The main platform is ready when all are true:
- [ ] All required `/api/v1/sync/*` endpoints exist.
- [ ] Sanctum token auth with required abilities works.
- [ ] `updated_since` incremental sync works on all resources.
- [ ] Cursor pagination and deterministic ordering are implemented.
- [ ] Products contract includes all fields and statuses from provided schema.
- [ ] Variants contract includes all fields from provided schema.
- [ ] Inventory endpoint is available and linked by `variant_id`.
- [ ] Orders and order-items read-only mirror endpoints are available.
- [ ] Standard error response shape is implemented.
- [ ] Endpoint docs/examples are published for integration.

## 13. Notes About Current Scope
- This project starts with admin-first modules.
- Orders are mirrored read-only in phase 1.
- Catalog and inventory remain authoritative in main store.
- Brand whitelist filtering is applied in this project after sync.
