# Hut

Hut is multi-user PHP app.
People plan which board games go to hut trip.

Note: toy project for learning. Setup assumptions are personal and may not fit every machine.

## What App Do

- Login/register with local auth or Google OAuth
- Browse imported BoardGameGeek games
- Add games to hut shortlist and heart games for ranking
- Keep personal collection links to games
- Food suggestion board with hearts and owner-only delete/update
- Weather page for trip location
- Residents overview/profile
- Admin import and admin tools

## Quick Start

1. Install deps:

```bash
composer install
```

2. Create env file:

```bash
cp .env.example .env
```

3. Edit env values (especially Google OAuth).

4. Start server:

```bash
php -S localhost:8080 -t public/
```

5. Open app:

- http://localhost:8080

Migrations run automatically on startup.

## Environment Variables

| Variable | Description |
|---|---|
| APP_ENV | Environment name (development/production) |
| DB_DSN | PDO DSN (SQLite or MariaDB) |
| DB_USERNAME | Database user (empty for SQLite) |
| DB_PASSWORD | Database password (empty for SQLite) |
| SESSION_NAME | PHP session cookie name |
| SESSION_SECRET | Session secret (use strong random value in production) |
| GOOGLE_CLIENT_ID | Google OAuth client ID |
| GOOGLE_CLIENT_SECRET | Google OAuth client secret |
| GOOGLE_REDIRECT_URI | OAuth callback URL |
| USER_COLLECTION / USER_COLLECTIONS | Comma-separated BGG usernames for admin collection fetch |
| ALWAYS_ADMIN_EMAILS | Optional comma-separated emails always treated as admin |
| BGG_TOKEN | Optional token for authenticated BGG API requests |

### Example DSN (SQLite dev)

```env
DB_DSN=sqlite:storage/hut.sqlite
DB_USERNAME=
DB_PASSWORD=
```

### Example DSN (MariaDB prod)

```env
DB_DSN=mysql:host=localhost;dbname=hut;charset=utf8mb4
DB_USERNAME=hut_user
DB_PASSWORD=replace_me
```

## Auth

Sign-in methods:

- Local email/password
- Google OAuth

For Google OAuth:

- Configure authorized redirect URI in Google Cloud Console
- Match GOOGLE_REDIRECT_URI in .env

Default callback path:

- /auth/google/callback

## Admin Import

Only admins can import BoardGameGeek data.

Primary CSV format example:

- [bgg_dump/boardgames_ranks.csv](bgg_dump/boardgames_ranks.csv)

Required CSV columns:

- id
- name
- yearpublished

Optional columns like rank, average, bayesaverage, usersrated, and category ranks are kept in raw payload and partially surfaced.

If user must become admin, set users.is_admin = 1 in database.

## Main Routes

Public/auth:

- GET /login
- POST /login
- GET /register
- POST /register
- POST /logout
- GET /auth/google
- GET /auth/google/callback

Core app:

- GET /
- GET /games
- GET /games/statistics
- GET /games/suggestions
- GET /games/{id}
- POST /games/{id}/select
- POST /games/{id}/heart
- POST /games/{id}/add-to-collection
- POST /games/{id}/remove-from-collection
- GET /collection
- GET /changelog
- GET /links

News/info:

- GET /news/food
- POST /news/food
- POST /news/food/{id}/update
- POST /news/food/{id}/heart
- POST /news/food/{id}/delete
- GET /news/weather

Residents:

- GET /residents
- GET /residents/{id}

Admin:

- GET /admin
- GET /admin/users
- GET /admin/import
- GET /admin/import/status
- GET /admin/notice
- GET /admin/links
- POST /admin/import
- POST /admin/import/start
- POST /admin/import/process
- POST /admin/site-notice
- POST /admin/collections/fetch
- POST /admin/users/{id}/delete
- POST /admin/users/{id}/approve
- POST /admin/users/{id}/disapprove
- POST /admin/games/{id}/remove-from-hut
- POST /admin/links
- POST /admin/links/{id}/category
- POST /admin/links/reorder
- POST /admin/links/{id}/delete
- POST /admin/links/{id}/refetch-preview
- POST /admin/links/refetch-all-previews
- POST /admin/link-categories
- POST /admin/link-categories/reorder
- POST /admin/link-categories/{id}/rename
- POST /admin/link-categories/{id}/delete

## Deployment Changelog

- ionos_deploy.sh generates storage/changelog.json each deploy
- Generated file is also in release artifact
- Entries come from recent git commits with GitHub links

## Database

Main tables:

- users
- games
- user_games
- votes
- bgg_thing
- user_collection
- migrations

Migrations live in migrations/ and run automatically on startup.

## Directory Layout

```text
public/          Front controller and static assets
src/             Application classes (auth, models, controllers)
templates/       Server-rendered PHP templates
migrations/      SQL migrations
storage/         SQLite database file in development
```

## Development Notes

- PSR-4 autoloading under Hut namespace
- AJAX used for suggestions, selection toggles, and hearts
- .env ignored by git and must not be committed
- No dedicated automated test suite or lint script in composer.json yet

## Security Notes

- Use HTTPS in production
- Use strong session secret and DB credentials
- Restrict PHP error display in production
- Validate Google OAuth settings before deploy
- State-changing routes must use POST + CSRF (forms and AJAX)
- Session cookies: HttpOnly, SameSite=Lax, Secure when HTTPS

## License

No license file yet.
