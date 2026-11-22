# Debugging, Troubleshooting & Deployment Guide (`DEBUGGING.md`) — Royal Hotel & Restaurant Hybrid POS

## 1. Dual-Mode Deployment Overview

The exact same repository powers both tiers of the **75% Offline / 25% Cloud AI** architecture:

1. **Hotel Outlet Server (`POS_MODE=outlet`, 75% Offline Core)**: Installed on each hotel's local Windows billing PC/laptop running XAMPP (Apache 2.4 Port `6808` + MySQL `hotel_pos`). Works 100% offline all day and runs a nightly scheduled sync at `01:00 AM`.
2. **Live Cloud Analytics Hub (`POS_MODE=cloud_hub`, 25% Cloud AI & Investor Portal)**: Deployed on an HTTPS-enabled cloud server (VPS, Laravel Cloud, or shared PHP 8.3+ host) to receive nightly snapshots from 1 or many hotels, run Google Gemini AI portfolio analysis, and serve public `/investor/{token}` links to investors anywhere in the world.

---

## 2. Common Errors & Root-Cause Fixes (Troubleshooting Matrix)

| Symptom / Error Message | Root Cause | Exact Fix Command / Action |
|---|---|---|
| **Waiter phone cannot open `http://192.168.1.x:6808` (Connection Timed Out)** | 1. Windows Firewall blocking inbound TCP `6808`.<br>2. Phone is on mobile data or Guest Wi-Fi with AP isolation.<br>3. Laptop Wi-Fi IP changed via DHCP. | 1. Right-click and run `Activate-Hotel-POS-6808.bat` as Administrator to re-apply the `Royal Hotel POS (Port 6808)` firewall rule.<br>2. Connect phone to the same local Wi-Fi router and check the laptop's IP printed by `Activate-Hotel-POS-6808.bat` (`ipconfig`). |
| **Apache2.4 fails to start on Port `6808`** | Syntax error in Apache VirtualHost config or another process occupying port `6808`. | Run `C:\xampp\apache\bin\httpd.exe -t` to inspect syntax, and `netstat -ano \| findstr :6808` in PowerShell to identify any conflicting PID. |
| **Kitchen KDS does not play the audio chime on new KOTs** | Browser autoplay policy blocks Web Audio API until the user taps once on the page. | Tap anywhere on the `/kitchen` screen once after opening (or click the status filter bar) to unlock the browser `AudioContext`. |
| **`POST /api/cloud/sync` returns `HTTP 401: Invalid cloud sync bearer token` or `Invalid payload HMAC signature`** | `cloud_sync_token` mismatch between the local Hotel Outlet and the central Cloud Hub. | Open **Owner (`1111`) → Settings** on BOTH the Local Outlet and the Cloud Hub, paste the exact same secret token into **Cloud Sync Secret Token**, and click **Save Settings**. |
| **Nightly Cloud Sync shows `Status: skipped` in `cloud_sync_logs`** | `cloud_sync_enabled` is unchecked, or `cloud_sync_url` / `cloud_sync_token` is blank in Owner Settings. | Note that local daily snapshots (`daily_analytics_snapshots`) are still built offline! To enable remote upload, enable **Sync Daily Snapshots Every Night** and set the Cloud Hub URL & Token in **Owner → Settings**. |
| **Nightly Cloud Sync shows `Status: failed` (`Offline / network error`)** | Hotel internet was disconnected when the `01:00 AM` task ran, or `cloud_sync_url` is unreachable. | No data is lost — failed snapshots remain queued with `sync_status = 'failed'`. Once internet returns, click **Sync Nightly Snapshots Now** on `/owner/analytics` or run `php artisan pos:sync-cloud --days=7 --ai --manual`. |
| **AI Report badge says `Offline Statistical Engine` instead of `Google Gemini`** | `gemini_api_key` is blank, the machine is offline, or Gemini API returned a non-2xx status (e.g., invalid key or quota limit). | Verify `gemini_api_key` in **Owner → Settings** and inspect `storage/logs/laravel.log` for `Gemini Interactions API non-2xx response`. |
| **Investor link `/investor/{token}` shows `404 Report Unavailable`** | The link was revoked (`is_active = false`), passed its `expires_at` date, or the token was mistyped. | Open `/owner/analytics` → **Investor Share Links** and click **Activate** (or generate a new share link). |
| **Thermal Receipt prints headers/URLs or clips the right edge on 58mm paper** | Browser print margins/headers enabled or `Paper Width` still set to `80mm`. | 1. In **Owner → Settings**, switch **Thermal Paper Width** to `58mm (2 inch)`.<br>2. In the browser Print dialog (`Ctrl+P`), set **Margins: None** and uncheck **Headers and footers**. |
| **`SQLSTATE[HY000] [2002] No connection could be made` on local POS** | XAMPP MySQL service is stopped. | Start MySQL from XAMPP Control Panel (`net start mysql` or `C:\xampp\mysql_start.bat`). |

---

## 3. Logging & Diagnostic Workflows

### 3.1 Inspect Application & AI Logs
```powershell
# Tail the latest 60 lines of the Laravel application log
Get-Content "D:\planned projects\hotel-pos-laravel\storage\logs\laravel.log" -Tail 60

# Stream live logs in real time using Laravel Pail
php artisan pail
```

### 3.2 Inspect Nightly Sync Queue & History via CLI
```powershell
# Check pending/failed daily snapshots and recent CloudSyncLog entries
php artisan tinker --execute "dump(App\Models\DailyAnalyticsSnapshot::select('business_date','net_revenue','sync_status','sync_attempts','last_sync_error')->latest('business_date')->take(7)->get()->toArray());"

php artisan tinker --execute "dump(App\Models\CloudSyncLog::latest()->take(5)->get()->toArray());"
```

### 3.3 Test Manual Cloud Sync & AI Generation
```powershell
# Run manual sync for the last 7 days + trigger AI report generation
php artisan pos:sync-cloud --days=7 --ai --manual
```

### 3.4 Verify Windows Scheduled Task (`01:00 AM`)
```powershell
# Query task status and last run result
schtasks /Query /TN "RoyalHotelPOS_NightlyCloudSync" /V /FO LIST

# Trigger the scheduled task immediately for testing
schtasks /Run /TN "RoyalHotelPOS_NightlyCloudSync"
```

---

## 4. Environment Deployment Steps

### 4.1 Deployment A — Local Hotel Outlet (`POS_MODE=outlet`, Windows XAMPP Port `6808`)
1. **Install & Bootstrap**:
   ```powershell
   cd "D:\planned projects\hotel-pos-laravel"
   composer install --no-dev --optimize-autoloader
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   ```
2. **Activate Apache Port `6808` & Firewall**:
   - Double-click [Activate-Hotel-POS-6808.bat](file:///d:/planned%20projects/hotel-pos-laravel/Activate-Hotel-POS-6808.bat) (accept Administrator prompt).
   - Double-click [Create-Hotel-POS-Shortcut.bat](file:///d:/planned%20projects/hotel-pos-laravel/Create-Hotel-POS-Shortcut.bat) to create the desktop app launcher.
3. **Register `01:00 AM` Nightly Sync**:
   - Configure Cloud Sync URL, Secret Token, and optional Gemini API key in **Owner (`1111`) → Settings**.
   - Double-click [Setup-Nightly-Cloud-Sync.bat](file:///d:/planned%20projects/hotel-pos-laravel/Setup-Nightly-Cloud-Sync.bat) (accept Administrator prompt).

### 4.2 Deployment B — Central Live Cloud Analytics Hub (`POS_MODE=cloud_hub`, Linux/Cloud HTTPS)
1. **Clone & Install on Cloud Server**:
   ```bash
   git clone https://github.com/shubham-bramhane/hotel-pos-laravel.git /var/www/hotel-pos-laravel
   cd /var/www/hotel-pos-laravel
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```
2. **Configure Production `.env`**:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://analytics.yourhotel.com

   POS_MODE=cloud_hub
   POS_CLOUD_SYNC_TOKEN=<64-char-random-secret>
   GEMINI_API_KEY=<your-google-gemini-api-key>
   GEMINI_MODEL=gemini-4-hydrogen
   ```
3. **Migrate, Seed & Cache**:
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. **Configure Cron Scheduler**:
   ```cron
   * * * * * cd /var/www/hotel-pos-laravel && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## 5. Post-Deployment Smoke Tests

1. **Health Endpoints**:
   - `GET http://127.0.0.1:6808/up` → HTTP `200 OK`
   - `GET http://127.0.0.1:6808/api/cloud/health` → `{"ok":true,"mode":"outlet","outlets_connected":1,...}`
2. **PIN Hardening**: Log in as Owner (`1111`) → **Staff** and rotate all default demo PINs (`1111`–`5555`).
3. **End-to-End Sync Smoke Test**: Run `php artisan pos:sync-cloud --days=7 --ai --manual` and confirm `Status: success` (or `skipped` if running purely offline).
4. **Automated Suite Verification**: Run `php artisan test --compact` (`7 passed, 139 assertions`).

---

## 6. Backup & Rollback Procedures

- **Instant Local JSON Backup**: Owner (`1111`) → **Settings → Download Full Database Backup (.json)** + daily MySQL dump (`mysqldump -u root hotel_pos > backup.sql`).
- **Application Code Rollback**:
  ```bash
  git checkout <last-known-good-commit>
  composer install --no-dev --optimize-autoloader
  php artisan optimize:clear
  ```
- **Database Migration Rollback**:
  ```bash
  php artisan migrate:rollback --step=1
  ```
  Then restart `Apache2.4` / `php-fpm` and verify `GET /up` returns HTTP `200`.
