# Royal Hotel / Restaurant Hybrid POS (75% Offline LAN + 25% Cloud AI Analytics)

An offline-first, investor-ready **Hotel & Restaurant POS** built on **Laravel 13 + MySQL + XAMPP Apache 2.4 (Port `6808`)**, engineered with a **75% Offline / 25% Cloud AI Hybrid Architecture**:

- **75% Offline Local LAN Core (`POS_MODE=outlet`)**: Waiter mobile ordering, Live Kitchen Display System (KDS) with 3-second polling & audio chime, Counter Billing, CGST/SGST & 80mm/58mm Thermal Printing, and Anti-Theft Audit Trail run 100% over your local Wi-Fi router with **zero internet dependency during rush hours**.
- **25% Nightly Cloud Sync, AI Analytics & Investor Portal (`POS_MODE=cloud_hub`)**: Every night (`01:00 AM`), SHA-256 checksummed daily snapshots sync via HMAC-SHA256 signed HTTPS payloads to your live **Cloud Analytics Hub** (with an automatic offline retry queue) — unlocking zero-CDN interactive SVG revenue graphs, payment/category pie charts, multi-branch benchmarking, **Google Gemini AI (`gemini-4-hydrogen`) CFO insights** (with an automatic Offline Statistical Fallback Engine), and **tokenized read-only Investor Performance Links (`/investor/{token}`)** for fundraising and bank due diligence.

---

## Key Features & Tech Stack Summary

| Layer | Specification |
|---|---|
| **Framework & Runtime** | Laravel `^13.17` on PHP `8.3+` (verified on `PHP 8.5.12`) |
| **Database** | MySQL `hotel_pos` (14 domain tables) + In-memory SQLite (`:memory:`) for automated tests |
| **Local LAN Server** | XAMPP Apache 2.4 on Port `6808` (coexists cleanly with Driving School Portal on Port `6707`) |
| **Charts & Frontend** | Blade + `public/css/pos.css` + `public/js/pos-charts.js` (100% offline zero-CDN vector SVG charts) |
| **AI Intelligence** | Google Gemini Interactions API `v1` (`gemini-4-hydrogen`, JSON Schema) + deterministic `statistical-engine-v1` fallback |
| **Quality Assurance** | PHPUnit 12 (**7 feature suites, 139 assertions passing**), Laravel Pint, Laravel Boost MCP |

---

## Quick Start (Local Hotel Outlet — 1 Click)

1. **Activate Port `6808` & Firewall**: Double-click [Activate-Hotel-POS-6808.bat](file:///d:/planned%20projects/hotel-pos-laravel/Activate-Hotel-POS-6808.bat) (click **Yes** on the Windows Admin prompt) to restart Apache 2.4 on port **`6808`** and open TCP `6808` in Windows Firewall.
2. **Open on Billing Counter Laptop**:
   - Double-click [Open-Hotel-POS-App.vbs](file:///d:/planned%20projects/hotel-pos-laravel/Open-Hotel-POS-App.vbs) (or browse to **http://127.0.0.1:6808**)
   - Optional Desktop Shortcut: run [Create-Hotel-POS-Shortcut.bat](file:///d:/planned%20projects/hotel-pos-laravel/Create-Hotel-POS-Shortcut.bat)
3. **Open on Waiter Phones / Kitchen Tablet (Same Wi-Fi)**:
   - Browse to **`http://<YOUR-LAPTOP-WIFI-IP>:6808`** (e.g., `http://192.168.1.8:6808`)
4. **Enable Nightly Cloud Sync (`01:00 AM`)**:
   - Configure Cloud Sync URL, Secret Token & optional Google Gemini API Key in **Owner (`1111`) → Settings**, then run [Setup-Nightly-Cloud-Sync.bat](file:///d:/planned%20projects/hotel-pos-laravel/Setup-Nightly-Cloud-Sync.bat) (or test anytime with `php artisan pos:sync-cloud --days=7 --ai --manual`).

---

## Default Staff PINs (Change Anytime in Owner → Staff)

| Role | Default PIN | Primary Workspace |
|---|---|---|
| **Owner (Admin)** | `1111` | Sales Dashboard (`/owner`), **AI Charts & Investor Portal (`/owner/analytics`)**, Menu, Tables, Staff, Anti-Theft Audit Log, Cloud/Gemini Settings & JSON DB Backup |
| **Cashier Desk** | `2222` | Billing Counter (`/counter`), Parcel Orders, Discounts, Table Transfer, Cash/UPI/Card/Split Settlement & 80mm/58mm Thermal Bill Print |
| **Main Kitchen** | `3333` | Live KDS Screen (`/kitchen`, 3s LAN auto-refresh + alert chime), Cooking/Ready/Served status & 86 Stock Out drawer |
| **Waiter Raju / Amit** | `4444` / `5555` | Mobile Table Floor Map (`/waiter`), Fast Menu + Short-Code (`PBM`, `BN`) Order Punching, Send KOT & Request Bill |

---

## Complete 15-File Project Documentation Index & Pitch Deck

| # | Document Standard | File Link | Scope & Contents |
|---|---|---|---|
| **1** | **`PRD.md`** — Product Requirements Document | [docs/prd.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/prd.md) | Problem statement, personas, goals & non-goals, functional specs, Mermaid user flows, edge cases & success metrics |
| **2** | **`DESIGN.md`** — Design System & UI/UX Specification | [docs/design.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/design.md) | Information architecture, screen layouts, design tokens (`pos.css`), offline SVG charts (`pos-charts.js`), responsive & print rules |
| **3** | **`ARCHITECTURE.md`** — System Architecture & TRD | [docs/trd.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/trd.md) | Hybrid 75/25 Mermaid architecture diagrams, tech stack, service boundaries, data flow, state management & operational constraints |
| **4** | **`DATABASE.md`** — Database & Data Model Specification | [docs/database.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/database.md) | Mermaid ER diagram, all 14 domain table schemas, indexes, foreign keys, migrations, seeding strategy & retention/backup policy |
| **5** | **`API.md`** — API & Route Contracts | [docs/api.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/api.md) | All web routes, RBAC matrix (`pos.auth`), LAN JSON polling schemas, and signed Cloud Hub API (`POST /api/cloud/sync`, `GET /api/cloud/health`) |
| **6** | **`AGENTS.md`** — AI Agent System Specification | [AGENTS.md](file:///d:/planned%20projects/hotel-pos-laravel/AGENTS.md) | All 5 runtime/dev agents (`Gemini CFO`, `Statistical Fallback`, `Nightly Sync`, `Portfolio Benchmark`, `Laravel Boost`), prompts, schemas, handoffs & guardrails |
| **7** | **`README.md`** — Project Overview & Documentation Index | [README.md](file:///d:/planned%20projects/hotel-pos-laravel/README.md) | Value proposition, architecture highlights, quick-start commands, default staff PINs & complete 15-file documentation index |
| **8** | **`IMPLEMENTATION.md`** — Project Implementation Plan | [docs/implementation-plan.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/implementation-plan.md) | Phased delivery milestones (Phases 1–7 completed), dependency graph, module ownership map & Phase 8+ roadmap |
| **9** | **`SETUP.md`** — Setup & Initialisation Guide | [docs/setup.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/setup.md) | Toolchain prerequisites, step-by-step installation, full `.env` configuration reference, DB bootstrapping & Windows one-click scripts |
| **10** | **`FEATURES.md`** — Feature Development Specification | [docs/features.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/features.md) | Detailed specs for all 9 modules, user stories, GST/discount/split math formulas, Mermaid state machines & acceptance criteria |
| **11** | **`SECURITY.md`** — Authentication & Security Specification | [docs/security.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/security.md) | PIN session lifecycle, RBAC enforcement, non-echoing secret hygiene, Bearer + HMAC-SHA256 cloud transport, zero-PII & anti-theft `audit_logs` |
| **12** | **`MCP.md`** — MCP Connection & Tooling Guide | [docs/mcp.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/mcp.md) | `.mcp.json` (`laravel-boost`), `boost.json`, Gemini API Docs MCP tools, permission boundaries & Artisan CLI fallbacks |
| **13** | **`INTEGRATIONS.md`** — External Integrations Guide | [docs/integrations.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/integrations.md) | Google Gemini Interactions API v1, Cloud Hub sync webhook + retry queue, 80mm/58mm thermal printers, UPI, Excel CSV/JSON exports & Task Scheduler |
| **14** | **`TESTING.md`** — Testing & QA Strategy | [docs/testing-strategy.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/testing-strategy.md) | Test pyramid, PHPUnit commands, 139-assertion feature scenario matrix, `Http::fake()` mocks, manual LAN/printer checklist & quality gates |
| **15** | **`DEBUGGING.md`** — Debugging, Troubleshooting & Deployment | [docs/deployment-plan.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/deployment-plan.md) | Common errors & root-cause fixes, log/tinker diagnostics, dual-mode deployment (`outlet` & `cloud_hub`), smoke checks & rollback steps |
| **PDF** | **Pitch Deck & Executive Overview (PDF & HTML)** | [pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf](file:///d:/planned%20projects/hotel-pos-laravel/pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf) · [pitch/pitch-deck.html](file:///d:/planned%20projects/hotel-pos-laravel/pitch/pitch-deck.html) | Shareable 11-slide executive pitch deck covering the 75% Offline / 25% Cloud AI architecture, unit economics, and deployment model |
