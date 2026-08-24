# Admin Dashboard Statistics API

Last updated: 2026-07-22

## Authentication

- Middleware: `auth:sanctum` + `admin` (`EnsureAdmin`)
- Actor must be a `User` with `role = admin`
- Header: `Authorization: Bearer {admin_token}`

Unauthenticated → `401`. Authenticated non-admin → `403`.

## Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/v1/admin/dashboard/overview` | Latest requests/ratings, top artisans, category distribution |
| `GET` | `/api/v1/admin/dashboard/request-statistics/monthly` | Per-month request counts and percentages for a year |
| `GET` | `/api/v1/admin/dashboard/stats` | Existing summary counters (unchanged) |

## Query parameters

### Overview

No query filters in this phase.

`date_from` / `date_to` were omitted on purpose: applying them only to category/top-artisan sections while keeping “latest” lists global would produce inconsistent dashboard data. Use monthly statistics for time-bounded reporting.

### Monthly request statistics

| Parameter | Required | Rules |
|-----------|----------|-------|
| `year` | No (defaults to current app-timezone year) | Integer, `2000` … `current_year + 1` |
| `category_id` | No | Must exist in `categories` |
| `provider_id` | No | Must exist in `providers` and not soft-deleted |

Invalid values → `422`.

## Response envelope

Uses `ApiResponseTrait`:

```json
{
  "success": true,
  "message": "...",
  "data": {},
  "errors": null
}
```

## Overview response example

```json
{
  "success": true,
  "message": "Dashboard overview retrieved successfully.",
  "data": {
    "latest_requests": [
      {
        "id": 150,
        "customer": { "id": 20, "name": "Customer name" },
        "artisan": { "id": 8, "name": "Artisan name" },
        "category": { "id": 3, "name": "Plumbing" },
        "status": "completed",
        "urgency": "normal",
        "scheduled_at": "2026-07-22T10:00:00+00:00",
        "created_at": "2026-07-22T08:00:00+00:00"
      }
    ],
    "latest_ratings": [
      {
        "id": 80,
        "rating": 5,
        "comment": "Excellent service",
        "customer": { "id": 20, "name": "Customer name" },
        "artisan": { "id": 8, "name": "Artisan name" },
        "service_request": { "id": 150 },
        "created_at": "2026-07-22T09:00:00+00:00"
      }
    ],
    "top_artisans": [
      {
        "id": 8,
        "name": "Artisan name",
        "profile_image": "http://localhost/storage/providers/images/x.jpg",
        "average_rating": 4.85,
        "ratings_count": 74,
        "completed_requests_count": 132,
        "categories": [{ "id": 3, "name": "Plumbing" }]
      }
    ],
    "category_distribution": {
      "total_requests": 1000,
      "categories": [
        {
          "category_id": 1,
          "name": "Plumbing",
          "requests_count": 430,
          "percentage": 43.0
        }
      ]
    }
  },
  "errors": null
}
```

Notes:

- Categories store a single `name` field (no `name_ar` / `name_en` columns).
- Soft-deleted service requests are excluded.
- Soft-deleted / missing relations are returned as `null` summaries.
- Sensitive fields (password, tokens, phone, coordinates, addresses) are not exposed.

## Monthly statistics response example

```json
{
  "success": true,
  "message": "Monthly request statistics retrieved successfully.",
  "data": {
    "year": 2026,
    "annual_total": 320,
    "months": [
      {
        "month": 4,
        "month_key": "april",
        "month_name": "April",
        "month_name_ar": "نيسان",
        "total": 320,
        "annual_percentage": 100.0,
        "statuses": {
          "pending": { "count": 20, "percentage": 6.25 },
          "accepted": { "count": 0, "percentage": 0 },
          "rejected": { "count": 0, "percentage": 0 },
          "delayed": { "count": 0, "percentage": 0 },
          "completed": { "count": 200, "percentage": 62.5 },
          "cancelled": { "count": 100, "percentage": 31.25 }
        }
      }
    ],
    "year_totals": {
      "total": 320,
      "pending": 20,
      "accepted": 0,
      "rejected": 0,
      "delayed": 0,
      "completed": 200,
      "cancelled": 100
    }
  },
  "errors": null
}
```

All 12 months are always returned in order `1…12`.

## Percentage formulas

### Category distribution

```text
category percentage = (category request count / total categorized requests) × 100
```

- Denominator = count of non-deleted `service_requests` (schema requires `category_id`, so all counted rows are categorized).
- Active categories with zero requests are included at `0%`.
- Inactive categories are excluded.
- Division by zero → `0`.

### Monthly status percentage

```text
status percentage = (status count in month / monthly total) × 100
```

### Annual percentage

```text
month annual percentage = (monthly total / annual total) × 100
```

Percentages are rounded to 2 decimal places. Rounded values may not sum to exactly 100.

## Top-artisan ranking

Deterministic order:

1. `AVG(ratings.rating)` descending (`0` when no ratings)
2. `COUNT(ratings.id)` descending
3. Count of completed service requests descending
4. `providers.id` ascending

Excluded: soft-deleted providers and `status = suspended`.

Live aggregates are used (not only stored `rating_avg`) so ranking matches `ratings_count`.

## Status interpretation

There is **no** request-status history table.

Monthly statistics use:

```text
request creation month + current status
```

Example: a request created in April and later completed in May is counted under **April → completed**.

Supported status keys (from `RequestStatusEnum`):

`pending`, `accepted`, `rejected`, `delayed`, `completed`, `cancelled`

## Timezone behavior

- Application timezone: `config('app.timezone')` (default `UTC`)
- Year bounds: `[Jan 1 00:00:00 year, Jan 1 00:00:00 year+1)` in that timezone
- Filtering uses `created_at >= start AND created_at < next_year_start` (index-friendly)

## Empty-state behavior

- No requests: overview latest lists are `[]`; category percentages are `0`; monthly months are all zeros.
- No ratings: `latest_ratings` is `[]`; artisans without ratings rank after rated ones with `average_rating: 0`, `ratings_count: 0`.

## Error responses

| Code | When |
|------|------|
| `401` | Missing/invalid Sanctum token |
| `403` | Authenticated but not admin |
| `422` | Invalid `year` / `category_id` / `provider_id` |
| `500` | Unexpected statistics failure (logged; no raw SQL leaked) |

## Postman usage

Collection: `DarCare_Admin_API.postman_collection.json`

Variables:

- `base_url`
- `admin_token`
- `year`
- `category_id`
- `provider_id`

Requests under folder **Dashboard**:

1. Dashboard Overview
2. Monthly Request Statistics
3. Get Summary Stats

## Implementation notes

- Queries run synchronously in the HTTP request (no queues/jobs).
- Overview uses a small fixed number of queries with eager loads / aggregates (no N+1).
- Monthly statistics use one grouped aggregate query, then in-memory expansion to 12 months.
