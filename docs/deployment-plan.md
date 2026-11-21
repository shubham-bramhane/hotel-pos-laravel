# Deployment Plan — Royal Hotel & Restaurant Hybrid POS

## 1. Dual-Mode Deployment Overview

The exact same repository powers BOTH tiers of the **75% Offline / 25% Cloud AI** architecture:

1. **Hotel Outlet Server (`POS_MODE=outlet`, 75% Offline Core)**: Installed on each hotel's local Windows billing PC/laptop running XAMPP (Apache 2.4 Port `6808` + MySQL `hotel_pos`). Works 100% offline all day and runs a nightly scheduled sync at `01:00 AM`.
2. **Live Cloud Analytics Hub (`POS_MODE=cloud_hub`, 25% Cloud AI & Investor Portal)**: Deployed on an HTTPS-enabled cloud server (VPS, Laravel Cloud, or shared PHP 8.3+ host) to receive nightly snapshots from 1 or many hotels, run Google Gemini AI portfolio analysis, and serve public `/investor/{token}` links to investors anywhere in the world.

---

## 2. Deployment A — Local Hotel Outlet (Windows XAMPP LAN)

### 2.1 Prerequisites
- Windows 10/11 (Admin rights), XAMPP at `C:\xampp` (`Apache2.4` + `MySQL` + `PHP >= 8.3`), Composer 2.x.
- Local Wi-Fi router with DHCP static IP reservation for the billing laptop (e.g., `192.168.1.8`).
- Optional: 80mm/58mm thermal receipt printer.

### 2.2 Install & Migrate
```powershell
git clone https://github.com/shubham-bramhane/hotel-pos-laravel.git "D:\planned projects\hotel-pos-laravel"
cd "D:\planned projects\hotel-pos-laravel"
composer install --no-dev --optimize-autoloader
copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```
Set `.env` for local MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=hotel_pos`, `POS_MODE=outlet`, `POS_OUTLET_CODE=ROYAL-YTL-01`).

### 2.3 Apache Port `6808` & One-Click Launchers
1. Ensure `Listen 6808` and the `<VirtualHost *:6808>` pointing to `public/` exist in XAMPP Apache config.
2. Double-click `Activate-Hotel-POS-6808.bat` (validates Apache syntax, restarts `Apache2.4`, opens Firewall TCP `6808`, verifies `/up` on ports `6808` and `6707`).
3. Run `Create-Hotel-POS-Shortcut.bat` to place the standalone desktop app icon (`Open-Hotel-POS-App.vbs`) on the billing counter Desktop.

### 2.4 Enable Nightly Cloud Sync (01:00 AM Windows Task Scheduler)
1. Log in as Owner (`1111`) → **Settings** → **Hybrid Cloud Sync & Google Gemini AI**:
   - Set **Server Deployment Mode**: `Local Hotel Outlet`.
   - Set unique **Outlet Code** (e.g., `ROYAL-YTL-01`, `ROYAL-PUN-02`).
   - Enable **Sync Daily Snapshots Every Night**, enter your Live Cloud Hub URL (`https://<your-cloud-domain>/api/cloud/sync`), and paste the shared **Cloud Sync Secret Token**.
   - Optionally add your **Google Gemini API Key** (model `gemini-4-hydrogen`).
2. Double-click **`Setup-Nightly-Cloud-Sync.bat`** (Accept Admin prompt) — registers Windows Scheduled Task `RoyalHotelPOS_NightlyCloudSync` to execute:
   ```powershell
   php artisan pos:sync-cloud --days=3 --ai
   ```
   every night at `01:00 AM`. If the hotel's internet is down at 1 AM, failed days remain queued and automatically upload on the next successful sync.

---

## 3. Deployment B — Live Cloud Analytics Hub (`POS_MODE=cloud_hub`)

Deploy the same repository to any Linux VPS / Laravel Cloud / PHP 8.3+ HTTPS host:

```bash
git clone https://github.com/shubham-bramhane/hotel-pos-laravel.git
cd hotel-pos-laravel
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Configure production `.env` on the Cloud Hub:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://analytics.yourhotel.com

POS_MODE=cloud_hub
POS_CLOUD_SYNC_TOKEN=<generate-64-char-random-secret>
GEMINI_API_KEY=<your-google-ai-studio-api-key>
GEMINI_MODEL=gemini-4-hydrogen
```

Run migrations, seed initial owner account, and optimize caches:
```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Verify Cloud Hub readiness:
- `GET https://analytics.yourhotel.com/up` → HTTP `200`
- `GET https://analytics.yourhotel.com/api/cloud/health` → `{"ok":true,"mode":"cloud_hub",...}`
- Immediately change the default Owner PIN (`1111`) on the live server under **Owner → Staff**!

Add standard Linux cron for Laravel Scheduler on the Cloud Hub:
```cron
* * * * * cd /var/www/hotel-pos-laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Go-Live & Post-Deployment Smoke Checks

1. Replace all default demo PINs (`1111`–`5555`) on both Outlet and Cloud Hub.
2. Run a manual sync test from Outlet: `php artisan pos:sync-cloud --days=7 --ai --manual` and verify `Status: success`.
3. Open Cloud Hub → **Owner → AI Analytics**, confirm the outlet's charts & Gemini AI report load, create a test **Investor Share Link**, open `/investor/{token}` in an incognito tab, and verify **Print / Save Investor PDF**.

---

## 5. Backup & Rollback Procedures

- **Daily Local Backup**: Owner → **Settings → Download Full Database Backup (.json)** + scheduled `mysqldump -u root hotel_pos > backup.sql`.
- **Cloud Hub Backup**: Automated daily MySQL snapshots on cloud host.
- **Code Rollback**:
  ```bash
  git checkout <last-known-good-commit>
  composer install --no-dev --optimize-autoloader
  php artisan optimize:clear
  ```
- **Migration Rollback**: `php artisan migrate:rollback --step=1` (or restore pre-upgrade `.sql` dump), then restart Apache/PHP-FPM and verify `/up`.
