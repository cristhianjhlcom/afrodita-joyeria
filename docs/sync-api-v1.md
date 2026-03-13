# Sync API v1

This document describes the JSON contracts for the sync endpoints exposed by the main store.

## Base

- Base path: `/api/v1/sync/*`
- Auth: Sanctum Bearer token
- Required abilities per endpoint:
  - Catalog: `catalog-sync:read`
  - Inventory: `inventory-sync:read`
  - Orders: `orders-sync:read`

## Common Query Params

- `updated_since` (optional): ISO 8601 UTC timestamp
- `cursor` (optional): pagination cursor
- `per_page` (optional): max 200, default 100

Ordering is deterministic: `updated_at`, then `id`.

## Common Response Envelope (paginated)

```json
{
  "data": [],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

## Error Envelope

```json
{
  "error": {
    "code": "string_code",
    "message": "Human readable message",
    "details": {},
    "trace_id": "uuid"
  }
}
```

## Catalog Endpoints

### GET /api/v1/sync/brands

Ability: `catalog-sync:read`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Brand",
      "slug": "brand",
      "is_active": true,
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/categories

Ability: `catalog-sync:read`

```json
{
  "data": [
    {
      "id": 10,
      "name": "Category",
      "slug": "category",
      "is_active": true,
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/subcategories

Ability: `catalog-sync:read`

```json
{
  "data": [
    {
      "id": 100,
      "category_id": 10,
      "name": "Subcategory",
      "slug": "subcategory",
      "is_active": true,
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/products

Ability: `catalog-sync:read`

```json
{
  "data": [
    {
      "id": 1000,
      "name": "Product",
      "slug": "product",
      "description": "Product description",
      "status": "draft",
      "subcategory_id": 100,
      "brand_id": 1,
      "youtube_video_id": "dQw4w9WgXcQ",
      "created_at": "2026-03-13T12:00:00Z",
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/variants

Ability: `catalog-sync:read`

```json
{
  "data": [
    {
      "id": 2000,
      "product_id": 1000,
      "sku": "SKU-001",
      "code": "CODE-001",
      "price": 12900,
      "sale_price": 15900,
      "color": "Gold",
      "hex": "#ffd700",
      "size": "7",
      "include_in_merchant": true,
      "gtin": "1234567890123",
      "mpn": "MPN-001",
      "google_product_category": "Jewelry",
      "sale_price_starts_at": "2026-03-13T10:00:00Z",
      "sale_price_ends_at": "2026-03-20T10:00:00Z",
      "created_at": "2026-03-13T12:00:00Z",
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/variant-images

Ability: `catalog-sync:read`

Image URLs are always fully qualified.

```json
{
  "data": [
    {
      "id": "image-10",
      "product_id": 1000,
      "variant_id": null,
      "url": "https://store.test/storage/products/product-1.jpg",
      "sort_order": 0,
      "is_primary": true,
      "alt": null,
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    },
    {
      "id": "variant-2000-image",
      "product_id": 1000,
      "variant_id": 2000,
      "url": "https://store.test/storage/variants/variant-1.jpg",
      "sort_order": 0,
      "is_primary": true,
      "alt": null,
      "updated_at": "2026-03-13T12:00:00Z",
      "deleted_at": null
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

## Inventory Endpoint

### GET /api/v1/sync/inventory

Ability: `inventory-sync:read`

Inventory is computed from stock movements. `stock_reserved` is always 0.

```json
{
  "data": [
    {
      "variant_id": 2000,
      "stock_on_hand": 5,
      "stock_reserved": 0,
      "stock_available": 5,
      "updated_at": "2026-03-13T12:00:00Z"
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

## Orders Endpoints (Read-Only)

### GET /api/v1/sync/orders

Ability: `orders-sync:read`

```json
{
  "data": [
    {
      "id": 5000,
      "customer_id": 42,
      "status": "pending",
      "currency": "PEN",
      "subtotal": 25000,
      "discount_total": 0,
      "shipping_total": 1000,
      "tax_total": 0,
      "grand_total": 26000,
      "placed_at": "2026-03-13T12:00:00Z",
      "updated_at": "2026-03-13T12:10:00Z"
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/order-items

Ability: `orders-sync:read`

```json
{
  "data": [
    {
      "id": 7000,
      "order_id": 5000,
      "variant_id": 2000,
      "sku": "SKU-001",
      "name_snapshot": "Product - Gold 7",
      "qty": 2,
      "unit_price": 12900,
      "line_total": 25800,
      "updated_at": "2026-03-13T12:10:00Z"
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

## Order Write Endpoints

These endpoints are used by the other store to push orders into the main store.

### POST /api/v1/orders

Auth: integration Sanctum token

```json
{
  "external_order_id": "remote-order-001",
  "currency": "PEN",
  "discount_amount": 0,
  "shipping_cost": 1000,
  "order_status": "pending",
  "payment_status": "unpaid",
  "customer": {
    "email": "buyer@example.com",
    "contact": "Optional contact",
    "first_name": "Ana",
    "last_name": "Store",
    "phone": "999111222",
    "doc_type": "DNI",
    "doc_number": "12345678",
    "observation": "Optional note"
  },
  "items": [
    {
      "sku": "SKU-001",
      "quantity": 2,
      "price_per_unit": 12900
    }
  ]
}
```

Response: `201` with `data` in the order sync shape.

### GET /api/v1/orders/{external_order_id}

Auth: integration Sanctum token

Response:

```json
{
  "data": {
    "id": 5000,
    "customer_id": 42,
    "status": "pending",
    "currency": "PEN",
    "subtotal": 25000,
    "discount_total": 0,
    "shipping_total": 1000,
    "tax_total": 0,
    "grand_total": 26000,
    "placed_at": "2026-03-13T12:00:00Z",
    "updated_at": "2026-03-13T12:10:00Z"
  }
}
```

### POST /api/v1/orders/{external_order_id}/cancel

Auth: integration Sanctum token

```json
{
  "note": "Customer requested cancellation",
  "mark_refunded": true
}
```

Response: `200` with `data` in the order sync shape.

## Address Endpoints

### GET /api/v1/sync/countries

```json
{
  "data": [
    {
      "id": 1,
      "name": "Peru",
      "iso_code_2": "PE",
      "iso_code_3": "PER",
      "is_active": true,
      "updated_at": "2026-03-13T12:00:00Z"
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/departments

```json
{
  "data": [
    {
      "id": 10,
      "country_id": 1,
      "name": "Lima",
      "ubigeo_code": "15",
      "updated_at": "2026-03-13T12:00:00Z",
      "country": {
        "id": 1,
        "name": "Peru",
        "iso_code_2": "PE",
        "iso_code_3": "PER",
        "is_active": true,
        "updated_at": "2026-03-13T12:00:00Z"
      }
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/provinces

```json
{
  "data": [
    {
      "id": 100,
      "department_id": 10,
      "name": "Lima",
      "ubigeo_code": "1501",
      "shipping_price": "10.00",
      "cost": "10.00",
      "is_active": true,
      "updated_at": "2026-03-13T12:00:00Z",
      "department": {
        "id": 10,
        "name": "Lima",
        "ubigeo_code": "15"
      },
      "country": {
        "id": 1,
        "iso_code_2": "PE"
      }
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/districts

```json
{
  "data": [
    {
      "id": 1000,
      "province_id": 100,
      "name": "Miraflores",
      "ubigeo_code": "150122",
      "shipping_price": "12.00",
      "is_active": true,
      "has_delivery_express": true,
      "updated_at": "2026-03-13T12:00:00Z",
      "province": {
        "id": 100,
        "ubigeo_code": "1501"
      },
      "department": {
        "id": 10,
        "ubigeo_code": "15"
      },
      "country": {
        "id": 1,
        "iso_code_2": "PE"
      }
    }
  ],
  "meta": {
    "next_cursor": null,
    "has_more": false,
    "per_page": 100,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

### GET /api/v1/sync/addresses

This endpoint returns nested address data and does not use cursor pagination.

```json
{
  "data": [
    {
      "id": 1,
      "name": "Peru",
      "iso_code_2": "PE",
      "iso_code_3": "PER",
      "is_active": true,
      "departments": [
        {
          "id": 10,
          "name": "Lima",
          "ubigeo_code": "15",
          "provinces": [
            {
              "id": 100,
              "name": "Lima",
              "ubigeo_code": "1501",
              "shipping_price": "10.00",
              "cost": "10.00",
              "is_active": true,
              "districts": [
                {
                  "id": 1000,
                  "name": "Miraflores",
                  "ubigeo_code": "150122",
                  "shipping_price": "12.00",
                  "is_active": true,
                  "has_delivery_express": true
                }
              ]
            }
          ]
        }
      ]
    }
  ],
  "meta": {
    "countries_count": 1,
    "synced_at": "2026-03-13T12:00:00Z"
  }
}
```

## Notes

- Image URLs are always returned as fully qualified URLs.
- `deleted_at` is `null` for active records and ISO 8601 for soft-deleted records.
