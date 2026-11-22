# PRD: Royal Hotel & Restaurant Hybrid POS — Phase 8 Hardening & Expansion Tasks

## Overview
Royal Hotel & Restaurant Hybrid POS is a Laravel 13 + MySQL platform operating a **75% Offline Local LAN POS (`POS_MODE=outlet`, Port `6808`)** and a **25% Nightly Cloud Sync, Google Gemini AI (`gemini-4-hydrogen`), and Tokenized Investor Portal (`POS_MODE=cloud_hub`)**.

Phases 1–7 and the 15-file documentation standard are complete and verified (`7` feature test suites, `139` assertions). Complete the following self-contained Phase 8 tasks in sequential iterations, verifying each task with `php artisan test --compact` and `vendor/bin/pint --dirty --format agent`.

---

## Task 1: Unit Test Suite for `PosService::formatINR()` & Order Rounding Edge Cases
Create `tests/Unit/PosServiceFormatInrTest.php` covering Indian currency formatting (`PosService::formatINR`) across zero (`₹0`), hundreds (`₹450`), thousands (`₹12,450`), lakhs (`₹1,24,500`), crores (`₹1,09,82,589.29`), decimals, and negative values (`-₹250`).
- Run `php artisan test --compact tests/Unit/PosServiceFormatInrTest.php`
- Run `vendor/bin/pint --dirty --format agent`

## Task 2: Negative Feature Tests for Expired Investor Links & Tampered Cloud Sync HMAC
Extend `tests/Feature/HybridAnalyticsAndSyncTest.php` to verify:
- An `InvestorShareLink` with `expires_at = now()->subDay()` returns HTTP `404` on `GET /investor/{token}`.
- A `POST /api/cloud/sync` request with a valid Bearer token but a tampered `X-POS-Signature` header returns HTTP `401`.
- Attempting to delete an occupied dining table (`DELETE /owner/tables/{table}`) is blocked and preserves the table row.

## Task 3: Login PIN Rate Limiting (`throttle:10,1`)
Apply `throttle:10,1` middleware to `POST /login` (`pos.login.submit`) in `routes/web.php` to protect against automated PIN brute-forcing on LAN or Cloud Hub deployments, and verify all existing login tests pass.

## Task 4: Offline Dynamic UPI Payment Details & QR Prompt on Thermal Bill
Enhance `resources/views/print/bill.blade.php` so that when `upi_id` is configured in `hotel_settings`, the printed 80mm/58mm customer bill displays a clean `Pay via UPI: {upi_id}` payment block with the exact payable `Grand Total` in INR.

## Task 5: Synchronize 15-File Documentation & GSD State
After completing Tasks 1–4, update `.gsd/STATE.md`, `.gsd/ROADMAP.md`, `.gsd/TODO.md`, and `docs/testing-strategy.md` with the updated test and assertion counts, run `php artisan test --compact`, and verify `git status`.
