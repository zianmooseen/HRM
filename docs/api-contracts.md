# API Contracts

All API responses use a stable envelope.

## Success

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

## Validation Error

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

## Authorization Error

```json
{
  "success": false,
  "message": "You are not authorized to perform this action."
}
```

## Authentication

### `GET /sanctum/csrf-cookie`

Initializes the Sanctum session cookie for browser clients.

### `POST /api/login`

Request:

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Response:

```json
{
  "success": true,
  "message": "Authenticated successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "roles": ["company_admin"],
      "permissions": ["employees.view", "employees.create"]
    }
  }
}
```

### `POST /api/logout`

Logs out the current session.

### `GET /api/me`

Returns the authenticated user, roles, and permissions.

## Company Setup

### `GET /api/companies`

Returns companies visible to the current user. Super admins can see all companies; company admins see assigned companies.

### `GET /api/company`

Returns the current company for the authenticated user's active company scope.

### `PUT /api/company`

Updates the current company. Requires `companies.update` and creates an audit log.

### `GET /api/branches`

Returns branches for the current company.

### `POST /api/branches`

Creates a branch for the current company. Requires `companies.update`.

### `PUT /api/branches/{branch}`

Updates a branch only when it belongs to the current company.

### `DELETE /api/branches/{branch}`

Soft deletes a branch and creates an audit log.

### `GET /api/departments`

Returns departments for the current company.

### `POST /api/departments`

Creates a department. Optional `branch_id` must belong to the current company.

### `GET /api/job-titles`

Returns job titles for the current company.

### `POST /api/job-titles`

Creates a job title for the current company.

## Employee Management

Implemented endpoints:

- `GET /api/employees`
- `POST /api/employees`
- `GET /api/employees/{employee}`
- `PUT /api/employees/{employee}`
- `DELETE /api/employees/{employee}`

Rules:

- All employee endpoints are scoped to the authenticated user's company.
- `branch_id`, `department_id`, and `job_title_id` must belong to the current company.
- Salary fields are returned only when the user has `employees.view_salary`.
- Create, update, and delete operations create audit logs.

Planned onboarding endpoints:

- `POST /api/employees/{employee}/onboarding/start`
- `POST /api/employees/{employee}/onboarding/complete`

Every write endpoint must validate input, authorize server-side, and write audit logs for sensitive changes.
