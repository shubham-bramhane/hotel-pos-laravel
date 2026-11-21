# Technical Requirements Document (TRD) — Royal Hotel & Restaurant LAN POS

## 1. System Architecture

Royal Hotel & Restaurant LAN POS is a monolithic, server-rendered **Laravel 13** web application backed by a local **MySQL** database (`hotel_pos`) and served on the local area network (LAN) via **XAMPP Apache 2.4** on TCP port `6808`.

```
[Waiter Phones]   [Kitchen Tablet]   [Owner Phone]    [Billing PC / Launcher]
       \                 |                 /                     |
        \                |                /            http://127.0.0.1:6808
         +------ Local Wi-Fi LAN (http://<LAN-IP>:6808) ---------+
                                 |
                    [Apache 2.4 VirtualHost :6808]
                                 |
                    [Laravel 13 Application (PHP 8.3+)]
                    ├── Middleware: EnsureStaffAuthenticated (pos.auth)
                    ├── Controllers: AuthPin, Waiter, Kitchen, Counter, Owner
                    ├── Domain Service: App\Services\PosService (DB Transactions)
                    ├── Views: Blade Templates + public/css/pos.css + Print Layouts
                    └── Live Polling JSON APIs (/api/waiter/tables, /api/kitchen/live, /api/counter/live)
                                 |
                      [Local MySQL: hotel_pos]
```

### Key Architectural Decisions
- **LAN Polling over WebSockets**: Uses lightweight JSON polling endpoints (3s interval on KDS) instead of running a separate Reverb/Pusher/Redis daemon, eliminating extra Windows background processes and maximizing offline reliability.
- **Centralized Domain Service (`App\Services\PosService`)**: All order math, state transitions, KOT generation, table status synchronization, and audit logging live in a single transactional service (`DB::transaction()`), keeping controllers thin.
- **Zero-Build Runtime Assets**: Core POS styling lives in `public/css/pos.css` with Blade views and vanilla JS fetch polling so the app runs reliably without requiring a Vite dev server in production.

---

## 2. Technology Stack

| Layer | Technology | Version / Notes |
|---|---|---|
| Backend Framework | Laravel Framework | `^13.17` |
| Language Runtime | PHP | `^8.3` |
| Production Database | MySQL (XAMPP) | Database `hotel_pos` |
| Test Database | SQLite | In-memory (`:memory:`) via `phpunit.xml` |
| Web Server | Apache 2.4 (XAMPP) | Port `6808` (coexists with port `6707`) |
| Frontend Templates | Laravel Blade + Vanilla JS | `resources/views/` + `public/css/pos.css` |
| Frontend Tooling | Vite + Tailwind CSS | `vite ^8.0`, `tailwindcss ^4.0` (dev/optional assets) |
| Testing | PHPUnit | `^12.5.12` |
| Code Style | Laravel Pint | `^1.27` |
| Desktop Packaging | Windows VBScript + PWA Manifest | `Open-Hotel-POS-App.vbs`, `public/manifest.json` |

---

## 3. Data Model & Database Schema

Defined in `database/migrations/2026_11_20_000001_create_hotel_pos_tables.php` (plus standard Laravel `users`, `cache`, and `jobs` tables):

### 3.1 Tables Summary

| Table | Purpose | Key Columns & Constraints |
|---|---|---|
| `staff_users` | Staff accounts & PINs | `id`, `name`, `role` (`owner\|cashier\|kitchen\|waiter`, indexed), `pin_code` (unique, 4–6 digits), `is_active` |
| `dining_tables` | Restaurant floor map | `id`, `code` (unique, e.g. `T1`, `AC-1`), `name`, `section`, `capacity`, `status` (`available\|occupied\|billing`, indexed), `sort_order`, `is_active` |
| `menu_categories` | Dish grouping | `id`, `name`, `sort_order`, `is_active` |
| `menu_items` | Sellable dishes | `id`, `category_id` (FK cascade), `name`, `short_code` (indexed), `price` (decimal 10,2), `is_veg`, `is_available`, `sort_order` |
| `orders` | Dine-in & takeaway bills | `id`, `order_no` (unique `ORD-YYYYMMDD-NNN`), `table_id` (FK nullOnDelete), `waiter_id`, `settled_by_id`, `order_type` (`dine_in\|takeaway`), `guest_count`, `customer_name`, `customer_phone`, `status` (`open\|billing\|paid\|cancelled`, indexed), `subtotal`, `discount_type` (`none\|flat\|percent`), `discount_value`, `discount_amount`, `gst_rate`, `cgst_amount`, `sgst_amount`, `round_off`, `grand_total`, `payment_method` (`cash\|upi\|card\|split`), `cash_amount`, `upi_amount`, `notes`, `opened_at`, `paid_at` (indexed) |
| `kots` | Kitchen Order Tickets | `id`, `kot_no` (`KOT-NNN`), `order_id` (FK cascade), `created_by_id`, `status` (`pending\|preparing\|ready\|served\|cancelled`, indexed), `notes`, `ready_at`, `served_at` |
| `order_items` | Line items per order & KOT | `id`, `order_id` (FK cascade), `kot_id` (FK cascade), `menu_item_id` (FK nullOnDelete), `item_name` (snapshot), `is_veg`, `unit_price` (snapshot), `quantity`, `line_total`, `special_note`, `status`, `cancel_reason` |
| `audit_logs` | Immutable anti-theft trail | `id`, `staff_id` (FK nullOnDelete), `staff_name` (snapshot), `staff_role`, `action` (indexed), `entity_type`, `entity_id`, `details` (JSON), `ip_address` |
| `hotel_settings` | Key-value configuration | `key` (PK, varchar 60), `value` (text) |

### 3.2 Snapshot Integrity
- `order_items` stores snapshots of `item_name`, `unit_price`, and `is_veg` at order time so subsequent menu price edits or dish deletions never alter historical bills.
- `audit_logs` stores snapshots of `staff_name` and `staff_role` alongside `staff_id` so historical logs remain readable even if a staff user is modified or removed.

---

## 4. Routing & API Contracts

All routes are declared in `routes/web.php`, with Laravel health check enabled at `GET /up` in `bootstrap/app.php`.

### 4.1 Authentication
- `GET /` — Redirects authenticated staff to role home route, otherwise `/login`.
- `GET /login`, `POST /login` — PIN validation against active `staff_users`, session regeneration (`pos_staff_id`), audit log (`staff_login`).
- `POST /logout` — Invalidates session and regenerates CSRF token.

### 4.2 Waiter Routes (`pos.auth:waiter,cashier`)
- `GET /waiter` — Floor map grouped by section with Ready KOT counter.
- `GET /waiter/table/{table}` — Table order & menu punching view.
- `POST /waiter/table/{table}/kot` — Opens/reuses table order and creates KOT from `cart_json`.
- `POST /waiter/order/{order}/request-bill` — Marks order & table status as `billing`.
- `GET /api/waiter/tables` — JSON snapshot of all active tables, statuses, totals, KOT counts, and open durations.

### 4.3 Kitchen KDS Routes (`pos.auth:kitchen,cashier`)
- `GET /kitchen` — Active KOT board & 86 stock toggles.
- `GET /api/kitchen/live` — JSON payload with `server_time`, `latest_kot_id`, `counts` (`pending`, `preparing`, `ready`), and active `kots[]`.
- `PATCH /kitchen/kot/{kot}/status` — Updates KOT and non-cancelled item statuses (JSON or redirect).
- `PATCH /kitchen/menu/{item}/toggle` — Toggles `menu_items.is_available` and logs `menu_stock_toggled`.

### 4.4 Counter / Billing & Print Routes (`pos.auth:cashier` / print: `waiter,kitchen,cashier`)
- `GET /counter`, `GET /counter/order/{order}` — Counter billing workspace.
- `POST /counter/table/{table}/open` — Opens empty table order from counter.
- `POST /counter/parcel` — Creates takeaway order.
- `POST /counter/order/{order}/items` — Quick item addition + KOT creation.
- `POST /counter/item/{item}/cancel` — Cancels line item (mandatory `reason`), auto-cancels KOT if empty, recalculates order.
- `POST /counter/order/{order}/discount` — Applies `none | flat | percent` discount and recalculates.
- `POST /counter/order/{order}/transfer` — Moves dine-in order to a vacant target table.
- `POST /counter/order/{order}/settle` — Settles via `cash | upi | card | split`, frees table, marks KOTs/items served.
- `POST /counter/order/{order}/cancel` — Cancels unpaid order (mandatory `reason`) and frees table.
- `GET /api/counter/live` — JSON summary (`active_count`, `billing_requests`, `today_sales_fmt`, `today_bills`, `latest_kot_id`).
- `GET /print/bill/{order}` — Consolidated thermal bill receipt view (58mm/80mm).
- `GET /print/kot/{kot}` — Thermal kitchen order ticket view.

### 4.5 Owner Admin Routes (`pos.auth:owner`)
- `GET /owner` — KPI dashboard (`range=today|yesterday|7days|month|custom`).
- `GET /owner/export-sales` — Streamed UTF-8 BOM CSV export.
- `GET /owner/menu`, `POST /owner/menu/category`, `POST/PUT/DELETE /owner/menu/item/{item}` — Menu CRUD.
- `GET /owner/tables`, `POST /owner/tables`, `DELETE /owner/tables/{table}` — Table CRUD (protected against active orders).
- `GET /owner/staff`, `POST /owner/staff`, `PUT /owner/staff/{staffUser}` — Staff & PIN CRUD.
- `GET /owner/audit` — Paginated audit log (40/page, filterable by `action`).
- `GET /owner/settings`, `POST /owner/settings` — Hotel, GST, UPI, and printer width settings.
- `POST /owner/backup` — Streamed JSON snapshot of all 9 POS tables (latest 1,000 audit logs).

---

## 5. Business Logic & Financial Calculation Rules

Implemented in `PosService::recalculateOrder()`:

1. **Subtotal**: Sum of `line_total` for all `order_items` where `status != 'cancelled'`.
2. **Discount**:
   - `flat`: `min(subtotal, max(0, discount_value))`
   - `percent`: `round(subtotal * (clamp(discount_value, 0, 100) / 100), 2)`
   - `none`: `0.00`
3. **Taxable Amount**: `max(0, subtotal - discount_amount)`
4. **GST Split**: `halfRate = gst_rate / 2`; `cgst = round(taxable * halfRate / 100, 2)`; `sgst = round(taxable * halfRate / 100, 2)` (stored per order at creation time from `hotel_settings`).
5. **Rounding**: `rawTotal = taxable + cgst + sgst`; `grand_total = round(rawTotal)`; `round_off = round(grand_total - rawTotal, 2)`.
6. **Split Payment**: For `split`, `cash_amount` is taken from input and `upi_amount` defaults to `max(0, grand_total - cash_amount)` if not explicitly entered.

---

## 6. Security & Reliability

- **Session Security**: Session ID regenerated on login (`$request->session()->regenerate()`); invalidated + CSRF token regenerated on logout.
- **CSRF Protection**: Enabled across all state-changing web routes via Laravel's default web middleware stack.
- **Role Enforcement**: `EnsureStaffAuthenticated` middleware (`pos.auth`) validates both active DB status (`is_active = true`) and role permissions on every request, returning `401`/`403` JSON for API routes and redirects for web routes.
- **Exception Rendering**: `bootstrap/app.php` forces JSON exception responses for `/api/*` and `Accept: application/json` requests.
- **Trusted Proxies**: `$middleware->trustProxies(at: '*')` configured for flexible LAN reverse-proxy setups.
- **Known Limitations / Hardening Opportunities**:
  - PINs are stored in plain text in `staff_users.pin_code` (unique indexed for fast lookup in trusted LANs); rate-limiting `/login` and hashing PINs can be added in future hardening phases.
  - `nextOrderNo()` and `nextKotNo()` use daily `count() + 1` inside transactions; under very high concurrent load, adding a row lock or sequence table prevents duplicate `order_no` collisions.

---

## 7. Performance & Capacity Profile

- Designed for 1–20 concurrent LAN clients (waiter phones + 1–2 KDS tablets + 1–2 counter terminals + owner phone) on a standard Windows laptop running XAMPP Apache + MySQL.
- Indexed columns on high-frequency queries: `staff_users.role`, `dining_tables.status`, `menu_items.short_code`, `orders.status`, `orders.paid_at`, `kots.status`, `audit_logs.action`.
- Eager loading (`with(...)`) used across Waiter, Kitchen, Counter, and Owner controllers to prevent N+1 queries on live polling intervals.
