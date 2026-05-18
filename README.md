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

## Implementation Notes

- Backend write operations must use Form Requests, policies, and audit logs.
- Frontend route access can improve UX, but backend authorization is authoritative.
- Compliance, leave, and payroll rules must be configurable and versioned instead of hardcoded into controllers.
- API responses must use the standard success/error envelopes documented in `docs/api-contracts.md`.
