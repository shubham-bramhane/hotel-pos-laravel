# Project State — Royal Hotel & Restaurant Hybrid POS

**Milestone:** Milestone 1 — Hybrid 75% Offline / 25% Cloud AI POS & Phase 8 Hardening
**Phase:** 8 - Security Hardening, Dynamic UPI QR & Concurrency Locks
**Status:** Ready to Execute
**Plan:** Plan 8.1

## Last Action
Completed Phases 1–7 (75% Offline POS Core + 25% Cloud AI Analytics, Nightly Sync, Investor Portal, 15-File Documentation Standard) and configured GSD, Roo Code, Roo Code Nightly, Ralph Loop, and CodeRabbit workspace integrations. All 7 PHPUnit feature test suites (139 assertions) passing.

## Next Steps
1. [ ] Execute Plan 8.1: Concurrency-safe `order_no` and `kot_no` sequence locking in `PosService`
2. [ ] Execute Plan 8.2: Add `throttle:10,1` rate limiting on `POST /login`
3. [ ] Execute Plan 8.3: Render offline SVG UPI QR code on `resources/views/print/bill.blade.php`
4. [ ] Execute Plan 8.4: Add unit tests for `PosService::formatINR()` and negative tests for expired `/investor/{token}` links

## Blockers
- [ ] None
