# Project Guidelines (Caveman Compress)

## Core Law
- PHP file use strict mode: `declare(strict_types=1);`.
- Follow existing shape in `src/`, `src/controllers/`, `templates/`.
- Template escape output with `htmlspecialchars(...)` unless truly safe.
- Keep auth checks pattern: `Auth::requireLogin()`, `Auth::requireAdmin()`, `Auth::requireCsrf()`.

## Where Things Live
- Entry + routes wire-up: `public/index.php`.
- Router brain: `src/Router.php`.
- Domain logic: `src/`.
- Controllers (HTTP/auth/admin): `src/controllers/`.
- Views: `templates/`, shared bits: `templates/partials/`.
- DB changes: `migrations/` (auto-run on boot).

## Run + Check
- Install: `composer install`
- Env: `cp .env.example .env`
- Local server: `php -S localhost:8080 -t public/`
- Lint one file: `php -l path/to/file.php`
- Quick lint set:
  - `php -l public/index.php`
  - `php -l src/Auth.php`
  - `php -l src/controllers/AuthController.php`
- No full test/lint script in `composer.json` yet.

## Safety + Behavior
- Mutating actions stay POST.
- CSRF required for forms and AJAX.
- Never show raw internal errors to user; log detailed error, show safe flash.
- Never edit `vendor/`.
- Migration side effects at boot are expected.

## SQL Cross-DB Rules (SQLite + MariaDB)
- Use `CURRENT_TIMESTAMP` defaults.
- Do not use `datetime('now')` or `NOW()`.
- Avoid upserts in migrations; if needed, only patterns handled by `Database::normalizeSqlForMysql()`.
- Do not use `INSERT OR IGNORE`.
- `INSERT IGNORE` only inside MySQL-specific branches.
- `ALTER TABLE ... DROP COLUMN IF EXISTS` is acceptable.
- `INTEGER PRIMARY KEY AUTOINCREMENT` is rewritten for MariaDB by normalize step.

## Weather Rules
- Logic: `src/WeatherForecast.php`
- Endpoint: `src/controllers/WeatherController.php`
- UI: `templates/info/weather.php`
- API: Open-Meteo forecast endpoint.
- Fixed location: St. Veit im Pongau (`47.34011279241758, 13.136896307009817`).
- Params: timezone `Europe/Vienna`, `forecast_days=14`, metric, include current + daily.
- Cache file: `storage/weather_cache/st_veit_pongau.json` with short TTL + graceful fallback.
- HTTPS only, request timeout set, retry/backoff only for transient (`429`, `503`).
- WMO mapping lives in `WeatherForecast::wmoInfo()`.
- Meteocons base must stay pinned:
  - `https://cdn.meteocons.com/3.0.0-next.10/svg/fill/`
- Do not switch to `latest` without verifying local + prod (had `404`).
- Thunderstorm icon slugs are `thunderstorms` / `thunderstorms-rain`.
- If icons break: verify icon URL + clear `storage/weather_cache/st_veit_pongau.json`.
- Keep Open-Meteo + Meteocons attribution unless integration changes.

## Deep References
- General app setup/routes/features: `README.md`
- BGG API retries/polling caveats: `Learnings.md`
