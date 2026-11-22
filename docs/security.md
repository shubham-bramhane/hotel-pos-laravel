# Authentication & Security Specification (`SECURITY.md`) — Royal Hotel & Restaurant Hybrid POS

## 1. Security Threat Model & Design Principles

Royal Hotel & Restaurant Hybrid POS protects against two distinct threat environments:
1. **Local Restaurant Floor Threats (75% Offline LAN)**: Unauthorized counter discounts, silent bill deletions after cash collection, waiter/kitchen role privilege escalation, and casual terminal snooping.
2. **Cloud & External Sharing Threats (25% Cloud AI & Investor Layer)**: Spoofed nightly snapshot uploads to the Cloud Hub, secret token leakage in HTML forms, customer PII leakage to external LLMs or investors, and stale investor links remaining active after fundraising concludes.

---

## 2. Authentication & Session Lifecycle

### 2.1 Staff PIN Authentication (`AuthPinController`)
- **Login Flow**:
  1. Staff enters a 4–6 digit numeric PIN on `GET /login` (`POST /login`).
  2. `AuthPinController::login()` looks up an active `StaffUser` where `pin_code = trim($pin)` and `is_active = true`.
  3. On match, the server writes `pos_staff_id` to the encrypted/database-backed Laravel session, calls `$request->session()->regenerate()` to prevent session fixation, logs `staff_login` in `audit_logs` with client IP, and redirects to `$staff->homeRoute()`.
- **Logout Flow (`POST /logout`)**:
  - Protected by CSRF verification (`@csrf`).
  - Removes `pos_staff_id`, calls `$request->session()->invalidate()`, and regenerates the CSRF token (`regenerateToken()`).
- **Instant Lockout**:
  - On every request, `EnsureStaffAuthenticated` reloads `StaffUser::where('is_active', true)->find($staffId)`. If an Owner deactivates a staff member in **Owner → Staff**, their active session is terminated on the very next click or 3-second poll.

---

## 3. Authorization & Role-Based Access Control (RBAC)

Enforced by [EnsureStaffAuthenticated.php](file:///d:/planned%20projects/hotel-pos-laravel/app/Http/Middleware/EnsureStaffAuthenticated.php) (`pos.auth:<roles>`) and `StaffUser::canAccess()`:

| Route Prefix / Endpoint | Allowed Roles | Blocked Roles | Unauthorized Response |
|---|---|---|---|
| `/waiter/*`, `/api/waiter/tables` | `waiter`, `cashier`, `owner` | `kitchen` | Redirect to `/kitchen` (or `403` JSON) |
| `/kitchen/*`, `/api/kitchen/live` | `kitchen`, `cashier`, `owner` | `waiter` | Redirect to `/waiter` (or `403` JSON) |
| `/counter/*`, `/api/counter/live` | `cashier`, `owner` | `waiter`, `kitchen` | Redirect to role home (or `403` JSON) |
| `/print/bill/*`, `/print/kot/*` | `waiter`, `kitchen`, `cashier`, `owner` | Unauthenticated | Redirect to `/login` |
| `/owner/*` (Dashboard, Menu, Tables, Staff, Audit, Settings, Backup, AI Analytics) | `owner` **only** | `cashier`, `kitchen`, `waiter` | Redirect to role home (or `403` JSON) |
| `/investor/{token}` | Public capability token | Revoked / Expired token | HTTP `404` (`investor.unavailable`) |
| `POST /api/cloud/sync` | Signed Bearer Token + HMAC | Missing / Invalid Token or HMAC | HTTP `401 Unauthorized` JSON |

---

## 4. Secrets Management & Credential Hygiene

1. **Zero Secret Echo in Settings UI (`resources/views/owner/settings.blade.php`)**:
   - `cloud_sync_token` and `gemini_api_key` are rendered as `<input type="password" value="">` with a status badge indicating whether a secret is currently stored (`Configured` vs `Not Set`).
   - Saved secret values are **never** echoed back into the DOM or page source.
2. **Non-Destructive Blank Saves (`OwnerController::saveSettings`)**:
   - When an Owner updates general settings (such as GST rate or hotel phone number) and leaves the password inputs for `cloud_sync_token` or `gemini_api_key` blank, `OwnerController::saveSettings()` preserves the existing stored secret unless the explicit `clear_cloud_sync_token` or `clear_gemini_api_key` checkbox is checked.
3. **Environment Isolation**:
   - `.env` is excluded from Git via `.gitignore`.
   - `.env.example` contains only safe blank placeholders for `POS_CLOUD_SYNC_TOKEN=` and `GEMINI_API_KEY=`.

---

## 5. Cloud Sync Transport Security (Outlet → Cloud Hub)

Nightly and manual snapshot uploads from a local hotel outlet to the live Cloud Hub (`POST /api/cloud/sync`) use dual-layer authentication implemented in [CloudSyncService.php](file:///d:/planned%20projects/hotel-pos-laravel/app/Services/CloudSyncService.php):

1. **Bearer Token Verification**:
   - Outbound request sends `Authorization: Bearer <cloud_sync_token>`.
   - Cloud Hub compares the token against its configured `cloud_sync_token` using PHP's constant-time `hash_equals($expectedToken, trim($bearerToken))` to prevent timing attacks.
2. **HMAC-SHA256 Payload Integrity Signature**:
   - Outbound request computes:
     ```php
     $timestamp = (string) now()->timestamp;
     $signature = hash_hmac('sha256', $timestamp.'.'.$rawJson, $syncToken);
     ```
   - Sent in headers `X-POS-Timestamp` and `X-POS-Signature`.
   - Cloud Hub recomputes the HMAC over the raw request body and verifies it with `hash_equals($expectedSignature, $signature)`. Any tampered financial snapshot payload is rejected with HTTP `401`.

---

## 6. Data Protection, Zero-PII & Anti-Theft Controls

### 6.1 Zero Customer PII in Cloud, AI & Investor Layers
- `orders.customer_phone` and `orders.customer_name` remain strictly inside the local hotel's `orders` table for counter parcel lookup.
- `DailyAnalyticsSnapshot::toSyncPayload()`, `GeminiAnalyticsService::callGeminiInteractionsApi()`, and `InvestorPortalController::show()` transmit and render **only aggregated metrics** (revenue, order counts, payment split, dish/category totals, waiter order totals). No customer phone numbers, raw order notes, or staff PINs ever leave the local database or appear on `/investor/{token}`.

### 6.2 Immutable Anti-Theft Audit Trail (`audit_logs`)
- Every sensitive action records an immutable row in `audit_logs` with `staff_id`, snapshotted `staff_name` and `staff_role`, `action`, `entity_type`, `entity_id`, JSON `details`, and client `ip_address`:
  - `staff_login`
  - `order_opened`, `parcel_opened`, `kot_sent`, `kot_status_updated`, `bill_requested`
  - `item_cancelled` (mandatory reason + item name + qty + rupee amount)
  - `order_cancelled` (mandatory reason)
  - `discount_applied` (type, value, rupee discount amount, reason)
  - `table_transferred` (source table → target table)
  - `bill_settled` (payment method + grand total)
  - `menu_item_added`, `menu_item_updated`, `menu_item_deleted`, `menu_stock_toggled`
  - `backup_downloaded`, `ai_insight_generated`, `cloud_sync_completed`
  - `investor_link_created`, `investor_link_toggled`, `investor_link_deleted`

### 6.3 CSRF & SQL Injection Protection
- All state-changing web routes (`POST`, `PUT`, `PATCH`, `DELETE`) enforce Laravel's `VerifyCsrfToken` middleware.
- All database queries use Eloquent ORM / parameterized query bindings; Blade templates use escaped `{{ ... }}` output by default.

---

## 7. Security Hardening Checklist for Production Go-Live

1. **Rotate Default PINs Immediately**: Log in as Owner (`1111`) → **Staff** and change all five default demo PINs (`1111`, `2222`, `3333`, `4444`, `5555`) to unique 6-digit PINs before opening for service.
2. **Enforce HTTPS on Cloud Hub**: Deploy `POS_MODE=cloud_hub` behind TLS/HTTPS (`APP_URL=https://...`, `APP_ENV=production`, `APP_DEBUG=false`).
3. **Generate Strong Sync Token**: Use a 64-character random secret (`php -r "echo bin2hex(random_bytes(32));"`) for `POS_CLOUD_SYNC_TOKEN`.
4. **Set Expiry on Investor Links**: Always set `expires_in_days` (e.g., `14` or `30` days) when generating `/investor/{token}` links for external due diligence.
