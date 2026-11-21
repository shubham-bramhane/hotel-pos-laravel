# Testing Strategy — Royal Hotel & Restaurant Hybrid POS

## 1. Testing Philosophy

This system spans two operational boundaries: a **75% offline mission-critical billing floor** (where zero math error, zero downtime, and strict RBAC are mandatory) and a **25% cloud AI & investor reporting layer** (where nightly sync idempotency, HMAC signature verification, Gemini API resilience, and investor link privacy are paramount).

---

## 2. Test Environment & Commands

Configured in `phpunit.xml` (in-memory SQLite `:memory:` with `RefreshDatabase` + `DatabaseSeeder`, zero external network calls thanks to `Http::fake()`):

```bash
# Run full automated suite
php artisan test --compact

# Run targeted test suites
php artisan test --compact tests/Feature/HotelPosFlowTest.php
php artisan test --compact tests/Feature/HybridAnalyticsAndSyncTest.php

# Format PHP files per Laravel Pint standard
vendor/bin/pint --dirty --format agent
```

---

## 3. Automated Test Coverage (`tests/Feature/`)

### 3.1 Core Offline POS Lifecycle (`HotelPosFlowTest.php`)
- Unauthenticated redirect & PIN login (`4444`, `3333`, `2222`, `1111`).
- RBAC isolation (Waiter blocked from `/owner` and `/counter`).
- Table opening, multi-item KOT submission, CGST/SGST math (`660 → 693`), bill request.
- Kitchen KDS `/api/kitchen/live` JSON counts and `pending → preparing → ready` transitions.
- Cashier table transfer (`T1 → T2`), item cancellation with reason, flat discount recalculation (`480 - 80 + 5% GST = 420`), Split Cash+UPI settlement, and table release.
- Thermal Bill & KOT print rendering, Owner KPIs, CSV sales export, JSON backup download, and `audit_logs` assertions.

### 3.2 Hybrid 75/25 AI Analytics, Cloud Sync & Investor Portal (`HybridAnalyticsAndSyncTest.php`)
- **Offline Snapshots & RBAC**: Verifies `/owner/analytics` blocks non-owners, builds `daily_analytics_snapshots` for dine-in + parcel orders, computes accurate revenue/payment/category rollups, and renders SVG chart containers.
- **Gemini AI + Offline Fallback**:
  - Verifies automatic `local_fallback` statistical report generation when no Gemini API key is set.
  - Mocks `POST https://generativelanguage.googleapis.com/v1/interactions` via `Http::fake()` and asserts `x-goog-api-key` header, `gemini-4-hydrogen` model, structured JSON extraction (`provider = gemini`, `health_score = 89`).
  - Simulates HTTP `503` Gemini outage and verifies seamless fallback to `local_fallback` without crashes.
- **Nightly Outbound Sync & Inbound Multi-Outlet Cloud Hub**:
  - Simulates offline failure (`503`) on `php artisan pos:sync-cloud` and verifies snapshot is marked `failed` with `sync_attempts = 1` for auto-retry.
  - Verifies retry success (`200`) sends Bearer token, `X-POS-Outlet-Code`, and `X-POS-Signature` HMAC headers, marking snapshot `synced` and logging `CloudSyncLog`.
  - Verifies `POST /api/cloud/sync` rejects invalid Bearer tokens (`401`), ingests signed payloads from a second remote hotel (`ROYAL-PUN-02`, Pune), updates `/api/cloud/health` (`outlets_connected = 2`), and aggregates both branches on `/owner/analytics`.
- **Investor Share Portal & Privacy**:
  - Verifies Owner link creation, unauthenticated investor access to `/investor/{token}`, view counter increment, AI narrative rendering, **zero customer phone PII leakage**, and immediate `404` lockout upon revocation.

### 3.3 Recommended Next Unit & Edge Tests
- Unit tests for `PosService::formatINR()` (lakhs, crores, decimals, negative numbers) and `recalculateOrder()` rounding extremes.
- Negative validation tests for expired investor tokens (`expires_at < now()`), tampered HMAC signatures on `/api/cloud/sync`, and deleting occupied tables.

---

## 4. Manual LAN, Cloud & Hardware QA Checklist

| Area | Verification Steps |
|---|---|
| **Offline Floor Resilience** | Disconnect internet WAN cable while keeping Wi-Fi active; complete Waiter → KDS → Counter → Thermal Bill cycle and open `/owner/analytics` to confirm offline SVG charts and statistical AI insights still render with zero console errors. |
| **Nightly Cloud Sync** | Configure a Cloud Hub URL + shared token in Owner → Settings, click **Sync Nightly Snapshots Now** (or run `php artisan pos:sync-cloud --days=7 --ai --manual`), and confirm sister branch appears on Cloud Hub `/owner/analytics`. |
| **Windows Scheduler** | Run `Setup-Nightly-Cloud-Sync.bat` as Admin and verify task `RoyalHotelPOS_NightlyCloudSync` exists in Windows Task Scheduler (`schtasks /Query /TN RoyalHotelPOS_NightlyCloudSync`). |
| **Investor PDF Export** | Open a generated `/investor/{token}` link in an incognito browser, click **Print / Save Investor PDF**, and verify clean charts, tables, and zero staff/PII leakage. |
| **Thermal Printing & Coexistence** | Verify 80mm/58mm print formatting and confirm `GET /up` returns `200 OK` on both port `6808` and port `6707`. |

---

## 5. Release Quality Gate

1. `vendor/bin/pint --dirty --format agent` passes with zero formatting violations.
2. `php artisan test --compact` passes 100% (currently **7 tests, 139 assertions**).
3. `php artisan migrate --force` succeeds cleanly on local MySQL (`hotel_pos`).
4. `GET /up` and `GET /api/cloud/health` both return HTTP `200`.
