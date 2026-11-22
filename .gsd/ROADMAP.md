---
milestone: Milestone 1 — Hybrid 75% Offline / 25% Cloud AI POS & Phase 8 Hardening
---

# Roadmap — Royal Hotel & Restaurant Hybrid POS

> **Current Phase:** 8 - Security Hardening, Dynamic UPI QR & Concurrency Locks
> **Status:** planning

---

## Milestone 1: Hybrid 75% Offline / 25% Cloud AI Platform

### Phase 1: Core POS Database Schema & Indian Restaurant Seeder
**Status:** ✅ Complete
- [x] Plan 1.1: Create 9 core POS tables (`2026_11_20_000001_create_hotel_pos_tables.php`)
- [x] Plan 1.2: Seed default hotel settings, 5 staff PINs, 14 tables, 6 categories & 29 dishes

### Phase 2: Fast Staff PIN Authentication & RBAC Middleware
**Status:** ✅ Complete
- [x] Plan 2.1: Build `AuthPinController` & numeric PIN keypad login UI
- [x] Plan 2.2: Enforce `EnsureStaffAuthenticated` (`pos.auth`) role hierarchy

### Phase 3: Transactional POS Engine & Anti-Theft Audit Trail
**Status:** ✅ Complete
- [x] Plan 3.1: Build `PosService` ACID transactions for orders, parcels, KOTs, discounts, transfers & split settlements
- [x] Plan 3.2: Implement CGST/SGST math, nearest-rupee round-off, `formatINR()` & immutable `audit_logs`

### Phase 4: Waiter Mobile POS, 3s Kitchen KDS & Counter Billing UI
**Status:** ✅ Complete
- [x] Plan 4.1: Build Waiter section floor map (6s poll) & short-code KOT punching UI
- [x] Plan 4.2: Build Kitchen KDS (3s poll, Web Audio chime, status transitions, 86 stock drawer)
- [x] Plan 4.3: Build Counter Billing workspace with inline cancellations, discounts, transfers & split settlement

### Phase 5: 80mm/58mm Thermal Printing & Owner Master Controls
**Status:** ✅ Complete
- [x] Plan 5.1: Build 80mm/58mm thermal Bill & KOT print views (`resources/views/print/`)
- [x] Plan 5.2: Build Owner Dashboard, UTF-8 Excel CSV export, Menu/Tables/Staff CRUD, Audit viewer & JSON backup

### Phase 6: Windows XAMPP Apache Port 6808 LAN Packaging
**Status:** ✅ Complete
- [x] Plan 6.1: Create `Activate-Hotel-POS-6808.bat`, `Open-Hotel-POS-App.vbs`, `Create-Hotel-POS-Shortcut.bat` & `Setup-Nightly-Cloud-Sync.bat`

### Phase 7: 25% Cloud AI Analytics, Nightly Sync & Investor Portal
**Status:** ✅ Complete
- [x] Plan 7.1: Create 5 hybrid analytics & sync tables (`2026_11_21_211625_create_analytics_and_sync_tables.php`)
- [x] Plan 7.2: Build `AnalyticsService` daily SHA-256 snapshots & zero-CDN SVG chart engine (`public/js/pos-charts.js`)
- [x] Plan 7.3: Integrate Google Gemini Interactions API v1 (`GeminiAnalyticsService`) + Offline Statistical Fallback
- [x] Plan 7.4: Build `CloudSyncService`, `pos:sync-cloud` Artisan command, and `/api/cloud/sync` multi-outlet ingestion
- [x] Plan 7.5: Build tokenized read-only Investor Portal (`/investor/{token}`) & 15-file documentation suite

### Phase 8: Security Hardening, Dynamic UPI QR & Concurrency Locks
**Status:** Planning
- [ ] Plan 8.1: Add row-locked daily sequence generator for concurrency-safe `order_no` and `kot_no` creation
- [ ] Plan 8.2: Add login rate limiting (`throttle:10,1`) on `POST /login` with unit/feature test coverage
- [ ] Plan 8.3: Render offline SVG UPI QR code (`upi://pay?...`) on 80mm/58mm thermal customer bills when `upi_id` is configured
- [ ] Plan 8.4: Add expired investor token & tampered HMAC signature negative feature tests
