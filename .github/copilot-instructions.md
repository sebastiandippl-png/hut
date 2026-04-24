# Project Guidelines

## Code Style
- Use strict PHP typing (`declare(strict_types=1);`) in PHP source files.
- Follow existing naming and layout patterns under `src/`, `src/controllers/`, and `templates/`.
- Keep templates escaped by default with `htmlspecialchars(...)` for dynamic output.
- Preserve existing conventions for security checks in controllers (`Auth::requireLogin()`, `Auth::requireAdmin()`, `Auth::requireCsrf()`).

## Architecture
- App entrypoint and route registration live in `public/index.php`.
- Routing is handled by `src/Router.php` with controller methods as route targets.
- Core domain logic lives in `src/` (for example `Game.php`, `Vote.php`, `UserGame.php`, `Auth.php`).
- HTTP request handling and auth/admin workflows live in `src/controllers/`.
- Server-rendered views live in `templates/` with shared layout in `templates/partials/`.
- Database schema evolves through SQL files in `migrations/`, and migrations run automatically on app boot.

## Build and Test
- Install dependencies: `composer install`
- Set up env: `cp .env.example .env`
- Run local server: `php -S localhost:8080 -t public/`
- Lint one file: `php -l path/to/file.php`
- Lint key app files quickly:
	- `php -l public/index.php`
	- `php -l src/Auth.php`
	- `php -l src/controllers/AuthController.php`
- There is currently no dedicated automated test suite or linter command in `composer.json`.

## Conventions
- Keep state-changing routes as POST and enforce CSRF for both forms and AJAX.
- Avoid exposing raw internal exception messages to users; log server-side and show safe flash messages.
- Do not edit `vendor/` directly.
- Treat startup migration side effects as intentional: app boot may change DB schema if new migrations exist.
- Write all SQL to be compatible with both **SQLite** (local dev) and **MariaDB** (production). Avoid dialect-specific syntax:
  - Use `CURRENT_TIMESTAMP` for default datetime values, never `datetime('now')` (SQLite-only) or `NOW()` (MySQL-only).
  - Use `INSERT INTO ... ON CONFLICT DO NOTHING` only if handled by `normalizeSqlForMysql()` in `src/Database.php`; prefer avoiding upserts in migrations.
  - Do not use `INSERT OR IGNORE` (SQLite-only); use `INSERT IGNORE` only within MySQL-specific branches.
  - `ALTER TABLE ... DROP COLUMN IF EXISTS` is safe on both SQLite 3.35+ and MariaDB 10.0.2+.
  - `INTEGER PRIMARY KEY AUTOINCREMENT` in migrations is automatically rewritten for MariaDB by `Database::normalizeSqlForMysql()`.
- For deep BGG API behavior, retries, and polling caveats, reference `Learnings.md` instead of re-documenting details here.

## Weather Integration
- Weather forecast logic lives in `src/WeatherForecast.php`, the HTTP endpoint in `src/controllers/WeatherController.php`, and the UI in `templates/info/weather.php`.
- The weather page uses Open-Meteo `https://api.open-meteo.com/v1/forecast` for fixed St. Veit im Pongau coordinates `47.33056, 13.15556` with timezone `Europe/Vienna`, `forecast_days=14`, metric units, a `current` block, and a `daily` block.
- Cache weather API responses in `storage/weather_cache/st_veit_pongau.json` with a short TTL; keep graceful fallback behavior when the API is unavailable.
- Keep weather fetches HTTPS-only, use a stream context timeout, and retain retry/backoff only for transient failures like `429` and `503`.
- WMO weather-code to label/icon mapping is centralized in `WeatherForecast::wmoInfo()`; update mappings there instead of duplicating them in templates or controllers.
- Meteocons icons currently use the pinned CDN base `https://cdn.meteocons.com/3.0.0-next.10/svg/fill/`. Do **not** switch to the `latest` alias without verifying it resolves in local dev and production; it previously returned `404`.
- Meteocons naming is not always obvious: for example thunderstorm icons use `thunderstorms` and `thunderstorms-rain`, not singular `thunderstorm` slugs.
- If icons stop rendering after a mapping or CDN change, verify the generated icon URL directly and clear `storage/weather_cache/st_veit_pongau.json` so cached data is regenerated.
- Keep the attribution on the weather page for both Open-Meteo and Meteocons unless the integration changes.

## Reference Docs
- Setup, features, env vars, and routes: `README.md`
- BGG API caveats and operational learnings: `Learnings.md`
