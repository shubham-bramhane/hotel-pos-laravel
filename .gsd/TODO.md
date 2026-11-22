# GSD Todo List — Royal Hotel & Restaurant Hybrid POS

## Pending Todos
- [ ] `high` Concurrency-safe row lock for `nextOrderNo()` and `nextKotNo()` in `app/Services/PosService.php`
- [ ] `high` Add `throttle:10,1` rate-limiting middleware to `POST /login` in `routes/web.php`
- [ ] `medium` Add unit test suite `tests/Unit/PosServiceFormatInrTest.php` for Indian currency formatting edge cases
- [ ] `medium` Add negative tests for expired `InvestorShareLink` (`expires_at < now()`) and tampered `X-POS-Signature` HMAC
- [ ] `low` Render inline offline SVG UPI QR code on `resources/views/print/bill.blade.php` when `upi_id` is configured
- [ ] `low` Add guided JSON database backup restore utility in Owner Settings

## Completed Todos
- [x] Build 75% Offline LAN POS core (Waiter, Kitchen KDS, Counter Billing, 80mm/58mm Thermal Print, Owner Admin)
- [x] Build 25% Cloud AI Analytics (`/owner/analytics`) with zero-CDN interactive SVG charts (`public/js/pos-charts.js`)
- [x] Integrate Google Gemini Interactions API v1 (`gemini-4-hydrogen`) with Offline Statistical Engine fallback
- [x] Build nightly `01:00 AM` signed Cloud Sync (`pos:sync-cloud`) and multi-outlet Cloud Hub (`/api/cloud/sync`)
- [x] Build tokenized read-only Investor Portal (`/investor/{token}`) with print-to-PDF layout
- [x] Complete Mandatory 15-File Project Documentation Standard (`README.md`, `AGENTS.md`, `docs/*.md`)
- [x] Configure GSD, Roo Code, Roo Code Nightly, Ralph Loop & CodeRabbit extensions in workspace
