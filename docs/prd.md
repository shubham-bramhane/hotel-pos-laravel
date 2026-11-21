# Product Requirements Document (PRD) — Royal Hotel & Restaurant Hybrid POS

## 1. Product Overview

**Royal Hotel & Restaurant Hybrid POS** is an **offline-first, cloud-intelligent Point of Sale (POS)** web platform built for Indian family restaurants, hotels, cafes, and growing multi-branch food chains.

It follows a **75% Offline / 25% Cloud AI Hybrid Architecture**:
- **75% Local Offline Core (LAN)**: Waiter mobile ordering, Live Kitchen Display System (KDS), Counter Billing, GST/Thermal Printing, and Anti-Theft Audit Logs run 100% on the hotel's local billing PC + Wi-Fi router with **zero internet dependency during rush hours**.
- **25% Cloud AI & Nightly Sync Layer**: Every night (default `01:00 AM`, plus manual on-demand sync), the local POS aggregates daily performance snapshots and securely syncs them to a live **Cloud Analytics Hub** (same Laravel codebase running in `cloud_hub` mode). This unlocks **interactive graphs & pie charts**, **Google Gemini AI business insights**, **multi-outlet benchmarking**, and **read-only shareable Investor Performance Links** for fundraising and expansion.

---

## 2. Problem Statement

1. **Pure Cloud POS Fails Offline**: Internet cuts during peak dinner service freeze billing and waiter apps, while recurring per-device SaaS fees erode restaurant margins.
2. **Pure Offline POS Isolates Data**: Keeping data trapped on a single counter laptop prevents owners from benchmarking multiple hotels/branches, running AI growth analytics, or proving verified revenue traction to investors and lenders.
3. **Manual Spreadsheets Cannot Raise Funds**: Investors and banks require auditable revenue trends, Average Order Value (AOV), digital UPI traceability, discount/leakage discipline, and multi-outlet unit economics — not handwritten registers.

---

## 3. Goals & Non-Goals

### Goals
1. **Zero-Downtime 75% Offline Floor Operations**: All floor ordering, kitchen routing, counter billing, and 58mm/80mm thermal printing work seamlessly without WAN internet.
2. **Automated 25% Nightly Cloud Consolidation**: Daily snapshots (`daily_analytics_snapshots`) sync nightly via signed HTTPS payloads to a live Cloud Hub server, with automatic offline retry queues so no day is ever lost.
3. **Interactive Visual Analytics (Offline-Ready SVG Charts)**: Revenue trend graphs, Payment Split pie charts (Cash/UPI/Card), Category Mix pie charts, Peak Dining Hours bars, Veg/Non-Veg mix, Top Dishes, and Multi-Outlet Benchmarking charts that render without external CDN dependencies.
4. **Google Gemini AI Executive Intelligence**: Uses Google Gemini (`gemini-4-hydrogen` via Interactions API v1) to produce structured Executive Summaries, Health Scores (`0–100`), Highlights, Leakage Risks, Margin Recommendations, and Investor Narratives — with an automatic **Offline Statistical Fallback Engine** when no key or internet is available.
5. **Investor-Ready Shareable Portal**: Tokenized, revocable, expiring read-only URLs (`/investor/{token}`) showing verified KPIs, graphs, pie charts, branch comparisons, AI narrative, and one-click Print/Save PDF — exposing zero staff PINs or customer phone PII.
6. **Anti-Theft Accountability**: Immutable audit logs for every login, KOT, item/order cancellation (mandatory reason), discount, table transfer, settlement, AI report, cloud sync, and investor link action.

### Non-Goals (Current Version)
- Real-time cloud sync of live open KOTs (intentionally kept local LAN for 1–5 ms latency and zero cloud dependency).
- Online delivery aggregator webhooks (Swiggy/Zomato API integration).
- Raw-material inventory / recipe stock depletion.
- Automated payment gateway settlement webhooks (UPI is tracked as a payment mode with configurable UPI ID).

---

## 4. Target Users & Personas

| Role | Default Demo PIN | Primary Device | Core Responsibilities |
|---|---|---|---|
| **Waiter** | `4444`, `5555` | Android/iOS phone on LAN Wi-Fi | Live section floor map, short-code menu punching (`PBM`, `BN`), KOT notes, Ready KOT alerts, bill requests. |
| **Kitchen (KDS)** | `3333` | Kitchen tablet / TV screen | 3s auto-polling KDS queue with chime alert, Cooking/Ready/Served updates, 86 Out-of-Stock toggle, KOT print. |
| **Cashier / Counter** | `2222` | Billing Counter Laptop/PC | Dine-in & Takeaway Parcel billing, table transfer, item/order cancel with reason, flat/% discounts, Cash/UPI/Card/Split settlement, 58mm/80mm thermal bills. |
| **Owner / Admin** | `1111` | Counter PC / Phone / Cloud Hub | Live Sales Dashboard, **AI Analytics & Charts**, **Nightly Cloud Sync**, Multi-Outlet Benchmarking, **Investor Share Links**, Menu/Tables/Staff CRUD, Anti-Theft Audit Log, Settings & JSON Backup. |
| **External Investor / Lender** | *Token Link (No PIN)* | Any Browser (Desktop/Mobile) | Views read-only verified performance dashboard (`/investor/{token}`), inspects charts, AI executive narrative, multi-branch KPIs, and prints Investor PDF. |

---

## 5. Functional Requirements

### 5.1 75% Offline Core POS (Unchanged Reliability)
- **PIN Auth & RBAC**: Unique 4–6 digit PINs, deactivated user lockout, strict role route guards.
- **Waiter Floor & KOTs**: Multi-section table map (`Main Hall`, `AC Family`, `Garden`), Veg/Non-Veg markers, dish short codes, multiple incremental KOTs per order, bill request notifications.
- **Live Kitchen KDS**: 3-second JSON polling (`/api/kitchen/live`), chime alerts, elapsed timer, stock toggles.
- **Counter Billing & Printing**: Dine-in & Takeaway Parcel, CGST + SGST calculation, rupee round-off, Cash/UPI/Card/Split payment settlement, 58mm/80mm thermal Bill & KOT printing.
- **Owner Master Controls**: Date-range KPI dashboard, UTF-8 Excel Sales CSV export, Menu/Tables/Staff CRUD, Anti-Theft Audit Log, and 1-click JSON Backup.

### 5.2 25% Cloud AI Analytics, Graphs & Pie Charts (`/owner/analytics`)
- Date range filtering (`Today`, `7 Days`, `30 Days`, `This Month`, `90 Days`) and Outlet switcher (`All Connected Outlets` or individual branch).
- **Executive KPIs**: Net Sales Revenue + Period-over-Period Growth %, Settled Bills (Dine-In vs Parcel split), Average Order Value (AOV) + Peak Hour, Traceable Digital Payment Share (UPI + Card %), and Estimated Annualized Run Rate (ARR).
- **Offline-Capable Interactive Charts**:
  - Daily Revenue & Bill Velocity Area/Bar Trend Graph
  - Payment Mode Split Donut/Pie Chart (Cash vs UPI vs Card)
  - Menu Category Mix Donut/Pie Chart + Veg/Non-Veg split
  - Peak Dining Hours Bar Chart (highlighting peak hour)
  - Multi-Outlet Revenue Comparison Bar Chart + Benchmarking Table
  - Top Revenue Dishes Horizontal Bar Chart
- **AI Executive Insights**: Generates and caches structured `AiInsightReport` records via Google Gemini (`gemini-4-hydrogen`) or the Offline Statistical Engine fallback.

### 5.3 Dual-Mode Nightly Cloud Sync
- Same codebase runs as **`outlet`** (local hotel LAN) or **`cloud_hub`** (central live server).
- Scheduled daily command (`php artisan pos:sync-cloud --days=3 --ai` at `01:00 AM`) + manual **Sync Nightly Snapshots Now** button.
- Outbound sync builds checksummed `daily_analytics_snapshots`, signs payloads with HMAC-SHA256 + Bearer token, uploads to `POST {cloud_sync_url}`, and queues failed uploads cleanly for automatic retry when internet returns.
- Inbound Cloud Hub (`POST /api/cloud/sync`) verifies credentials/signature and idempotently upserts remote `outlets` and `daily_analytics_snapshots`.

### 5.4 Investor Performance Portal (`/investor/{token}`)
- Owner creates shareable links with custom title, investor name, reporting window (`7`–`365` days), optional expiry, and toggles for AI Insights and Branch Comparison.
- Owner tracks link view count and can revoke/re-enable or delete links anytime.
- Public read-only portal renders KPIs, charts, AI narrative, multi-branch comparison, and a clean **Print / Save Investor PDF** layout without exposing customer phone numbers or staff PINs.

---

## 6. Success Metrics

- **Offline Uptime**: 100% uninterrupted billing/KDS operation during WAN outages.
- **Nightly Sync Reliability**: 100% idempotent daily snapshot delivery with zero duplicate financial records across retries.
- **Investor Readiness**: Owners can generate and share a verified multi-branch executive link + printable PDF in under 30 seconds.
- **Test Quality**: 100% automated feature tests passing across both offline POS flows and hybrid AI/sync/investor flows.
