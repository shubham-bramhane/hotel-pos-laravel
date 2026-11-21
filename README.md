# Royal Hotel / Restaurant Hybrid POS (75% Offline LAN + 25% Cloud AI Analytics)

An offline-first **Hotel & Restaurant POS** built on **Laravel 13 + MySQL + XAMPP Apache 2.4 (Port `6808`)** engineered with a **75% Offline / 25% Cloud AI Hybrid Architecture**:

- **75% Offline Local LAN Core**: Waiter mobile ordering, Live Kitchen Display System (KDS), Billing Counter, GST & 80mm/58mm Thermal Printing, and Anti-Theft Audit Trail run completely over your local Wi-Fi router with **zero internet dependency during rush hours**.
- **25% Nightly Cloud Sync, AI Analytics & Investor Portal**: Every night (`01:00 AM`), signed daily performance snapshots sync automatically to your live **Cloud Analytics Hub** (same Laravel codebase in `cloud_hub` mode) — unlocking interactive revenue graphs, payment/category pie charts, multi-branch benchmarking, **Google Gemini AI insights** (with offline statistical fallback), and **shareable read-only Investor Performance Links** for raising funds.

## Quick Start (Local Hotel Outlet — 1 Click)
1. Double-click `Activate-Hotel-POS-6808.bat` (click **Yes** on Windows Admin prompt) to restart Apache 2.4 on port **`6808`** and allow port `6808` in Windows Firewall. *(Your Driving School Portal on port `6707` stays untouched!)*
2. Open on Billing Laptop:
   - Double-click `Open-Hotel-POS-App.vbs` (or open **http://127.0.0.1:6808**)
   - Optional Desktop Icon: run `Create-Hotel-POS-Shortcut.bat`
3. Open on Waiter Phones / Kitchen Tablet (Same Wi-Fi):
   - **http://192.168.1.8:6808** *(use your laptop's Wi-Fi IP)*
4. Enable Nightly Cloud Sync (`01:00 AM`):
   - Configure Cloud Sync URL, Secret Token & optional Google Gemini API Key in **Owner → Settings**, then run `Setup-Nightly-Cloud-Sync.bat` (or test anytime with `php artisan pos:sync-cloud --days=7 --ai --manual`).

## Default Staff PINs (Change Anytime in Owner → Staff)
| Role | Default PIN | Screen |
|---|---|---|
| **Owner (Admin)** | `1111` | Sales Reports, **AI Charts & Investor Portal (`/owner/analytics`)**, Menu, Tables, Staff, Anti-Theft Audit Log, Cloud/Gemini Settings & DB Backup |
| **Cashier Desk** | `2222` | Billing Counter, Parcel Orders, Discount, Table Transfer, Cash/UPI/Card/Split Settlement & 80mm/58mm Thermal Bill Print |
| **Main Kitchen** | `3333` | Live KDS Screen (3s LAN auto-refresh + alert chime), Cooking/Ready status, 86 Stock Out |
| **Waiter Raju / Amit** | `4444` / `5555` | Mobile Table Floor Map, Fast Menu + Short Code Order Punching, Send KOT & Request Bill |

## Project Documentation & Pitch Deck
- [Product Requirements Document (PRD)](docs/prd.md)
- [Technical Requirements Document (TRD)](docs/trd.md)
- [Implementation Plan](docs/implementation-plan.md)
- [Testing Strategy](docs/testing-strategy.md)
- [Deployment Plan](docs/deployment-plan.md)
- [Pitch Deck (PDF)](pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf) · [Interactive Pitch Deck (HTML)](pitch/pitch-deck.html)
