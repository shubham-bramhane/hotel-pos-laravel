# Project: Royal Hotel & Restaurant Hybrid POS

> **Status:** FINALIZED
> **Profile:** Web App (Laravel 13 + MySQL + Offline SVG Charts + Google Gemini AI)
> **Updated:** 2026-11-22

## Vision
Deliver a zero-downtime **75% Offline Local LAN Restaurant POS** paired with an automated **25% Nightly Cloud Sync, Google Gemini AI Executive Analytics, and Tokenized Investor Share Portal** on a single dual-mode Laravel 13 codebase (`POS_MODE=outlet | cloud_hub`).

---

## Core Architecture Pillars

1. **75% Offline LAN Core (`POS_MODE=outlet`, Port `6808`)**:
   - Fast 4–6 digit Staff PIN authentication (`Owner: 1111`, `Cashier: 2222`, `Kitchen: 3333`, `Waiter: 4444/5555`).
   - Mobile Waiter Floor Map (`/waiter`) with short-code menu punching (`PBM`, `BN`, `PTK`) and incremental KOTs.
   - Live Kitchen Display System (`/kitchen`) with 3-second LAN polling, audio chime alerts, and 86 stock toggles.
   - Counter Billing Workspace (`/counter`) with Dine-In & Parcel billing, table transfers, mandatory-reason item/order cancellations, flat/% discounts, CGST+SGST + rupee round-off, Cash/UPI/Card/Split settlement, and 80mm/58mm thermal receipts.
   - Immutable Anti-Theft Audit Trail (`audit_logs`), UTF-8 BOM Excel CSV export, and 1-click JSON database backup.

2. **25% Cloud AI, Nightly Sync & Investor Portal (`POS_MODE=cloud_hub`)**:
   - Checksummed daily rollups (`daily_analytics_snapshots`) synced every night at `01:00 AM` (`php artisan pos:sync-cloud --days=3 --ai`) via Bearer token + HMAC-SHA256 signature with an offline retry queue.
   - Zero-CDN interactive SVG charts (`public/js/pos-charts.js`) for Revenue Trend, Payment Split Pie, Category Mix Pie, Peak Hours, Top Dishes, and Multi-Outlet Benchmarking.
   - Google Gemini Interactions API `v1` (`gemini-4-hydrogen`) executive insights with automatic Offline Statistical Engine (`statistical-engine-v1`) fallback.
   - Public read-only tokenized Investor Portal (`/investor/{token}`) with view tracking, expiry, instant revocation, zero PII leakage, and Print-to-PDF layout.

---

## Non-Negotiable Constraints

- **100% Offline Floor Resilience**: Zero external CDN scripts or fonts in POS views.
- **ACID Financial Integrity**: All billing mutations execute inside `DB::transaction()` in `App\Services\PosService`.
- **Zero Customer PII in Cloud/AI/Investor Layers**: Never expose `customer_phone` or `pin_code` in snapshots, Gemini prompts, or `/investor/{token}`.
- **Quality Gate**: `php artisan test --compact` and `vendor/bin/pint --dirty --format agent` must pass before any task is marked complete.
