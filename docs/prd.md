# Product Requirements Document (PRD) — Royal Hotel & Restaurant LAN POS

## 1. Product Overview

**Royal Hotel & Restaurant LAN POS** is a 100% self-hosted, offline-first Point of Sale (POS) web application designed for Indian family restaurants, dhabas, cafes, and hotels. It runs on a local billing PC/laptop over the restaurant's Wi-Fi router (LAN) with **zero internet dependency** and **zero recurring SaaS subscription fees**.

Staff members access role-tailored screens directly from their existing Android/iOS phones, kitchen tablets, or the billing counter computer through a browser or standalone desktop shortcut.

---

## 2. Problem Statement

Small and mid-sized restaurants in India face recurring operational pain points with existing billing tools:

- **SaaS Lock-In & Recurring Costs**: Cloud POS vendors charge ₹10,000–₹25,000+/year plus per-device or add-on KDS fees.
- **Internet Outages Halt Billing**: Cloud-dependent POS systems freeze during Wi-Fi/ISP outages during peak dinner hours.
- **Paper KOT Chaos & Leakage**: Handwritten slips get lost between floor and kitchen, dishes are forgotten, and unlogged item cancellations or arbitrary discounts cause daily cash leakage.
- **Slow Staff Onboarding**: Complex multi-screen desktop software slows down waiters and requires training.

---

## 3. Goals & Non-Goals

### Goals
1. **100% Offline LAN Operation**: Full order, kitchen, billing, reporting, and thermal printing functionality with zero internet access.
2. **Role-Isolated, Fast PIN Access**: 4–6 digit PIN login with instant screen routing and strict role permissions.
3. **End-to-End Service Loop**: Waiter mobile ordering → Live Kitchen Display System (KDS) → Cashier Counter billing & thermal receipt printing → Owner analytics & anti-theft audit trail.
4. **Indian Restaurant Readiness**: Veg/Non-Veg indicators, dish short codes (`PBM`, `BN`, `DT`), CGST + SGST calculation, rupee round-off, INR formatting (`₹1,23,456`), Cash/UPI/Card/Split settlement, and 58mm/80mm thermal receipt layouts.
5. **Anti-Theft Accountability**: Immutable audit logging for every login, KOT, item cancellation (mandatory reason), discount, table transfer, order cancellation, settlement, menu edit, and DB backup.

### Non-Goals (Current Version)
- Cloud multi-branch sync or online aggregator integrations (Swiggy/Zomato APIs).
- Inventory/recipe raw-material stock deduction.
- Direct UPI payment gateway webhooks (UPI is tracked as a settled payment mode with configurable store UPI ID).
- Native Android/iOS app store builds (served as a responsive web app + PWA manifest + Windows desktop launcher).

---

## 4. Target Users & Personas

| Role | Default Demo PIN | Primary Device | Core Responsibilities |
|---|---|---|---|
| **Waiter** | `4444`, `5555` | Personal Android/iOS phone on LAN Wi-Fi | View live table floor map by section, open tables, punch orders via category/short code, add kitchen notes, send KOTs, track Ready KOT alerts, request bills. |
| **Kitchen (KDS)** | `3333` | Kitchen tablet / monitor | Monitor incoming KOTs in real time (3s auto-refresh + audio chime), update status (`Pending → Preparing → Ready → Served`), toggle out-of-stock ("86") dishes, print KOT slips. |
| **Cashier / Counter** | `2222` | Billing Counter Laptop/PC | Manage dine-in & takeaway/parcel orders, add items, transfer tables, apply flat/% discounts with reason, cancel items/orders with reason, settle Cash/UPI/Card/Split bills, print 58mm/80mm thermal bills. |
| **Owner / Admin** | `1111` | Counter PC / Phone on LAN | Monitor revenue KPIs, payment split, running floor value, top dishes, waiter performance; export sales CSV; manage menu, tables, staff PINs, hotel/GST/printer settings; inspect Anti-Theft Audit Log; download JSON DB backups. |

---

## 5. Functional Requirements

### 5.1 Fast PIN Authentication & RBAC
- Staff sign in using a unique 4–6 digit PIN (`/login`) with one-tap quick switcher support.
- Deactivated staff (`is_active = false`) cannot log in and active sessions are immediately invalidated.
- Role hierarchy & screen access:
  - **Waiter**: Waiter screens + thermal print views.
  - **Kitchen**: Kitchen KDS screens + thermal print views.
  - **Cashier**: Counter billing + Waiter + Kitchen + thermal print views.
  - **Owner**: All screens including Owner Dashboard, Menu, Tables, Staff, Audit Log, Settings, and Backup.

### 5.2 Waiter Floor Map & Mobile Ordering
- Displays active dining tables grouped by section (`Main Hall`, `AC Family`, `Garden`) with live color-coded states: **Available**, **Occupied**, **Billing Requested**.
- Shows running order total, assigned waiter, elapsed duration, and Ready KOT badges via live polling (`/api/waiter/tables`).
- Menu search by dish name or short code (e.g., `PBM` → Paneer Butter Masala), Veg/Non-Veg markers, out-of-stock lockouts, quantity controls, per-item special notes (e.g., "Less spicy"), and KOT-level notes.
- Supports sending multiple incremental KOTs to the same open table order and requesting the bill (`status = billing`).

### 5.3 Live Kitchen Display System (KDS)
- Auto-polls `/api/kitchen/live` every 3 seconds with new-order chime alert and elapsed timer per KOT.
- Orders KOTs by urgency (`pending` → `preparing` → `ready`, oldest first).
- One-tap status progression (`Pending → Preparing → Ready → Served`) syncing both KOT and non-cancelled `order_items`.
- Kitchen stock toggle ("86" item Out of Stock / In Stock) logged in the audit trail.

### 5.4 Counter Billing, Parcel & Thermal Printing
- Supports both **Dine-In** (linked to a `dining_table`) and **Takeaway / Parcel** orders (with optional customer name and phone).
- Cashier can open empty tables, create parcels, punch quick items (generating a KOT), transfer an active dine-in order to another vacant table, cancel individual items or entire unpaid orders (reason required), and apply flat or percentage discounts (capped at subtotal / 100%).
- Real-time GST calculation: splits configurable GST rate evenly into CGST + SGST on taxable amount (`subtotal - discount`), rounds grand total to nearest whole rupee, and records `round_off`.
- Settlement supports **Cash**, **UPI**, **Card**, and **Split (Cash + UPI)**; settling marks remaining KOTs/items served and frees the dining table.
- Thermal print views for **Customer Bill** (`/print/bill/{order}`, consolidating duplicate items at identical unit price) and **Kitchen KOT** (`/print/kot/{kot}`), configurable for **58mm** or **80mm** receipt paper.

### 5.5 Owner Dashboard, Master Data & Anti-Theft Controls
- **Sales & Control Dashboard**: Filterable by `Today`, `Yesterday`, `7 Days`, `This Month`, or `Custom` date range. Displays Total Sales Revenue, Bill Count, Average Bill Value, Payment Split (Cash / UPI / Card), Running Floor Value, Discounts & Cancellations, GST collected, Top 10 Selling Dishes, Waiter Performance, and Recent Settled Bills.
- **Sales CSV Export**: UTF-8 BOM CSV download compatible with Excel for the selected date range.
- **Menu Manager**: Add categories; create, edit, toggle stock, and delete dishes with short codes and veg/non-veg flags.
- **Table Manager**: Add/remove tables across sections (blocks deletion while an order is active).
- **Staff Manager**: Create/update staff profiles, roles, 4–6 digit PINs, and active status.
- **Anti-Theft Audit Log**: Paginated log filterable by action type, recording actor, role, entity, structured JSON details, IP address, and timestamp.
- **Settings & Backup**: Configure hotel name, address, phone, GSTIN, GST toggle & rate (0–28%), thermal paper width (58/80mm), UPI ID, and bill footer; one-click full JSON database backup export.

---

## 6. Key User Flows

1. **Dine-In Order to Settlement**: Waiter PIN login → Tap Table → Select dishes + notes → Send KOT → Kitchen prepares & marks Ready → Waiter serves & Requests Bill → Cashier reviews/discounts/settles (Cash/UPI/Card/Split) → Prints thermal bill → Table becomes Available → Revenue updates on Owner Dashboard.
2. **Walk-In Takeaway / Parcel**: Cashier creates Parcel order (customer name/phone) → Adds items → KOT sent to Kitchen → Settles payment → Prints receipt.
3. **Exception Handling (Table Transfer / Item Cancel)**: Cashier transfers active order from `T1` to vacant `T2` (`T1` freed, `T2` occupied) or cancels an item with a mandatory reason → Bill recalculates automatically → Event recorded in Audit Log.

---

## 7. Non-Functional Requirements

- **Offline Resilience**: Zero external CDN dependencies required for core runtime; operates over local Wi-Fi router without WAN internet.
- **Latency**: LAN page loads and JSON poll responses < 200 ms on local Apache/MySQL hardware.
- **Data Integrity**: All multi-table state transitions (order opening, KOT creation, cancellation, transfer, settlement) must be ACID-atomic via database transactions.
- **Usability**: Touch-friendly controls for waiter phones and kitchen tablets; keyboard/mouse efficient counter UI; standalone Chrome/Edge app window launcher on Windows.
- **Coexistence**: Must run on port `6808` without conflicting with existing local XAMPP services (e.g., port `6707`).

---

## 8. Success Metrics

- Order-to-KDS visibility latency ≤ 3 seconds over local Wi-Fi.
- 100% of discounts, cancellations, transfers, and settlements traceable in `audit_logs`.
- Zero failed transactions from concurrent table operations or partial writes.
- End-to-end automated feature test suite passing cleanly (`php artisan test`).
