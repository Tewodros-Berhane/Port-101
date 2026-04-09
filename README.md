# Port-101

Port-101 is a modular ERP for finance, inventory, purchasing, sales, projects, HR, approvals, reporting, and operational governance.

The application is built for teams that want commercial records, stock movement, financial control, project delivery, people operations, and platform governance to stay inside one permission-aware system.

## What Port-101 covers

### Core business areas
- Sales: leads, quotes, orders, and downstream billing handoff
- Purchasing: RFQs, purchase orders, receiving, and vendor workflow visibility
- Inventory: warehouses, locations, lots, stock moves, cycle counts, and reordering
- Accounting: invoices, payments, journals, statements, and reporting workspaces
- Projects: projects, tasks, timesheets, milestones, billables, and recurring billing
- HR: employees, onboarding access, leave, attendance, reimbursements, and payroll

### Shared operating controls
- Approvals and status-driven workflow execution
- Notifications and audit visibility
- Reports and exports
- Webhook endpoints, delivery history, and queue-health operations
- Company and platform administration

## Technology stack
- Backend: Laravel 12, PHP 8.4, PostgreSQL
- Frontend: Inertia.js, React 19, TypeScript, Tailwind CSS 4
- Auth and security: Laravel Fortify, Sanctum, role-aware navigation, approval and audit flows
- Document/report generation: OpenSpout, TCPDF
- Runtime services: PHP-FPM, Nginx, queue worker, scheduler

## Repository layout
- `app/` application logic, domain services, models, policies, jobs, and controllers
- `resources/js/` Inertia pages, React UI, and frontend application code
- `resources/views/` Blade templates and mail views
- `routes/` web, platform, API, and module route definitions
- `database/` migrations and seeders
- `docker/` container runtime configuration
- `tests/` Pest feature and unit coverage

## Default runtime assumptions
- Database: PostgreSQL
- Queue: `database`
- Cache: `database`
- Session driver: `database`
- Mail: configured from `.env`
- App URL in Docker: `http://localhost:8000`


## Getting started with Docker

Docker is the primary setup path for this repository.

### Included services
- `app`: Laravel PHP-FPM runtime
- `nginx`: public web entrypoint serving Laravel from `public/`
- `queue`: background queue worker
- `scheduler`: Laravel scheduler worker
- `db`: PostgreSQL 16

### First-time setup
1. Copy the environment file.
```powershell
Copy-Item .env.example .env
```
2. Set the application URL for Docker.
```env
APP_URL=http://localhost:8000
```
3. Set a real application key in `.env`.
```powershell
php artisan key:generate
```
4. If you want to test real mail delivery, configure SMTP in `.env`.
5. Build and start the stack.
```powershell
docker compose up --build -d
```
6. Run migrations and seed baseline data.
```powershell
docker compose run --rm app php artisan migrate --seed --force
```

### Access the app
- App: `http://localhost:8000`
- Health check: `http://localhost:8000/up`

### Seeded superadmin account
The default seed creates a platform superadmin for clean local setup.
- Email: `superadmin@port101.test`
- Password: `password`

Change or remove that account before using the application outside local development.

### Common Docker commands
Start the stack:
```powershell
docker compose up --build -d
```

Stop the stack:
```powershell
docker compose down
```

Stop and remove volumes:
```powershell
docker compose down -v
```

Follow logs:
```powershell
docker compose logs -f nginx app queue scheduler db
```

Run Artisan commands:
```powershell
docker compose run --rm app php artisan <command>
```

Reset the database and reseed from scratch:
```powershell
docker compose run --rm app php artisan migrate:fresh --seed --force
```

Open a shell in the app container:
```powershell
docker compose exec app sh
```

### Docker notes
- Frontend assets are built into the app image during `docker build`.
- The same app image is reused for `app`, `queue`, and `scheduler`.
- PostgreSQL data and Laravel storage use named volumes.
- Mail settings come from `.env`. Docker does not force the mailer to `log`.
- Local Docker runs over HTTP by default, so `SESSION_SECURE_COOKIE=false` is expected unless you add TLS in front of the stack.

## Local setup without Docker

Use this path only if you want to run the application directly on the host machine.

### Requirements
- PHP 8.4 with the extensions used by this app
- Composer 2
- Node.js 22 and npm
- PostgreSQL 16 or compatible

### Setup
1. Install backend dependencies.
```powershell
composer install
```
2. Install frontend dependencies.
```powershell
npm ci
```
3. Copy the environment file.
```powershell
Copy-Item .env.example .env
```
4. Generate the app key.
```powershell
php artisan key:generate
```
5. Configure PostgreSQL credentials in `.env`.
6. Run migrations and seed baseline data.
```powershell
php artisan migrate --seed
```
7. Start the local development processes.
```powershell
composer run dev
```

## Frontend and build commands

Build frontend assets:
```powershell
npm run build
```

Run the Vite dev server:
```powershell
npm run dev
```

Run TypeScript checks:
```powershell
npm run types
```

Run ESLint:
```powershell
npm run lint
```

## Backend commands

Run tests:
```powershell
php artisan test
```

Run the queue worker manually:
```powershell
php artisan queue:work
```

Run the scheduler manually:
```powershell
php artisan schedule:work
```

Clear application caches:
```powershell
php artisan optimize:clear
```

## Mail and invite testing

For local Docker testing with SMTP, set mail values in `.env`, for example:
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Port-101"
```

Invite links, password reset links, and other generated URLs use `APP_URL`, so keep it aligned with the port you are actually using.

## Production-minded notes
- Provide a real `APP_KEY`, database credentials, mail credentials, and webhook/integration secrets through environment variables.
- Set the correct public `APP_URL`.
- Use TLS in front of the stack and enable secure-cookie behavior accordingly.
- Run database migrations explicitly as part of deployment.
- Ensure queue and scheduler containers are running in production.
- Configure attachment scanning and outbound integration settings deliberately for the target environment.

## Validation and maintenance

Useful checks before shipping changes:
```powershell
npm run build
php artisan test
docker compose config
```

For a running Docker stack, confirm health with:
```powershell
Invoke-WebRequest -Uri http://localhost:8000/up -UseBasicParsing
```
