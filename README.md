# Hut

Hut is a multi-user PHP web application for planning which board games to bring to a hut trip.

**Note: This is a toy project to learn a few things. Do not expect that this will work for you out of the box. The code also makes string assumptions on my setup**


Users can sign in, browse imported BoardGameGeek games, add games to the shared shortlist, and heart games to rank what the group should play.

## Features

- Mandatory authentication for all app usage
- Local account registration and login
- Google OAuth login
- Admin-only BoardGameGeek import for the rankings CSV used by this project
- Searchable/filterable game catalog
- Personal game selection toggle ("Add to hut")
- Shared collection view (who selected what)
- Heart system with live tally updates
- Detailed game metadata view
- Responsive, mobile-friendly light UI

## Tech Stack

- PHP 8.1+
- PDO for database access
- SQLite for local development
- MariaDB for production (via DSN configuration)
- Composer for dependency management
- Dotenv for runtime configuration
- Google API Client for OAuth
- Vanilla HTML, CSS, and JavaScript

## Project Status

Core MVP is implemented and runnable.

## Quick Start (Local Development)

1. Install dependencies:

```bash
composer install
```

2. Create environment file:

```bash
cp .env.example .env
```

3. Edit .env values as needed (especially Google OAuth values).

4. Start the PHP development server:

```bash
php -S localhost:8080 -t public/
```

5. Open the app:

- http://localhost:8080

On startup, migrations are applied automatically.

## Environment Variables

| Variable | Description |
|---|---|
| APP_ENV | Environment name (development/production) |
| DB_DSN | PDO DSN (SQLite or MariaDB) |
| DB_USERNAME | Database user (empty for SQLite) |
| DB_PASSWORD | Database password (empty for SQLite) |
| SESSION_NAME | PHP session cookie name |
| SESSION_SECRET | Session secret (set a strong random value in production) |
| GOOGLE_CLIENT_ID | Google OAuth client ID |
| GOOGLE_CLIENT_SECRET | Google OAuth client secret |
| GOOGLE_REDIRECT_URI | OAuth callback URL |
| USER_COLLECTION / USER_COLLECTIONS | Comma-separated BGG usernames used by admin collection fetch |
| ALWAYS_ADMIN_EMAILS | Optional comma-separated emails that should always be treated as admin users |
| BGG_TOKEN | Optional token used for authenticated BGG API requests |

### Example DSNs

SQLite (dev):

```env
DB_DSN=sqlite:storage/hut.sqlite
DB_USERNAME=
DB_PASSWORD=
```

MariaDB (prod):

```env
DB_DSN=mysql:host=localhost;dbname=hut;charset=utf8mb4
DB_USERNAME=hut_user
DB_PASSWORD=replace_me
```

## Authentication

Two sign-in methods are supported:

- Local email/password
- Google OAuth

For Google OAuth, configure:

- Authorized redirect URI in Google Cloud Console
- Matching GOOGLE_REDIRECT_URI in .env

Default local callback path:

- /auth/google/callback

## Admin Import Flow

Only admin users can upload a BoardGameGeek export.

The primary supported format is the rankings CSV shaped like [bgg_dump/boardgames_ranks.csv](bgg_dump/boardgames_ranks.csv).

Required CSV columns:

- id
- name
- yearpublished

Optional columns such as rank, average, bayesaverage, usersrated, and category-specific rank columns are preserved in the raw import payload and partially surfaced in the description field.

Routes:

- GET /admin
- GET /admin/import
- POST /admin/import
- POST /admin/collections/fetch
- POST /admin/users/{id}/delete

If you need to promote a user to admin, update the users table and set is_admin = 1 for that user.

## Main Routes

- GET /login
- POST /login
- GET /register
- POST /register
- POST /logout
- GET /games
- GET /games/{id}
- POST /games/{id}/select
- POST /games/{id}/heart
- GET /games/suggestions
- GET /collection
- GET /rankings
- GET /changelog
- GET /admin
- GET /admin/import
- POST /admin/import
- POST /admin/collections/fetch
- POST /admin/users/{id}/delete

## Deployment Changelog

- `ionos_deploy.sh` generates `storage/changelog.json` on each run.
- The same generated file is also written into the release artifact, so deployed environments show the latest entries.
- Entries are taken from recent git commits and include direct links to each commit on GitHub.

## Database

The schema includes these tables:

- users
- games
- user_games
- votes
- bgg_thing
- user_collection
- migrations

Migrations live in the migrations directory and are executed automatically at startup.

## Directory Layout

```text
public/          Front controller and static assets
src/             Application classes (auth, models, controllers)
templates/       Server-rendered PHP templates
migrations/      SQL migrations
storage/         SQLite database file in development
```

## Development Notes

- Autoloading uses PSR-4 under the Hut namespace.
- AJAX is used for suggestions, selection toggles, and heart interactions.
- .env is ignored by git and should not be committed.
- There is currently no dedicated automated test suite or linter command in composer.json.

## Security Notes

- Use HTTPS in production.
- Set strong secrets and database credentials.
- Restrict PHP error display in production.
- Validate Google OAuth settings before deployment.
- State-changing routes use POST and CSRF protection (including AJAX requests).
- Session cookies are configured as HttpOnly and SameSite=Lax; `Secure` is enabled when running over HTTPS.

## License

No license file has been added yet.
