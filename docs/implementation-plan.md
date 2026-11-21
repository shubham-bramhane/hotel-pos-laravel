# Implementation Plan — Royal Hotel & Restaurant Hybrid POS

## 1. Delivery Approach

The platform was delivered in modular vertical slices: first establishing a 100% offline-resilient LAN POS core (Phases 1–6), then extending it into a **75% Offline / 25% Cloud AI Hybrid Platform** with interactive offline SVG charts, nightly signed cloud sync, dual-mode Cloud Hub multi-branch benchmarking, Google Gemini AI executive insights, and tokenized Investor Share Portals (Phase 7).

---

## 2. Completed Milestones

### Phase 1–6 — Core 75% Offline LAN POS *(Completed)*
- [x] **Schema & Seeders**: 9 core POS tables (`2026_11_20_000001_create_hotel_pos_tables.php`) + realistic Indian restaurant seed data (5 staff PINs, 14 tables, 6 categories, 29 dishes).
- [x] **Fast PIN Auth & RBAC**: `AuthPinController` + `EnsureStaffAuthenticated` middleware (`pos.auth`).
- [x] **Transactional POS Engine**: `App\Services\PosService` with `DB::transaction()` across table orders, parcels, KOTs, cancellations, discounts, transfers, CGST/SGST/rounding, split payments, and anti-theft audit logs.
- [x] **4 Role Interfaces + Thermal Print**: Waiter mobile floor map, 3s live Kitchen KDS with chime, Counter billing workspace, 58mm/80mm thermal Bill & KOT layouts, and Owner dashboard + CSV export + JSON backup.
- [x] **Windows LAN Packaging**: `Activate-Hotel-POS-6808.bat`, `Open-Hotel-POS-App.vbs`, `Create-Hotel-POS-Shortcut.bat`, PWA `manifest.json`.

### Phase 7 — 25% Cloud AI Analytics, Nightly Sync & Investor Portal *(Completed)*
- [x] **Hybrid Schema & Config**: `2026_11_21_211625_create_analytics_and_sync_tables.php` (`outlets`, `daily_analytics_snapshots`, `ai_insight_reports`, `investor_share_links`, `cloud_sync_logs`), `config/pos.php`, `.env.example`, and `HotelSetting` defaults.
- [x] **Offline-Capable SVG Charts (`public/js/pos-charts.js`)**: Zero-CDN interactive Revenue Area/Bar Trend Graph, Payment Split Pie/Donut Chart, Menu Category Mix Pie/Donut Chart, Peak Dining Hours Bar Chart, and Horizontal Comparison Bars.
- [x] **Analytics Aggregation Engine (`AnalyticsService`)**: SHA-256 checksummed daily rollups, growth % vs prior period, Annualized Run Rate (ARR), peak-hour detection, Veg/Non-Veg mix, and multi-outlet benchmarking.
- [x] **Google Gemini AI Insights (`GeminiAnalyticsService`)**: Integrates Gemini Interactions API v1 (`gemini-4-hydrogen`, JSON Schema structured output) with automatic Offline Statistical Engine fallback.
- [x] **Dual-Mode Nightly Cloud Sync (`CloudSyncService` + `SyncPosCloudCommand` + `CloudSyncApiController`)**:
  - Outbound signed sync (`Authorization: Bearer` + HMAC-SHA256 `X-POS-Signature`), offline failure retry queue, and `01:00 AM` scheduler (`routes/console.php` + `Setup-Nightly-Cloud-Sync.bat`).
  - Inbound Cloud Hub API (`POST /api/cloud/sync`, `GET /api/cloud/health`) for consolidating multiple hotels/branches.
- [x] **Owner AI Analytics UI & Investor Share Portal**:
  - `resources/views/owner/analytics.blade.php` + updated `owner/settings.blade.php` & `layouts/pos.blade.php`.
  - Public read-only Investor Portal (`resources/views/investor/report.blade.php` & `unavailable.blade.php`) with view tracking, revocation, expiry, and print-to-PDF layout.
- [x] **Automated Test Suite & Pitch Deck**: `tests/Feature/HybridAnalyticsAndSyncTest.php` + updated 11-slide Pitch Deck HTML & PDF.

---

## 3. Upcoming Roadmap (Phase 8+)

| Priority | Feature | Technical Notes |
|---|---|---|
| P1 | Concurrency-safe `order_no` / `kot_no` sequence | Add row-locked daily counter to eliminate collisions under simultaneous waiter submissions. |
| P1 | Login PIN rate limiting & hashing | Apply `throttle:10,1` on `POST /login` and migrate PINs to salted hash lookup. |
| P2 | Dynamic UPI QR code on thermal bills | Render offline SVG QR from `upi://pay?pa=<upi_id>&am=<grand_total>` on `print/bill.blade.php`. |
| P2 | Per-outlet secret keys in Cloud Hub UI | Allow Cloud Hub owners to provision and rotate individual `sync_secret_hash` tokens per franchisee outlet. |
| P2 | Automated JSON backup restore utility | Guided Owner-only restore workflow validating schema version before import. |
| P3 | Split-bill & table-merge operations | Split a single table order across multiple bills or merge two active tables. |

---

## 4. Module Ownership Map

| Module | Files |
|---|---|
| Offline POS Domain | `app/Services/PosService.php`, `app/Http/Controllers/{Waiter,Kitchen,Counter,Owner,AuthPin}Controller.php` |
| Hybrid Analytics & Charts | `app/Services/AnalyticsService.php`, `public/js/pos-charts.js`, `resources/views/owner/analytics.blade.php` |
| Gemini AI Engine | `app/Services/GeminiAnalyticsService.php`, `app/Models/AiInsightReport.php` |
| Nightly Cloud Sync & Hub API | `app/Services/CloudSyncService.php`, `app/Console/Commands/SyncPosCloudCommand.php`, `app/Http/Controllers/CloudSyncApiController.php`, `routes/api.php`, `Setup-Nightly-Cloud-Sync.bat` |
| Investor Executive Portal | `app/Http/Controllers/InvestorPortalController.php`, `app/Models/InvestorShareLink.php`, `resources/views/investor/` |
| Pitch & Engineering Docs | `docs/{prd,trd,implementation-plan,testing-strategy,deployment-plan}.md`, `pitch/pitch-deck.html`, `pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf` |
