# Testing Strategy — Royal Hotel & Restaurant LAN POS

## 1. Testing Philosophy

Because this POS handles live cash, UPI, tax calculations, kitchen routing, and anti-theft auditing in an offline environment, testing focuses on **zero financial miscalculation**, **strict role isolation**, **transactional state consistency**, and **real-world LAN usability** across phones, tablets, and thermal printers.

---

## 2. Test Environment & Tooling

Configured in `phpunit.xml` and `composer.json`:

- **Framework**: PHPUnit `^12.5.12` via `php artisan test` (or `composer test`).
- **Database**: In-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) with `RefreshDatabase` + `DatabaseSeeder` for fast, isolated, repeatable runs.
- **Drivers**: `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.
- **Linting / Formatting**: Laravel Pint (`vendor/bin/pint --test`).

### Quick Commands
```bash
# Run full automated test suite
composer test
# or
php artisan test

# Run specific POS end-to-end flow test
php artisan test --filter=HotelPosFlowTest

# Check code formatting
vendor/bin/pint --test
```

---

## 3. Test Pyramid & Coverage Plan

### 3.1 Feature / Integration Tests (`tests/Feature/`)
Tests HTTP routes, middleware, validation, database state, JSON APIs, Blade views, and streamed downloads end-to-end.

**Currently Implemented (`tests/Feature/HotelPosFlowTest.php`)**:
- Unauthenticated redirect to `/login`.
- Waiter PIN login (`4444`) and RBAC denial on `/owner` and `/counter`.
- Table opening (`T1`), multi-item KOT submission via `cart_json`, subtotal & 5% GST calculation (`660 → 693`), and table status transition (`occupied`).
- Waiter bill request (`billing` state).
- Kitchen PIN login (`3333`), `/api/kitchen/live` JSON counts, and KOT status transitions (`pending → preparing → ready`).
- Cashier PIN login (`2222`), table transfer (`T1 → T2`), item cancellation with reason, flat discount (`₹80`) + GST recalculation (`480 - 80 = 400 → +5% = 420`), and Split payment settlement (`Cash ₹200 + UPI ₹220`), releasing `T2`.
- Thermal receipt rendering for Bill (`/print/bill/{order}`) and KOT (`/print/kot/{kot}`).
- Owner PIN login (`1111`), KPI dashboard verification (`₹420`), Menu/Tables/Staff/Audit/Settings views, CSV sales export, JSON database backup download, and `audit_logs` presence.

**Recommended Feature Test Additions**:
1. **Negative RBAC Matrix**: Verify Kitchen (`3333`) cannot access `/counter` or `/owner`; Cashier (`2222`) cannot access `/owner`; deactivated staff (`is_active = false`) are blocked with `401` on `/api/*` and redirected on web routes.
2. **Takeaway / Parcel Lifecycle**: Create parcel order, add items, settle via Cash/UPI/Card, verify no table is locked.
3. **Edge-Case State Guards**:
   - Reject adding items or cancelling items on `paid` or `cancelled` orders.
   - Reject cancelling an already `paid` bill.
   - Reject transferring a dine-in order to an already occupied table or transferring a takeaway order.
   - Reject deleting a dining table that has an active order.
   - Auto-cancel KOT when its last active item is cancelled.
4. **Menu & Staff Validation**: Duplicate PIN rejection, invalid role rejection, duplicate table code rejection, GST disabled (`gst_enabled = 0`) billing behavior.
5. **Date-Range Reporting**: Verify `yesterday`, `7days`, `month`, and `custom` filters on `/owner` and `/owner/export-sales`.

### 3.2 Unit Tests (`tests/Unit/`)
Target pure business logic in `App\Services\PosService`:
- `PosService::formatINR()` across small numbers (`₹0`, `₹45`), thousands (`₹1,250`), lakhs (`₹1,25,000`), decimals (`₹99.50`), and negative amounts (`-₹250`).
- `recalculateOrder()` rounding edge cases (flat discount exceeding subtotal capped at subtotal, 100%+ percent discount capped at 100%, 0% GST, 5%/12%/18% CGST+SGST split, positive and negative `round_off` values).
- Split payment fallback calculation when `upi_amount` is omitted.

### 3.3 Manual LAN & Hardware QA Checklist
Run before deploying to a live restaurant floor:

| Area | Verification Steps |
|---|---|
| **Multi-Device LAN Sync** | Open Waiter on mobile phone (`http://<LAN-IP>:6808`), Kitchen on tablet, Counter on laptop; send KOT from phone and confirm Kitchen updates within ≤ 3s with chime sound. |
| **Offline Mode** | Disconnect router WAN/internet cable while keeping Wi-Fi active; complete full order → KDS → settle → print cycle with zero console/network errors. |
| **Thermal Printing** | Print both `/print/kot/{kot}` and `/print/bill/{order}` on actual 80mm and 58mm thermal printers; verify alignment, currency symbol (`₹`), GST breakdown, and margins (`@media print`). |
| **Desktop Launcher** | Double-click `Open-Hotel-POS-App.vbs` and confirm standalone Chrome/Edge app window launches without address bar clutter. |
| **Port Coexistence** | Run `Activate-Hotel-POS-6808.bat` and confirm `/up` returns HTTP 200 on both port `6808` (Hotel POS) and port `6707` (Driving School Portal). |
| **Backup & Export** | Open exported CSV in Microsoft Excel and verify `₹`/UTF-8 characters render cleanly; inspect downloaded JSON backup for valid JSON and complete table keys. |

---

## 4. Release Quality Gate

Before committing or deploying any production change:
1. Run `php artisan test` — **100% of tests must pass**.
2. Verify `GET /up` responds `200 OK` on the target Apache port (`6808`).
3. Verify no sensitive files (`.env`, `*.sqlite`, backups) are tracked in Git.
4. For schema changes, test both `php artisan migrate:fresh --seed` and incremental `php artisan migrate` against MySQL.
