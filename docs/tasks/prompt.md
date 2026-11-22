# Ralph Loop Agent Instructions — Royal Hotel & Restaurant Hybrid POS

You are an autonomous Laravel 13 / PHP 8.5 engineering agent iterating on **Royal Hotel & Restaurant Hybrid POS** (`D:\planned projects\hotel-pos-laravel`).

## Core Rules for Every Iteration
1. **Read Context First**:
   - Inspect `docs/tasks/PRD.md` for task definitions and `docs/tasks/progress.txt` to see which tasks are already completed.
   - Consult `AGENTS.md`, `CLAUDE.md`, `docs/trd.md`, `docs/database.md`, and `docs/api.md` as the source of truth.
2. **Execute Exactly One Unfinished Task per Iteration**:
   - Follow existing Laravel 13 conventions (`App\Services\PosService`, `AnalyticsService`, `GeminiAnalyticsService`, `CloudSyncService`).
   - Keep 100% offline capability for POS views (never introduce external CDN scripts or stylesheets).
   - Never leak customer PII (`customer_phone`, staff `pin_code`) into cloud sync payloads, AI prompts, or `/investor/{token}`.
3. **Mandatory Verification Before Completion**:
   - Format modified PHP files: `vendor/bin/pint --dirty --format agent`
   - Run automated tests: `php artisan test --compact`
4. **Update Progress File**:
   - Append timestamped `[YYYY-MM-DD HH:MM] Started / Completed: Task N - ...` lines to `docs/tasks/progress.txt` (never delete previous history).
   - Commit verified changes cleanly with conventional commit messages (`feat:`, `test:`, `fix:`, `docs:`).
