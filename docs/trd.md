# Technical Requirements Document (TRD) — Royal Hotel & Restaurant Hybrid POS

## 1. Hybrid System Architecture (75% Offline / 25% Cloud AI)

Royal Hotel POS uses a **Dual-Mode single Laravel 13 codebase** (`POS_MODE=outlet | cloud_hub`) that separates mission-critical real-time floor execution (75% offline LAN) from nightly multi-outlet intelligence & investor sharing (25% online cloud):

```
┌─────────────────────────────────────────────────────────────────────────┐
│  75% OFFLINE LAYER — HOTEL LOCAL LAN (POS_MODE=outlet, Port 6808)       │
│  [Waiter Phones] [Kitchen KDS Tablet] [Counter Laptop] [80mm Printer]   │
│                           │  1-5 ms Local Wi-Fi                         │
│            [Apache 2.4 + Laravel 13 + Local MySQL: hotel_pos]           │
│              • PosService (ACID DB::transaction billing & KOTs)         │
│              • AnalyticsService (Daily snapshots + offline SVG charts)  │
│              • Scheduler (01:00 AM): php artisan pos:sync-cloud --ai    │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │ Nightly HTTPS POST /api/cloud/sync
                                │ (Bearer Token + HMAC-SHA256 Signature)
                                │ (Auto-retry queue if internet is down)
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  25% CLOUD AI & INVESTOR LAYER — LIVE SERVER (POS_MODE=cloud_hub)       │
│  [Central Laravel 13 Hub + Cloud MySQL]                                 │
│    ├── Ingests signed snapshots from Hotel #1, Hotel #2, Hotel #N       │
│    ├── Google Gemini Interactions API v1 (model: gemini-4-hydrogen)     │
│    │     (with Offline Statistical Engine fallback if key/net absent)   │
│    ├── Multi-Outlet Owner AI Dashboard (/owner/analytics)               │
│    └── Read-Only Tokenized Investor Portal (/investor/{token})          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

| Layer | Technology | Version / Notes |
|---|---|---|
| Backend Framework | Laravel Framework | `^13.17` |
| Language Runtime | PHP | `^8.3` (verified on PHP 8.5) |
| Production Database | MySQL (XAMPP Local / Cloud MySQL) | Local DB `hotel_pos` |
| Test Database | SQLite | In-memory (`:memory:`) via `phpunit.xml` |
| Web Server | Apache 2.4 (XAMPP) / Cloud Nginx+PHP-FPM | Local port `6808` (coexists with `6707`) |
| Frontend & Charts | Blade + `public/css/pos.css` + `public/js/pos-charts.js` | Zero-CDN custom interactive SVG charts (100% offline-capable) |
| AI Engine | Google Gemini Interactions API (`v1`) | `POST https://generativelanguage.googleapis.com/v1/interactions`, default `gemini-4-hydrogen`, JSON Schema structured output + automatic local statistical fallback |
| Testing & Formatting | PHPUnit `^12.5`, Laravel Pint `^1.27`, Laravel Boost `^2.10` | `php artisan test --compact`, `vendor/bin/pint` |

---

## 3. Database Schema (14 Domain Tables)

### 3.1 Core Offline POS Tables (`2026_11_20_000001_create_hotel_pos_tables.php`)
1. `staff_users` — Staff profiles, `role` (`owner|cashier|kitchen|waiter`), unique 4–6 digit `pin_code`, `is_active`.
2. `dining_tables` — Floor map (`code`, `name`, `section`, `capacity`, `status`: `available|occupied|billing`).
3. `menu_categories` & `menu_items` — Categories, dishes, `short_code`, `price`, `is_veg`, `is_available`.
4. `orders` — Dine-in & takeaway bills (`order_no`, `status`, `subtotal`, `discount_*`, `gst_rate`, `cgst_amount`, `sgst_amount`, `round_off`, `grand_total`, `payment_method`, `cash_amount`, `upi_amount`, `opened_at`, `paid_at`).
5. `kots` & `order_items` — Kitchen Order Tickets and snapshotted line items (`item_name`, `unit_price`, `is_veg`, `special_note`, `cancel_reason`).
6. `audit_logs` — Immutable anti-theft trail (`staff_name`, `staff_role`, `action`, `entity_type`, `entity_id`, `details` JSON, `ip_address`).
7. `hotel_settings` — Key-value settings covering hotel profile, GST, thermal paper width, hybrid sync mode/URL/token, and Gemini model/API key.

### 3.2 Hybrid Analytics, Cloud Sync & Investor Tables (`2026_11_21_211625_create_analytics_and_sync_tables.php`)
8. `outlets` — Local hotel or remote synced branches (`outlet_code` unique, `name`, `city`, `state`, `seating_capacity`, `is_local`, `is_active`, `last_synced_at`).
9. `daily_analytics_snapshots` — Idempotent daily rollups (`unique(outlet_id, business_date)`) storing financial totals, payment breakdown, `category_sales` JSON, `top_dishes` JSON, `hourly_sales` JSON, `waiter_performance` JSON, `veg_nonveg_split` JSON, SHA-256 `payload_checksum`, `sync_status` (`pending|synced|failed`), `synced_at`, `sync_attempts`, `last_sync_error`.
10. `ai_insight_reports` — Cached AI/statistical executive reports (`outlet_id`, `scope`: `outlet|portfolio`, `period_from`, `period_to`, `provider`: `gemini|local_fallback`, `model_name`, `executive_summary`, `health_score` 0–100, `highlights`, `risks`, `recommendations`, `investor_narrative`, `metrics_snapshot`).
11. `investor_share_links` — Tokenized investor access records (`token` unique 48-char random string, `outlet_id`, `title`, `investor_name`, `period_days`, `include_ai_insights`, `include_branch_comparison`, `expires_at`, `is_active`, `views_count`, `last_viewed_at`).
12. `cloud_sync_logs` — Audit trail of outbound uploads and inbound hub ingestions (`direction`: `outbound|inbound`, `trigger_type`: `scheduled|manual|api`, `status`: `success|failed|skipped`, `snapshots_count`, `http_status`, `message`, `duration_ms`).

---

## 4. Routing & API Contracts

### 4.1 Core Web & LAN Polling Routes (`routes/web.php`)
- **Auth**: `GET /`, `GET/POST /login`, `POST /logout`
- **Waiter (`pos.auth:waiter,cashier`)**: `GET /waiter`, `GET /waiter/table/{table}`, `POST /waiter/table/{table}/kot`, `POST /waiter/order/{order}/request-bill`, `GET /api/waiter/tables`
- **Kitchen (`pos.auth:kitchen,cashier`)**: `GET /kitchen`, `PATCH /kitchen/kot/{kot}/status`, `PATCH /kitchen/menu/{item}/toggle`, `GET /api/kitchen/live`
- **Counter (`pos.auth:cashier`)**: `GET /counter`, `GET /counter/order/{order}`, `POST /counter/table/{table}/open`, `POST /counter/parcel`, `POST /counter/order/{order}/items|discount|transfer|settle|cancel`, `POST /counter/item/{item}/cancel`, `GET /api/counter/live`
- **Thermal Print (`pos.auth:waiter,kitchen,cashier`)**: `GET /print/bill/{order}`, `GET /print/kot/{kot}`
- **Owner Core (`pos.auth:owner`)**: `GET /owner`, `GET /owner/export-sales`, Menu CRUD, Tables CRUD, Staff CRUD, `GET /owner/audit`, `GET/POST /owner/settings`, `POST /owner/backup`

### 4.2 Hybrid AI Analytics & Investor Routes (`routes/web.php`)
- **Owner Analytics (`pos.auth:owner`)**:
  - `GET /owner/analytics` (`owner.analytics`) — Interactive SVG charts, KPIs, multi-outlet comparison, AI insights, investor link manager, sync logs.
  - `POST /owner/analytics/generate-ai` (`owner.analytics.ai`) — Triggers Gemini AI or offline statistical analysis.
  - `POST /owner/analytics/sync-now` (`owner.analytics.sync`) — Rebuilds recent snapshots and pushes to live cloud server immediately.
  - `POST /owner/analytics/share-links` (`owner.analytics.share.store`) — Generates a new tokenized Investor Share Link.
  - `PATCH /owner/analytics/share-links/{shareLink}/toggle` (`owner.analytics.share.toggle`) — Revokes or re-enables a share link.
  - `DELETE /owner/analytics/share-links/{shareLink}` (`owner.analytics.share.destroy`) — Deletes a share link.
- **Public Tokenized Investor Portal (No PIN Required)**:
  - `GET /investor/{token}` (`investor.report`) — Read-only investor executive report with view counter and print-to-PDF layout; returns `404` when revoked or expired.

### 4.3 Stateless Cloud Sync API (`routes/api.php`)
- `GET /api/cloud/health` (`api.cloud.health`) — Returns deployment mode (`outlet|cloud_hub`), connected outlets count, and server timestamp.
- `POST /api/cloud/sync` (`api.cloud.sync`) — Authenticates `Authorization: Bearer <token>` and optional `X-POS-Signature` (`HMAC-SHA256(timestamp + "." + rawBody, token)`), upserts remote outlet and daily snapshots idempotently, and logs inbound sync.

---

## 5. Core Domain Services & AI Integration

- **`App\Services\PosService`**: Transactional billing, KOTs, GST (half CGST + half SGST + nearest-rupee round-off), split payment math, audit logging, and Indian currency formatting (`formatINR`).
- **`App\Services\AnalyticsService`**: Builds SHA-256 checksummed daily rollups (`buildDailySnapshot`), compares current vs prior equal-length periods for growth %, computes Annualized Run Rate (ARR), and aggregates cross-outlet portfolios using DB-agnostic `whereDate` queries (MySQL & SQLite compatible).
- **`App\Services\GeminiAnalyticsService`**: Sends compact aggregated telemetry (zero PII) to Google Gemini Interactions API v1 (`x-goog-api-key`, `model: gemini-4-hydrogen`) with strict `response_format` JSON Schema; automatically falls back to a deterministic statistical engine when offline, unconfigured, or receiving non-2xx responses.
- **`App\Services\CloudSyncService`**: Handles signed outbound batch uploads, failure queueing (`sync_status = 'failed'`, incremented `sync_attempts`), and authenticated inbound Cloud Hub ingestion.
- **`App\Console\Commands\SyncPosCloudCommand`**: Artisan command `pos:sync-cloud {--days=7} {--ai} {--manual}`, scheduled daily at `01:00` in `routes/console.php` with `withoutOverlapping()`.

---

## 6. Security & Privacy Controls

- **Zero PII in Cloud Sync & Investor Portal**: Daily snapshots contain aggregated financial totals, category/dish sales, and staff name aggregates — never customer phone numbers, raw order notes, or staff PINs.
- **Signed Cloud Transport**: Bearer token verified with constant-time `hash_equals()`, plus HMAC-SHA256 request body signature (`X-POS-Signature`, `X-POS-Timestamp`).
- **Secret Preservation in Settings UI**: Password inputs for `cloud_sync_token` and `gemini_api_key` never echo saved secrets back into HTML values and preserve existing values if left blank on save.
- **Revocable Investor Tokens**: 48-character random tokens with optional `expires_at` and instant toggle revocation.
