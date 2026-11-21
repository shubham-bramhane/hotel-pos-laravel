# Royal Hotel / Restaurant LAN POS (Offline-First Laravel 13 + MySQL)

A 100% self-hosted, offline-first **Hotel & Restaurant POS** built on **Laravel 13 + Local MySQL (`hotel_pos`) + XAMPP Apache2.4 (Port `6808`)** — runs completely over your local Wi-Fi router with **zero internet dependency and zero yearly SaaS fees**.

## Quick Start (1 Click)
1. Double-click `Activate-Hotel-POS-6808.bat` (click **Yes** on Windows Admin prompt) to restart Apache2.4 on port **`6808`** and allow port `6808` in Windows Firewall. *(Your Driving School Portal on port `6707` stays untouched!)*
2. Open on Billing Laptop:
   - Double-click `Open-Hotel-POS-App.vbs` (or open **http://127.0.0.1:6808**)
   - Optional Desktop Icon: run `Create-Hotel-POS-Shortcut.bat`
3. Open on Waiter Phones / Kitchen Tablet (Same Wi-Fi):
   - **http://192.168.1.8:6808** *(use your laptop's Wi-Fi IP)*

## Default Staff PINs (Change Anytime in Owner → Staff)
| Role | Default PIN | Screen |
|---|---|---|
| **Owner (Admin)** | `1111` | Sales Reports, Menu, Tables, Staff, Anti-Theft Audit Log, Settings & DB Backup |
| **Cashier Desk** | `2222` | Billing Counter, Parcel Orders, Discount, Table Transfer, Cash/UPI/Split Settlement & 80mm/58mm Thermal Bill Print |
| **Main Kitchen** | `3333` | Live KDS Screen (3s LAN auto-refresh + alert chime), Cooking/Ready status, 86 Stock Out |
| **Waiter Raju / Amit** | `4444` / `5555` | Mobile Table Floor Map, Fast Menu + Short Code Order Punching, Send KOT & Request Bill |

## Project Documentation & Pitch Deck
- [Product Requirements Document (PRD)](docs/prd.md)
- [Technical Requirements Document (TRD)](docs/trd.md)
- [Implementation Plan](docs/implementation-plan.md)
- [Testing Strategy](docs/testing-strategy.md)
- [Deployment Plan](docs/deployment-plan.md)
- [Pitch Deck (PDF)](pitch/Royal_Hotel_LAN_POS_Pitch_Deck.pdf) · [Interactive Pitch Deck (HTML)](pitch/pitch-deck.html)
