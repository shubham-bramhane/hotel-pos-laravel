# Deployment Plan — Royal Hotel & Restaurant LAN POS

## 1. Deployment Overview

This application is deployed on-premises on a **Windows billing PC/laptop** running **XAMPP (Apache 2.4 + MySQL + PHP 8.3+)** over the restaurant's local Wi-Fi network on **TCP Port `6808`**. No cloud server, domain name, or internet connection is required during daily operations.

---

## 2. Prerequisites

- **OS**: Windows 10 / 11 (Administrator access for Apache service & Firewall setup).
- **Runtime**: PHP `>= 8.3` with extensions (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`), Composer `2.x`.
- **Web & DB Stack**: XAMPP installed at `C:\xampp` with **Apache2.4** Windows service and **MySQL** running.
- **Network**: Local Wi-Fi router with billing PC connected (assign a **static LAN IP** via router DHCP reservation, e.g., `192.168.1.8`, so waiter/kitchen bookmarks never break).
- **Hardware (Optional)**: 80mm or 58mm USB/LAN thermal receipt printer installed as default Windows printer.

---

## 3. Initial Environment Setup

### 3.1 Clone & Install Dependencies
```powershell
git clone https://github.com/shubham-bramhane/hotel-pos-laravel.git "D:\planned projects\hotel-pos-laravel"
cd "D:\planned projects\hotel-pos-laravel"
composer install --no-dev --optimize-autoloader
```

### 3.2 Environment Configuration (`.env`)
Copy `.env.example` to `.env` and configure for local MySQL (`hotel_pos`):
```ini
APP_NAME="Royal Family Restaurant & Hotel"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://127.0.0.1:6808

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_pos
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```
Generate application key, create the `hotel_pos` MySQL database, migrate, and seed:
```powershell
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3.3 Apache 2.4 VirtualHost Configuration (Port `6808`)
1. In `C:\xampp\apache\conf\httpd.conf`, ensure `Listen 6808` is added (alongside any existing port such as `Listen 6707`).
2. In `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, configure the VirtualHost pointing to `public/`:
```apache
<VirtualHost *:6808>
    DocumentRoot "D:/planned projects/hotel-pos-laravel/public"
    <Directory "D:/planned projects/hotel-pos-laravel/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 4. One-Click Activation & Client Setup

### 4.1 Start LAN Server & Firewall
Double-click `Activate-Hotel-POS-6808.bat` (accept the Windows UAC Admin prompt). The script automatically:
1. Validates Apache configuration syntax (`C:\xampp\apache\bin\httpd.exe -t`).
2. Restarts the `Apache2.4` Windows service.
3. Creates/refreshes Windows Inbound Firewall rule **`Royal Hotel POS (Port 6808)`** on TCP `6808`.
4. Verifies `GET /up` health endpoints on both **Port `6808`** (Hotel POS) and **Port `6707`** (coexisting portal).
5. Prints the exact laptop URL (`http://127.0.0.1:6808`) and Wi-Fi LAN URL (`http://<LAN-IP>:6808`).

### 4.2 Billing Counter Desktop App Mode
- Run `Create-Hotel-POS-Shortcut.bat` once to place **`Royal Hotel POS.lnk`** on the Desktop.
- Double-clicking `Open-Hotel-POS-App.vbs` (or the Desktop shortcut) opens the POS in Chrome/Edge `--app` standalone window mode.

### 4.3 Waiter Phones & Kitchen Tablet
- Connect devices to the same local Wi-Fi router.
- Open `http://<LAN-IP>:6808` in Chrome/Safari and choose **Add to Home Screen** (uses `public/manifest.json` + `public/icon.svg` for standalone app feel).

---

## 5. Post-Deployment Go-Live Checklist

1. **Change Default PINs**: Log in as Owner (`1111`) → **Staff** and replace all demo PINs (`1111`, `2222`, `3333`, `4444`, `5555`).
2. **Configure Restaurant Details**: Owner → **Settings** → set Hotel Name, Address, Phone, GSTIN, GST rate (or toggle off), UPI ID, thermal paper width (`80mm` or `58mm`), and receipt footer.
3. **Customize Menu & Floor Plan**: Update categories/dishes under **Owner → Menu** and dining tables/sections under **Owner → Tables**.
4. **Smoke Test**:
   - `GET http://127.0.0.1:6808/up` returns HTTP `200`.
   - Complete 1 test order (Waiter → Kitchen → Counter → Thermal Print → Owner Dashboard).
   - Cancel/clean test data or start fresh before opening shift.

---

## 6. Updates, Backup & Rollback Procedures

### 6.1 Routine Code Update
```powershell
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Then run `Activate-Hotel-POS-6808.bat` if Apache config changed.

### 6.2 Backup Strategy (Daily Recommended)
- **App-Level JSON Backup**: Owner → **Settings → Download Backup** (`POST /owner/backup`) downloads `hotel_pos_backup_YYYY-MM-DD_HHMMSS.json`. Save a copy to a USB drive or external folder daily.
- **MySQL Dump (Full Relational Backup)**:
```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root hotel_pos > "D:\backups\hotel_pos_$(Get-Date -Format yyyyMMdd_HHmm).sql"
```

### 6.3 Rollback Procedure
1. **Code Rollback**:
   ```powershell
   git log -n 5 --oneline
   git checkout <last-known-good-commit>
   php artisan optimize:clear
   ```
2. **Database Rollback** (if a migration caused issues):
   - Roll back latest migration batch: `php artisan migrate:rollback --step=1`
   - Or restore the pre-upgrade `.sql` dump:
     ```powershell
     C:\xampp\mysql\bin\mysql.exe -u root hotel_pos < "D:\backups\hotel_pos_<timestamp>.sql"
     ```
3. Restart Apache via `Activate-Hotel-POS-6808.bat` and verify `http://127.0.0.1:6808/up`.
