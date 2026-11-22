# Architecture Snapshot — Royal Hotel & Restaurant Hybrid POS

> Full canonical specification: [docs/trd.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/trd.md) · Database schema: [docs/database.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/database.md) · API contracts: [docs/api.md](file:///d:/planned%20projects/hotel-pos-laravel/docs/api.md)

## Hybrid 75% Offline / 25% Cloud AI Topology
- **75% Local Hotel LAN (`POS_MODE=outlet`, Port `6808`)**: Apache 2.4 + Laravel 13 + MySQL `hotel_pos` running Waiter mobile ordering, 3s Kitchen KDS, Counter Billing, 80mm/58mm thermal printing, and offline SVG charts with zero internet dependency.
- **25% Central Cloud Hub (`POS_MODE=cloud_hub`)**: Same Laravel 13 codebase ingesting nightly (`01:00 AM`) HMAC-SHA256 signed daily snapshots (`POST /api/cloud/sync`), running Google Gemini (`gemini-4-hydrogen`) portfolio analysis (with local statistical fallback), and serving tokenized `/investor/{token}` executive portals.

## Domain Services (`app/Services/`)
- `PosService`: ACID transactional billing, KOT lifecycle, CGST/SGST + round-off math, split settlement, `formatINR()`, and `audit_logs`.
- `AnalyticsService`: Checksummed daily rollups (`daily_analytics_snapshots`), PoP growth %, Annualized Run Rate (ARR), and multi-outlet benchmarking.
- `GeminiAnalyticsService`: Google Gemini Interactions API v1 client with structured JSON Schema output and automatic deterministic `statistical-engine-v1` fallback.
- `CloudSyncService`: Outbound signed snapshot batch uploader with offline retry queue and inbound Cloud Hub ingestion.
