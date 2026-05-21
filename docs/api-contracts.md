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

## Attendance

Implemented endpoints:

- `GET /api/attendance-records`
- `POST /api/attendance-records`
- `GET /api/attendance-records/{attendance_record}`
- `PUT /api/attendance-records/{attendance_record}`
- `DELETE /api/attendance-records/{attendance_record}`

Supported list filters:

- `employee_id`
- `date_from`
- `date_to`

Create/update request:

```json
{
  "employee_id": 1,
  "date": "2026-05-20",
  "check_in": "09:00",
  "check_out": "18:00",
  "break_minutes": 60,
  "status": "present",
  "source": "manual",
  "notes": "Created by HR."
}
```

Rules:

- All attendance endpoints are scoped to the authenticated user's company.
- `employee_id` must belong to the current company.
- One attendance record is allowed per employee per date.
- Create, update, and delete operations create audit logs.
- Manual attendance entry is ready for future web, mobile, biometric, and import sources.

## Leave Management

Implemented endpoints:

- `GET /api/leave-types`
- `GET /api/leave-balances`
- `GET /api/leave-requests`
- `POST /api/leave-requests`
- `GET /api/leave-requests/{leave_request}`
- `POST /api/leave-requests/{leave_request}/approve`
- `POST /api/leave-requests/{leave_request}/reject`

Supported leave request filters:

- `employee_id`
- `status`

Create request:

```json
{
  "employee_id": 1,
  "leave_type_id": 1,
  "start_date": "2026-05-20",
  "end_date": "2026-05-22",
  "reason": "Family travel."
}
```

Reject request:

```json
{
  "rejection_reason": "Insufficient handover coverage."
}
```

Rules:

- All leave endpoints are scoped to the authenticated user's company.
- `employee_id` must belong to the current company.
- Leave types can be global statutory types or company-specific active types.
- New leave requests start as `pending` and reserve `pending_days` on the employee balance.
- Approval moves pending days to used days.
- Rejection releases pending days and stores a rejection reason.
- Create, approve, and reject operations create audit logs.

## Payroll Foundation

Implemented endpoints:

- `GET /api/salary-components`
- `POST /api/salary-components`
- `PUT /api/salary-components/{salary_component}`
- `GET /api/employee-salary-components`
- `POST /api/employee-salary-components`
- `PUT /api/employee-salary-components/{employee_salary_component}`
- `GET /api/payroll-periods`
- `POST /api/payroll-periods`
- `GET /api/payroll-periods/{payroll_period}`
- `POST /api/payroll-periods/{payroll_period}/run`
- `POST /api/payroll-periods/{payroll_period}/approve`

Salary component request:

```json
{
  "code": "HRA",
  "name": "Housing Allowance",
  "type": "earning",
  "is_taxable": false,
  "is_recurring": true,
  "status": "active"
}
```

Employee salary component request:

```json
{
  "employee_id": 1,
  "salary_component_id": 1,
  "amount": 2000,
  "effective_from": "2026-05-01",
  "effective_to": null,
  "status": "active"
}
```

Payroll period request:

```json
{
  "period_start": "2026-05-01",
  "period_end": "2026-05-31",
  "pay_date": "2026-06-01"
}
```

Rules:

- Payroll endpoints are scoped to the authenticated user's company.
- Salary component codes are unique per company.
- Salary assignments require both the employee and component to belong to the current company.
- Running payroll regenerates draft payslips for active employees only.
- Payslips include employee basic salary plus active recurring salary assignments effective during the period.
- Deductions reduce net pay; earnings increase gross pay.
- Approved payroll periods cannot be rerun.
- Salary setup, payroll runs, and payroll approvals create audit logs.

## Compliance Calculations

Implemented endpoints:

- `GET /api/compliance/legal-rules`
- `POST /api/compliance/gratuity`

Gratuity calculation request:

```json
{
  "employee_id": 1,
  "termination_date": "2026-12-31",
  "basic_salary": 12000,
  "unpaid_leave_days": 0
}
```

Rules:

- The employee must belong to the authenticated user's current company.
- Employee `hire_date` and a positive basic salary are required.
- `basic_salary` is optional; if omitted, the employee record value is used.
- Service days exclude submitted unpaid leave days.
- UAE private-sector default gratuity uses 21 days per year for the first 5 years, then 30 days per year after that.
- Gratuity is capped at 24 months of basic salary.
- The response includes the legal rule snapshot used for the estimate.
- Results are compliance guidance and should be reviewed before final settlement.

Every write endpoint must validate input, authorize server-side, and write audit logs for sensitive changes.
