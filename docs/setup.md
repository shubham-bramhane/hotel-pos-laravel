# Setup & Initialisation Guide (`SETUP.md`) — Royal Hotel & Restaurant Hybrid POS

## 1. Prerequisites & Toolchain Versions

| Component | Minimum Version | Verified Local Version | Purpose |
|---|---|---|---|
| **Operating System** | Windows 10/11 (Outlet) or Linux/Windows (Cloud Hub) | Windows 11 Pro | Hosts local LAN POS server or Cloud Hub |
| **PHP** | `>= 8.3` (`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `xml`) | `PHP 8.5.12` (`C:\xampp\php\php.exe`) | Executes Laravel 13 application & Artisan scheduler |
| **Composer** | `>= 2.6` | `Composer 2.11.1` | PHP dependency management |
| **Database** | MySQL `8.0+` / MariaDB `10.4+` (Prod) + SQLite (Tests) | XAMPP MySQL (`hotel_pos`) | Transactional POS & analytics storage |
| **Web Server** | Apache `2.4` (XAMPP Port `6808`) or Nginx | XAMPP `Apache2.4` on Port `6808` | Multi-device LAN HTTP server (coexists with port `6707`) |
| **Node.js & NPM** | `>= 20.x` *(Optional)* | Vite `8.x` | Only needed if rebuilding Vite bundles; core POS uses zero-build `public/css/pos.css` & `public/js/pos-charts.js` |

Verify PHP and Composer availability in PowerShell:
```powershell
php -v
composer -V
```

---

## 2. Step-by-Step Installation

### 2.1 Clone & Install Dependencies
```powershell
git clone https://github.com/shubham-bramhane/hotel-pos-laravel.git "D:\planned projects\hotel-pos-laravel"
cd "D:\planned projects\hotel-pos-laravel"
composer install
```

### 2.2 Create `.env` & Generate Application Key
```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

### 2.3 Environment Variables Reference (`.env`)

Configure `.env` according to whether this machine is a **Local Hotel Outlet (`POS_MODE=outlet`)** or a **Live Cloud Analytics Hub (`POS_MODE=cloud_hub`)**:

```ini
APP_NAME="Royal Hotel POS"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:6808

# Database Configuration (MySQL for Production / XAMPP)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_pos
DB_USERNAME=root
DB_PASSWORD=

# Session & Cache Drivers
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# Hybrid 75% Offline POS / 25% Cloud AI Analytics & Nightly Sync
# POS_MODE: 'outlet' (Local Hotel LAN Server) or 'cloud_hub' (Central Live Analytics Server)
POS_MODE=outlet
POS_OUTLET_CODE=ROYAL-YTL-01
POS_CLOUD_SYNC_ENABLED=false
POS_CLOUD_SYNC_URL=https://analytics.yourhotel.com/api/cloud/sync
POS_CLOUD_SYNC_TOKEN=replace-with-64-char-shared-secret
POS_NIGHTLY_SYNC_TIME=01:00
POS_CLOUD_SYNC_TIMEOUT=15

# Google Gemini API (25% Cloud AI Insights — falls back to Offline Statistical Engine when empty)
GEMINI_API_KEY=
GEMINI_MODEL=gemini-4-hydrogen
GEMINI_API_ENDPOINT=https://generativelanguage.googleapis.com/v1/interactions
GEMINI_TIMEOUT=25
```

> [!NOTE]
> Almost all operational settings (`hotel_name`, `gst_rate`, `paper_width`, `pos_mode`, `outlet_code`, `cloud_sync_enabled`, `cloud_sync_url`, `cloud_sync_token`, `gemini_api_key`, `gemini_model`) can also be configured live from the browser under **Owner (`1111`) → Settings** (`hotel_settings` table overrides `.env` defaults).

---

## 3. Database & Seeder Bootstrapping

Ensure the `hotel_pos` database exists in MySQL (or use `DB_CONNECTION=sqlite` for quick evaluation), then run migrations and seed initial data:

```powershell
# Create MySQL database if not already created
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS hotel_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run all migrations (14 POS/Analytics domain tables + 4 framework tables)
php artisan migrate --force

# Seed default settings, 5 staff PINs, 14 tables, 6 categories & 29 Indian dishes
php artisan db:seed --force
```

---

## 4. Windows One-Click Launchers & Nightly Sync Setup

The repository includes four turnkey Windows automation scripts in the project root:

1. **[Activate-Hotel-POS-6808.bat](file:///d:/planned%20projects/hotel-pos-laravel/Activate-Hotel-POS-6808.bat)**:
   - Requests Administrator privileges.
   - Runs `httpd.exe -t` to validate XAMPP Apache configuration syntax.
   - Restarts the `Apache2.4` Windows service.
   - Opens inbound TCP port `6808` in Windows Firewall (`Royal Hotel POS (Port 6808)`).
   - Verifies `http://127.0.0.1:6808/up` (and confirms port `6707` coexistence) and prints the laptop's Wi-Fi LAN IP for Waiter phones.
2. **[Open-Hotel-POS-App.vbs](file:///d:/planned%20projects/hotel-pos-laravel/Open-Hotel-POS-App.vbs)**:
   - Launches the POS in standalone chromeless App Mode (`--app=http://127.0.0.1:6808`) using Microsoft Edge or Google Chrome.
3. **[Create-Hotel-POS-Shortcut.bat](file:///d:/planned%20projects/hotel-pos-laravel/Create-Hotel-POS-Shortcut.bat)**:
   - Creates a one-click desktop shortcut on the billing counter PC pointing to `Open-Hotel-POS-App.vbs`.
4. **[Setup-Nightly-Cloud-Sync.bat](file:///d:/planned%20projects/hotel-pos-laravel/Setup-Nightly-Cloud-Sync.bat)**:
   - Registers the Windows Scheduled Task `RoyalHotelPOS_NightlyCloudSync` to execute `php artisan pos:sync-cloud --days=3 --ai` every night at `01:00 AM` with highest privileges.

---

## 5. Local Run Verification Checklist

Run these commands after installation to verify 100% readiness:

```powershell
# 1. Run the automated PHPUnit test suite (in-memory SQLite, zero network required)
php artisan test --compact

# 2. Verify code formatting with Laravel Pint
vendor/bin/pint --dirty --format agent

# 3. Test the Nightly Cloud Sync & AI Snapshot command manually
php artisan pos:sync-cloud --days=7 --ai --manual

# 4. Verify HTTP health endpoints on Port 6808
Invoke-WebRequest -Uri "http://127.0.0.1:6808/up" -UseBasicParsing
Invoke-WebRequest -Uri "http://127.0.0.1:6808/api/cloud/health" -UseBasicParsing
```

### Default Login PINs for Verification
- **Owner (`1111`)**: Open `http://127.0.0.1:6808/login`, enter `1111`, and verify `/owner` and `/owner/analytics`.
- **Cashier (`2222`)**: Verify `/counter` floor grid, parcel creation, and thermal receipt preview.
- **Kitchen (`3333`)**: Verify `/kitchen` live KDS polling and 86 stock toggles.
- **Waiter (`4444` / `5555`)**: Open `http://<LAN-IP>:6808/login` from a phone on the same Wi-Fi, enter `4444`, punch a KOT on Table `T1`, and watch it appear on `/kitchen` within 3 seconds.
