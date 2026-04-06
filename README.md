# ERP Docker Setup

## Included services
- `app`: PHP-FPM Laravel runtime
- `nginx`: web front serving `public/`
- `queue`: Laravel queue worker
- `scheduler`: Laravel scheduler worker
- `db`: PostgreSQL

## First-time setup
1. Copy the environment file if you do not already have one:
   - `Copy-Item .env.example .env`
2. Set a real `APP_KEY` in `.env`.
3. Start the stack:
   - `docker compose up --build -d`
4. Reset the database and seed the baseline data:
   - `docker compose run --rm app php artisan migrate:fresh --seed --force`

## Common commands
- Start:
  - `docker compose up --build -d`
- Stop:
  - `docker compose down`
- Stop and remove volumes:
  - `docker compose down -v`
- Follow logs:
  - `docker compose logs -f nginx app queue scheduler`
- Run artisan commands:
  - `docker compose run --rm app php artisan <command>`

## Notes
- The stack defaults to PostgreSQL and database-backed queue, cache, and session drivers.
- Frontend assets are built into the app image during `docker build`.
- Redis is not included because this repo does not require it by default.
- Local compose defaults to HTTP on `http://localhost:8000`, so `SESSION_SECURE_COOKIE` should stay `false` unless you add TLS in front of the stack.
- Production deployments should provide real secrets, real mail/integration credentials, and a production-safe `APP_URL`.
