# Implementation Plan — Royal Hotel & Restaurant LAN POS

## 1. Delivery Approach

The project follows a vertical-slice, offline-first delivery model: establish the relational schema and transactional POS domain engine first, layer role-based UIs and live LAN polling on top, add thermal printing and owner controls, and package one-click Windows LAN deployment scripts.

---

## 2. Phase Breakdown & Status

### Phase 1 — Foundation, Schema & Auth *(Completed)*
- [x] Scaffold Laravel 13 project (`composer.json`, `bootstrap/app.php`, `routes/web.php`).
- [x] Create unified POS migration (`2026_11_20_000001_create_hotel_pos_tables.php`) for all 9 tables: `staff_users`, `dining_tables`, `menu_categories`, `menu_items`, `orders`, `kots`, `order_items`, `audit_logs`, `hotel_settings`.
- [x] Implement Eloquent models (`StaffUser`, `DiningTable`, `MenuCategory`, `MenuItem`, `Order`, `Kot`, `OrderItem`, `AuditLog`, `HotelSetting`) and relationships.
- [x] Implement seed data (`DatabaseSeeder`) with default settings, 5 staff PINs (`1111`–`5555`), 14 tables across 3 sections, 6 categories, and 29 Indian restaurant dishes.
- [x] Build fast PIN authentication (`AuthPinController`) and RBAC middleware (`EnsureStaffAuthenticated`, aliased as `pos.auth`).

### Phase 2 — Core POS Domain Service *(Completed)*
- [x] Build `App\Services\PosService` with `DB::transaction()` guarantees:
  - Table order opening (`openOrGetTableOrder`) & Takeaway creation (`createTakeawayOrder`).
  - Cart-to-KOT creation (`addItemsAndCreateKot`) with item price/name snapshots.
  - KOT status updates (`updateKotStatus`) and bill requests (`requestBill`).
  - Item cancellation (`cancelOrderItem`), full order cancellation (`cancelOrder`), flat/% discount (`applyDiscount`), and table transfer (`transferTable`).
  - Payment settlement (`settleOrder` for Cash/UPI/Card/Split) and table release.
  - GST (CGST + SGST) and rupee rounding engine (`recalculateOrder`) + Indian currency formatter (`formatINR`).
  - Audit logging (`logAudit`) across all operational mutations.

### Phase 3 — Waiter Mobile POS & Live Kitchen KDS *(Completed)*
- [x] Build `WaiterController` + views (`waiter/index.blade.php`, `waiter/table.blade.php`) with floor map, short-code/name menu search, cart builder, and bill request button.
- [x] Implement live table status polling endpoint (`GET /api/waiter/tables`).
- [x] Build `KitchenController` + view (`kitchen/index.blade.php`) with live 3-second polling (`GET /api/kitchen/live`), new-order alert chime, status transitions, and 86 out-of-stock toggles.

### Phase 4 — Counter Billing & Thermal Printing *(Completed)*
- [x] Build `CounterController` + view (`counter/index.blade.php`) supporting dine-in tables, takeaway parcels, quick item punching, table transfer, item/order cancellation with mandatory reason, discounts, and Cash/UPI/Card/Split settlement.
- [x] Implement live counter summary polling (`GET /api/counter/live`).
- [x] Build 58mm / 80mm print-ready thermal templates (`print/bill.blade.php` with consolidated line items and `print/kot.blade.php`).

### Phase 5 — Owner Dashboard, Admin CRUD & Anti-Theft Controls *(Completed)*
- [x] Build `OwnerController` + views (`owner/dashboard`, `owner/menu`, `owner/tables`, `owner/staff`, `owner/audit`, `owner/settings`).
- [x] Implement date-range KPI analytics (`today`, `yesterday`, `7days`, `month`, `custom`), top-selling dishes, and waiter performance breakdowns.
- [x] Add Excel-friendly UTF-8 BOM Sales CSV export (`GET /owner/export-sales`).
- [x] Add one-click full JSON database backup download (`POST /owner/backup`).

### Phase 6 — Packaging, LAN Launchers, Pitch Assets & Verification *(Completed)*
- [x] Configure PWA manifest (`public/manifest.json`) and app icon (`public/icon.svg`).
- [x] Build Windows deployment & launcher scripts: `Activate-Hotel-POS-6808.bat`, `Open-Hotel-POS-App.vbs`, `Create-Hotel-POS-Shortcut.bat`.
- [x] Add end-to-end automated feature test (`tests/Feature/HotelPosFlowTest.php`).
- [x] Add commercial sales collateral (`pitch/pitch-deck.html`, `pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf`).

---

## 3. Future Roadmap (Phase 7+ Enhancements)

| Priority | Item | Technical Notes |
|---|---|---|
| P1 | Concurrency-safe `order_no` / `kot_no` generator | Use a locked daily counter row or `MAX(id)` + unique retry loop inside `PosService` to prevent collisions under simultaneous KOT submissions. |
| P1 | Login rate limiting & PIN security hardening | Apply `throttle:10,1` middleware to `POST /login` to mitigate PIN guessing on LAN devices. |
| P1 | Expand automated test coverage | Add unit tests for `PosService` math/edge cases and negative RBAC/validation feature tests (see `docs/testing-strategy.md`). |
| P2 | JSON backup restore utility | Add Owner-only restore workflow (with confirmation & schema validation) to complement `POST /owner/backup`. |
| P2 | Dynamic UPI QR code on thermal bills | Render offline SVG QR code from `upi://pay?pa=<upi_id>&am=<grand_total>` on `print/bill.blade.php` when `upi_id` is configured. |
| P3 | Split-bill / table-merge support | Allow splitting one table order into multiple bills or merging two occupied tables. |

---

## 4. Component Ownership Map

| Module | Files |
|---|---|
| Domain Logic | `app/Services/PosService.php` |
| Auth & RBAC | `app/Http/Controllers/AuthPinController.php`, `app/Http/Middleware/EnsureStaffAuthenticated.php`, `app/Models/StaffUser.php` |
| Waiter Module | `app/Http/Controllers/WaiterController.php`, `resources/views/waiter/` |
| Kitchen Module | `app/Http/Controllers/KitchenController.php`, `resources/views/kitchen/` |
| Counter & Print | `app/Http/Controllers/CounterController.php`, `resources/views/counter/`, `resources/views/print/` |
| Owner Module | `app/Http/Controllers/OwnerController.php`, `resources/views/owner/` |
| Shared UI & Styles | `resources/views/layouts/pos.blade.php`, `public/css/pos.css` |
| Windows LAN Ops | `Activate-Hotel-POS-6808.bat`, `Open-Hotel-POS-App.vbs`, `Create-Hotel-POS-Shortcut.bat` |
