# UAE HRM Platform

Monorepo for a UAE-focused Human Resource Management platform. The repository keeps the Nuxt frontend, Laravel API, shared contracts, and documentation together while preserving clear application boundaries.

## Structure

```txt
frontend/   Nuxt 4, Vue 3, TypeScript client
backend/    Laravel API application files
shared/     Shared constants and TypeScript API contracts
docs/       Product, database, API, compliance, and delivery notes
scripts/    Local helper scripts
```

## Local Development

Requirements:

- PHP 8.5
- Composer 2
- Node.js and npm
- Docker Desktop or another Docker Compose-compatible runtime

1. Copy environment examples.

```bash
cp .env.example .env
cp frontend/.env.example frontend/.env
cp backend/.env.example backend/.env
```

2. Start infrastructure.

```bash
docker compose up -d
```

3. Install and run the frontend.

```bash
cd frontend
npm install
npm run dev
```

4. Install and run the backend.

```bash
cd backend
composer install
php artisan migrate
php artisan serve
```

## Scenario Data

Reset the local database and generate the complete dashboard test dataset:

```bash
cd backend
php artisan migrate:fresh --seed
```

The seed creates 160 employees and scenario records for organization setup, onboarding, attendance, corrections, leave, documents, payroll, WPS, compliance, terminations, audit logs, and billing.

Local seeded accounts use the username as the password:

| Role | Username |
| --- | --- |
| System admin | `sys.admin` |
| Company admin | `com.admin` |
| HR manager | `hr.manager` |
| Payroll manager | `payroll.manager` |
| Department manager | `department.manager` |
| Employee | `employee.demo` |

These credentials are for local development only and can be overridden with the `SEED_*` environment variables.

## Implementation Notes

- Backend write operations must use Form Requests, policies, and audit logs.
- Frontend route access can improve UX, but backend authorization is authoritative.
- Compliance, leave, and payroll rules must be configurable and versioned instead of hardcoded into controllers.
- API responses must use the standard success/error envelopes documented in `docs/api-contracts.md`.
