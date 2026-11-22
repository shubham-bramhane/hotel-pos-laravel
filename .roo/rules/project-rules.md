# Roo Code & Roo Code Nightly Project Rules

See root [.roorules](file:///d:/planned%20projects/hotel-pos-laravel/.roorules), [AGENTS.md](file:///d:/planned%20projects/hotel-pos-laravel/AGENTS.md), and [CLAUDE.md](file:///d:/planned%20projects/hotel-pos-laravel/CLAUDE.md) for canonical Laravel 13, 75% Offline / 25% Cloud AI POS, PHPUnit, and Laravel Pint rules.

## Quick Verification Commands
- Run all tests: `php artisan test --compact`
- Format dirty PHP files: `vendor/bin/pint --dirty --format agent`
- Inspect routes: `php artisan route:list --except-vendor`
- Manual Cloud Sync & AI test: `php artisan pos:sync-cloud --days=7 --ai --manual`
