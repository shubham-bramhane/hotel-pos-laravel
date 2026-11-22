# API & Route Contracts (`API.md`) — Royal Hotel & Restaurant Hybrid POS

## 1. Routing Overview

Routes are split across two files:
- **[routes/web.php](file:///d:/planned%20projects/hotel-pos-laravel/routes/web.php)**: Session-authenticated POS interfaces, thermal print views, LAN JSON polling endpoints (`/api/waiter/tables`, `/api/kitchen/live`, `/api/counter/live`), Owner administration, AI Analytics, and the public tokenized Investor Portal (`/investor/{token}`).
- **[routes/api.php](file:///d:/planned%20projects/hotel-pos-laravel/routes/api.php)**: Stateless machine-to-machine Cloud Hub sync endpoints (`/api/cloud/health`, `/api/cloud/sync`).
- **Health Probe**: `GET /up` (configured in `bootstrap/app.php`) returns HTTP `200 OK` for Apache/Cloud uptime checks.

---

## 2. Authentication & Role Guard Matrix (`pos.auth`)

Web and LAN polling routes use the custom `EnsureStaffAuthenticated` middleware aliased as `pos.auth`:
- **Session Key**: `pos_staff_id` referencing an active `staff_users.id`.
- **Role Hierarchy (`StaffUser::canAccess`)**:
  - `owner` automatically satisfies **any** role check (`owner`, `cashier`, `kitchen`, `waiter`).
  - Other roles must match one of the middleware parameters (e.g., `pos.auth:waiter,cashier` allows `waiter`, `cashier`, and `owner`).
- **Failure Responses**:
  - Unauthenticated HTML request → `302 Redirect` to `GET /login`.
  - Unauthenticated JSON / `/api/*` request → `401 Unauthorized`:
    ```json
    { "message": "Staff PIN login required." }
    ```
  - Insufficient role HTML request → `302 Redirect` to the staff member's `homeRoute()` with flash error.
  - Insufficient role JSON / `/api/*` request → `403 Forbidden`:
    ```json
    { "message": "Access denied for role: waiter" }
    ```

---

## 3. Web Route Contracts (`routes/web.php`)

### 3.1 Authentication Routes (`AuthPinController`)

| Method | URI | Route Name | Auth | Validation / Parameters | Behavior |
|---|---|---|---|---|---|
| `GET` | `/` | `pos.root` | Public | — | Redirects logged-in staff to their `homeRoute()`, otherwise `/login` |
| `GET` | `/login` | `pos.login` | Public | — | Renders fast numeric PIN login screen |
| `POST` | `/login` | `pos.login.submit` | Public | `pin`: `required\|string\|max:10` | Verifies active `StaffUser`, regenerates session, logs `staff_login`, redirects to role home |
| `POST` | `/logout` | `pos.logout` | Session | CSRF token | Clears `pos_staff_id`, invalidates session, redirects to `/login` |

### 3.2 Waiter Mobile POS Routes (`WaiterController` — `pos.auth:waiter,cashier`)

| Method | URI | Route Name | Request Body / Validation | Response |
|---|---|---|---|---|
| `GET` | `/waiter` | `waiter.index` | — | Floor map grouped by section + ready KOT alert count |
| `GET` | `/waiter/table/{table}` | `waiter.table` | `{table}` route-model bound `DiningTable` | Table order screen with menu categories & active KOTs |
| `POST` | `/waiter/table/{table}/kot` | `waiter.kot.send` | `guest_count`: `nullable\|integer\|min:1\|max:50`<br>`kot_notes`: `nullable\|string\|max:250`<br>`cart_json`: `required\|string` (JSON array of `{menu_item_id, quantity, note}`) | Opens/fetches table order, creates `Kot` + `OrderItem` rows, recalculates GST, redirects to `waiter.table` |
| `POST` | `/waiter/order/{order}/request-bill` | `waiter.bill.request` | `{order}` bound `Order` | Marks order & table status as `billing`, logs `bill_requested` |

### 3.3 Kitchen Display System Routes (`KitchenController` — `pos.auth:kitchen,cashier`)

| Method | URI | Route Name | Request Body / Validation | Response |
|---|---|---|---|---|
| `GET` | `/kitchen` | `kitchen.index` | — | Live KDS screen + 86 stock availability drawer |
| `PATCH` | `/kitchen/kot/{kot}/status` | `kitchen.kot.status` | `status`: `required\|in:pending,preparing,ready,served` | Updates KOT & item statuses, stamps `ready_at`/`served_at`, returns JSON `{ok: true, kot_id, status}` or redirect |
| `PATCH` | `/kitchen/menu/{item}/toggle` | `kitchen.menu.toggle` | `{item}` bound `MenuItem` | Flips `is_available` boolean and logs `menu_stock_toggled` |

### 3.4 Counter Billing & Thermal Print Routes (`CounterController`)

| Method | URI | Route Name | Middleware | Request Body / Validation | Response |
|---|---|---|---|---|---|
| `GET` | `/counter` | `counter.index` | `pos.auth:cashier` | — | Main billing counter workspace |
| `GET` | `/counter/order/{order}` | `counter.order` | `pos.auth:cashier` | `{order}` bound `Order` | Billing counter focused on selected order |
| `POST` | `/counter/table/{table}/open` | `counter.table.open` | `pos.auth:cashier` | `{table}` bound `DiningTable` | Opens or selects table order |
| `POST` | `/counter/parcel` | `counter.parcel.create` | `pos.auth:cashier` | `customer_name`: `nullable\|string\|max:100`<br>`customer_phone`: `nullable\|string\|max:20` | Creates a `takeaway` order (`ORD-YYYYMMDD-NNN`) |
| `POST` | `/counter/order/{order}/items` | `counter.order.items` | `pos.auth:cashier` | `menu_item_id`: `required\|integer\|exists:menu_items,id`<br>`quantity`: `required\|integer\|min:1\|max:50`<br>`note`: `nullable\|string\|max:160` | Appends item and generates a new KOT |
| `POST` | `/counter/item/{item}/cancel` | `counter.item.cancel` | `pos.auth:cashier` | `reason`: `required\|string\|max:160` | Marks item cancelled, cancels KOT if empty, recalculates bill, logs `item_cancelled` |
| `POST` | `/counter/order/{order}/discount` | `counter.order.discount` | `pos.auth:cashier` | `discount_type`: `required\|in:none,flat,percent`<br>`discount_value`: `nullable\|numeric\|min:0`<br>`reason`: `nullable\|string\|max:160` | Applies discount, recalculates CGST/SGST/Grand Total, logs `discount_applied` |
| `POST` | `/counter/order/{order}/transfer` | `counter.order.transfer` | `pos.auth:cashier` | `target_table_id`: `required\|integer\|exists:dining_tables,id` | Moves active dine-in order to an empty table, frees old table, logs `table_transferred` |
| `POST` | `/counter/order/{order}/settle` | `counter.order.settle` | `pos.auth:cashier` | `payment_method`: `required\|in:cash,upi,card,split`<br>`cash_amount`: `nullable\|numeric\|min:0`<br>`upi_amount`: `nullable\|numeric\|min:0` | Marks order `paid`, releases table to `available`, flashes `print_bill_url`, logs `bill_settled` |
| `POST` | `/counter/order/{order}/cancel` | `counter.order.cancel` | `pos.auth:cashier` | `reason`: `required\|string\|max:160` | Cancels entire unpaid order & KOTs, frees table, logs `order_cancelled` |
| `GET` | `/print/bill/{order}` | `print.bill` | `pos.auth:waiter,kitchen,cashier` | — | Renders 80mm/58mm customer tax invoice |
| `GET` | `/print/kot/{kot}` | `print.kot` | `pos.auth:waiter,kitchen,cashier` | — | Renders 80mm/58mm kitchen order ticket |

### 3.5 Owner Administration, AI Analytics & Investor Routes (`pos.auth:owner`)

| Method | URI | Route Name | Controller | Validation / Query Params | Description |
|---|---|---|---|---|---|
| `GET` | `/owner` | `owner.dashboard` | `OwnerController@dashboard` | `?range=today\|yesterday\|7days\|month\|custom&from=YYYY-MM-DD&to=YYYY-MM-DD` | Owner KPI summary, top dishes, waiter stats, recent settled bills |
| `GET` | `/owner/export-sales` | `owner.export.sales` | `OwnerController@exportSales` | Same date range query params | Streams UTF-8 BOM CSV file `Hotel_Sales_<from>_to_<to>.csv` |
| `GET` | `/owner/menu` | `owner.menu` | `OwnerController@menu` | — | Menu categories & dishes management view |
| `POST` | `/owner/menu/category` | `owner.category.store` | `OwnerController@storeCategory` | `name`: `required\|string\|max:80` | Creates new menu category |
| `POST` | `/owner/menu/item` | `owner.item.store` | `OwnerController@storeMenuItem` | `category_id`, `name`, `short_code`, `price`, `is_veg` | Creates dish and logs `menu_item_added` |
| `PUT` | `/owner/menu/item/{item}` | `owner.item.update` | `OwnerController@updateMenuItem` | `name`, `short_code`, `price`, `is_veg`, `is_available` | Updates dish and logs `menu_item_updated` |
| `DELETE` | `/owner/menu/item/{item}` | `owner.item.destroy` | `OwnerController@destroyMenuItem` | — | Deletes dish and logs `menu_item_deleted` |
| `GET` | `/owner/tables` | `owner.tables` | `OwnerController@tables` | — | Floor section & dining table manager |
| `POST` | `/owner/tables` | `owner.tables.store` | `OwnerController@storeTable` | `code` (unique), `name`, `section`, `capacity` (`1–30`) | Adds new table |
| `DELETE` | `/owner/tables/{table}` | `owner.tables.destroy` | `OwnerController@destroyTable` | Blocked if `activeOrder()->exists()` | Deletes idle table |
| `GET` | `/owner/staff` | `owner.staff` | `OwnerController@staff` | — | Staff & PIN directory |
| `POST` | `/owner/staff` | `owner.staff.store` | `OwnerController@storeStaff` | `name`, `role`, `pin_code` (`digits_between:4,6`, unique) | Creates staff user |
| `PUT` | `/owner/staff/{staffUser}` | `owner.staff.update` | `OwnerController@updateStaff` | `name`, `role`, `pin_code`, `is_active` | Updates staff PIN/role/active status |
| `GET` | `/owner/audit` | `owner.audit` | `OwnerController@audit` | `?action=all\|<action>&page=N` | Paginated (`40/page`) immutable anti-theft log |
| `GET` | `/owner/settings` | `owner.settings` | `OwnerController@settings` | — | Hotel, GST, Thermal Paper, Cloud Sync & Gemini settings UI |
| `POST` | `/owner/settings` | `owner.settings.save` | `OwnerController@saveSettings` | Validates hotel profile, GST (`0–28%`), paper (`58\|80`), sync URL/token, Gemini key/model | Saves settings while preserving blank secret fields |
| `POST` | `/owner/backup` | `owner.backup` | `OwnerController@downloadBackup` | — | Streams `hotel_pos_backup_YYYY-MM-DD_HHMMSS.json` |
| `GET` | `/owner/analytics` | `owner.analytics` | `AnalyticsController@index` | `?range=today\|7days\|30days\|month\|90days&outlet_id=<id>` | Interactive SVG charts, multi-outlet table, Gemini AI insights, investor link manager |
| `POST` | `/owner/analytics/generate-ai` | `owner.analytics.ai` | `AnalyticsController@generateAi` | `range`, `outlet_id` | Generates & stores `AiInsightReport` via Gemini or local statistical engine |
| `POST` | `/owner/analytics/sync-now` | `owner.analytics.sync` | `AnalyticsController@syncNow` | `days` (`1–90`), `refresh_ai` (`boolean`) | Rebuilds recent snapshots and triggers immediate outbound cloud sync |
| `POST` | `/owner/analytics/share-links` | `owner.analytics.share.store` | `AnalyticsController@createShareLink` | `title`, `investor_name`, `outlet_id`, `period_days` (`7,14,30,60,90,180,365`), `expires_in_days` | Creates tokenized `InvestorShareLink` |
| `PATCH` | `/owner/analytics/share-links/{shareLink}/toggle` | `owner.analytics.share.toggle` | `AnalyticsController@toggleShareLink` | — | Activates or revokes an investor link |
| `DELETE` | `/owner/analytics/share-links/{shareLink}` | `owner.analytics.share.destroy` | `AnalyticsController@destroyShareLink` | — | Permanently deletes an investor link |

### 3.6 Public Tokenized Investor Route (`InvestorPortalController`)

| Method | URI | Route Name | Auth | Behavior |
|---|---|---|---|---|
| `GET` | `/investor/{token}` | `investor.report` | Token in URL (No PIN) | Validates `is_active === true` and non-expired `expires_at`; increments `views_count` & `last_viewed_at`; renders read-only charts, KPIs, and AI narrative. Returns HTTP `404` (`investor.unavailable`) when invalid, expired, or revoked. |

---

## 4. LAN JSON Polling Endpoints

### 4.1 `GET /api/waiter/tables` (`pos.auth:waiter,cashier`)
Polled every 6 seconds by the Waiter Floor screen.
```json
{
  "time": "07:45:12 PM",
  "tables": [
    {
      "id": 1,
      "code": "T1",
      "status": "occupied",
      "grand_total": 693,
      "grand_total_fmt": "₹693",
      "waiter": "Waiter Raju",
      "ready_kots": 1,
      "pending_kots": 0,
      "minutes": 18
    }
  ]
}
```

### 4.2 `GET /api/kitchen/live` (`pos.auth:kitchen,cashier`)
Polled every 3 seconds by the Kitchen KDS screen. Triggers an alert chime when `latest_kot_id` increases.
```json
{
  "server_time": "07:45:15 PM",
  "latest_kot_id": 12,
  "counts": {
    "pending": 2,
    "preparing": 1,
    "ready": 1
  },
  "kots": [
    {
      "id": 12,
      "kot_no": "KOT-012",
      "status": "pending",
      "table_label": "T1 (Table 1)",
      "order_type": "dine_in",
      "waiter": "Waiter Raju",
      "notes": "Serve hot",
      "created_time": "07:42 PM",
      "elapsed_mins": 3,
      "print_url": "http://127.0.0.1:6808/print/kot/12",
      "status_url": "http://127.0.0.1:6808/kitchen/kot/12/status",
      "items": [
        { "name": "Paneer Butter Masala", "qty": 2, "is_veg": true, "note": "Less spicy" }
      ]
    }
  ]
}
```

### 4.3 `GET /api/counter/live` (`pos.auth:cashier`)
Polled by the Billing Counter header to show live running table count, pending bill requests, and today's settled sales total.
```json
{
  "active_count": 4,
  "billing_requests": 1,
  "today_sales_fmt": "₹18,450",
  "today_bills": 27,
  "latest_kot_id": 12
}
```

---

## 5. Stateless Cloud Hub Sync API (`routes/api.php`)

### 5.1 `GET /api/cloud/health` (`api.cloud.health`)
Public health and topology endpoint for verifying Cloud Hub availability.
```json
{
  "ok": true,
  "mode": "cloud_hub",
  "outlets_connected": 2,
  "server_time": "2026-11-22T15:10:00+05:30"
}
```

### 5.2 `POST /api/cloud/sync` (`api.cloud.sync`)
Ingests signed daily analytics snapshots from one or more local hotel outlets.

#### Required & Optional Headers
| Header | Required | Value Format |
|---|---|---|
| `Authorization` | **Yes** | `Bearer <cloud_sync_token>` (verified with constant-time `hash_equals`) |
| `Content-Type` | **Yes** | `application/json` |
| `X-POS-Outlet-Code` | Recommended | e.g., `ROYAL-YTL-01` |
| `X-POS-Timestamp` | Optional (paired with Signature) | Unix epoch seconds (`1795340400`) |
| `X-POS-Signature` | Optional (verified when present) | `hash_hmac('sha256', $timestamp . '.' . $rawBody, $cloudSyncToken)` |

#### Request Body Schema
```json
{
  "outlet": {
    "outlet_code": "ROYAL-YTL-01",
    "name": "Royal Family Restaurant & Hotel",
    "city": "Yavatmal",
    "state": "Maharashtra",
    "seating_capacity": 74
  },
  "snapshots": [
    {
      "business_date": "2026-11-22",
      "total_orders": 42,
      "dine_in_orders": 34,
      "takeaway_orders": 8,
      "gross_sales": 28400.0,
      "discount_total": 400.0,
      "gst_total": 1400.0,
      "net_revenue": 29400.0,
      "avg_bill_value": 700.0,
      "cash_total": 11400.0,
      "upi_total": 16000.0,
      "card_total": 2000.0,
      "cancelled_orders": 1,
      "cancelled_items": 2,
      "guest_count": 148,
      "category_sales": [{ "name": "Main Course", "qty": 58, "revenue": 14200.0 }],
      "top_dishes": [{ "name": "Paneer Butter Masala", "qty": 24, "revenue": 5760.0, "is_veg": true }],
      "hourly_sales": [{ "hour": 20, "orders": 14, "revenue": 9800.0 }],
      "waiter_performance": [{ "name": "Waiter Raju", "orders": 22, "revenue": 15400.0 }],
      "veg_nonveg_split": { "veg_qty": 90, "veg_revenue": 19400.0, "nonveg_qty": 32, "nonveg_revenue": 9000.0 },
      "payload_checksum": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
    }
  ],
  "sent_at": "2026-11-22T01:00:02+05:30"
}
```

#### Response Codes
- **`200 OK`**:
  ```json
  {
    "ok": true,
    "outlet_code": "ROYAL-YTL-01",
    "upserted": 1,
    "server_time": "2026-11-22T01:00:03+05:30"
  }
  ```
- **`401 Unauthorized`**: Invalid or missing Bearer token or mismatched `X-POS-Signature` HMAC.
- **`422 Unprocessable Entity`**: Missing `outlet.outlet_code` or malformed `snapshots` array.

---

## 6. Pagination & Rate-Limiting Conventions

- **Pagination**: `GET /owner/audit` uses Laravel's cursor-friendly `paginate(40)->withQueryString()` to keep anti-theft log queries fast over tens of thousands of events.
- **Polling Cadence**: Waiter floor polls every `6s`, Kitchen KDS polls every `3s` over local Wi-Fi (`1–5 ms` LAN latency).
- **Idempotency**: `POST /api/cloud/sync` upserts by `(outlet_id, business_date)`, making repeated nightly retries 100% side-effect free.
