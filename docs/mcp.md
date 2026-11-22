# MCP Connection & Tooling Guide (`MCP.md`) — Royal Hotel & Restaurant Hybrid POS

## 1. Overview of MCP Architecture

This repository uses the **Model Context Protocol (MCP)** to give AI coding and operations agents structured, zero-guesswork access to the Laravel 13 application runtime, database schema, route definitions, logs, and upstream Google Gemini API documentation.

Two MCP layers are used during development and maintenance:
1. **Project-Local Laravel Boost MCP Server (`laravel-boost`)** — Defined in [.mcp.json](file:///d:/planned%20projects/hotel-pos-laravel/.mcp.json) and [boost.json](file:///d:/planned%20projects/hotel-pos-laravel/boost.json), powered by `laravel/boost` (`^2.10`) via `php artisan boost:mcp`.
2. **Workspace Google Gemini API Docs MCP Server (`gemini-api_gemini-api-docs`)** — Provides authoritative upstream reference documentation for the Google Gemini Interactions API (`v1`) integrated in `App\Services\GeminiAnalyticsService`.

---

## 2. Project MCP Configuration Files

### 2.1 `.mcp.json` (Repository Root)
```json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": [
                "artisan",
                "boost:mcp"
            ]
        }
    }
}
```

### 2.2 `boost.json` (Repository Root)
```json
{
    "agents": [
        "claude_code"
    ],
    "guidelines": true,
    "mcp": true,
    "nightwatch_mcp": false,
    "sail": false,
    "skills": []
}
```

---

## 3. Available MCP Servers & Tool Definitions

### 3.1 `laravel-boost` MCP Server (`php artisan boost:mcp`)

| Tool Name | Purpose | When to Use | Safety / Permissions |
|---|---|---|---|
| `application-info` | Returns PHP version (`8.5.12`), Laravel version (`13.x`), database engine, installed packages, and Eloquent models | At the start of a task to verify environment state | Read-only |
| `database-schema` | Inspects live tables (`orders`, `kots`, `daily_analytics_snapshots`, etc.), columns, types, indexes, and foreign keys | Before writing migrations, queries, or Eloquent models | Read-only |
| `database-query` | Executes read-only SQL `SELECT` queries against the configured database | Inspecting `hotel_settings`, `outlets`, `daily_analytics_snapshots`, or `audit_logs` without raw CLI scripts | Read-only (`SELECT` only) |
| `list-routes` | Lists all registered web and API routes, middleware (`pos.auth`), controller actions, and route names | Verifying route contracts before modifying controllers or Blade links | Read-only |
| `get-absolute-url` | Resolves the canonical scheme, host, and port (`http://127.0.0.1:6808`) for any path or named route | Before sharing clickable URLs with the user | Read-only |
| `search-docs` | Searches version-pinned Laravel 13, PHPUnit 12, and Pint documentation | Before using framework APIs or version-specific features | Read-only |
| `browser-logs` | Reads recent frontend browser console logs and uncaught JS exceptions | Debugging `public/js/pos-charts.js` or KDS/Waiter polling | Read-only |
| `last-error` / `read-log-entries` | Reads recent exceptions from `storage/logs/laravel.log` | Diagnosing runtime 500 errors, Cloud Sync failures, or Gemini API warnings | Read-only |
| `tinker` | Executes PHP expressions inside the booted Laravel application container | Inspecting service outputs (`AnalyticsService`, `PosService::formatINR`) during debugging | Read/Write in app context — avoid mutating production data without user consent |

### 3.2 `gemini-api_gemini-api-docs` MCP Server

| Tool Name | Purpose | Usage in This Project |
|---|---|---|
| `gemini_search_docs` | Searches upstream Google Gemini API and SDK documentation (`api: interactions`) | Used to verify Interactions API v1 request shape (`model`, `input`, `response_format`) in `GeminiAnalyticsService.php` |
| `gemini_get_doc` | Retrieves full documentation chunks by `chunk_id` | Inspecting structured JSON output schemas and header requirements (`x-goog-api-key`) |

---

## 4. Permission Boundaries & Guardrails

1. **Read-First Preference**: Always prefer `database-schema`, `database-query`, and `list-routes` over ad-hoc shell scripts or destructive database commands.
2. **Zero Secret Exposure**: When querying `hotel_settings` via `database-query` or `tinker`, never print live production values of `cloud_sync_token` or `gemini_api_key` into public logs or documentation.
3. **Destructive Command Prohibition**: Never run `migrate:fresh`, `db:wipe`, or `DROP TABLE` against the production `hotel_pos` MySQL database without explicit user confirmation. Automated tests automatically isolate destructive resets inside in-memory SQLite (`:memory:` via `phpunit.xml`).

---

## 5. Offline & CLI Fallback Behavior

If an AI client or terminal environment does not have the `laravel-boost` MCP process attached, use these exact Artisan CLI fallbacks in PowerShell:

| MCP Tool | Equivalent Artisan / CLI Fallback Command |
|---|---|
| `application-info` | `php artisan about` |
| `database-schema` | `php artisan db:show` / `php artisan db:table daily_analytics_snapshots` |
| `database-query` | `php artisan tinker --execute "App\Models\Outlet::all()->toArray();"` |
| `list-routes` | `php artisan route:list --except-vendor` |
| `read-log-entries` | `Get-Content storage\logs\laravel.log -Tail 50` |
| `get-absolute-url` | `php artisan config:show app.url` |
