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
  "login": "sys.admin",
  "password": "sys.admin"
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
      "name": "System Admin",
      "username": "sys.admin",
      "email": "sys.admin@example.local",
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
- `POST /api/employees/{employee}/account`
- `GET /api/employees/{employee}/service-periods`
- `POST /api/employees/{employee}/service-periods/extend`
- `POST /api/employees/{employee}/service-periods/rehire`
- `GET /api/self/profile`

Rules:

- All employee endpoints are scoped to the authenticated user's company.
- `branch_id`, `department_id`, and `job_title_id` must belong to the current company.
- Salary fields are returned only when the user has `employees.view_salary`.
- Create, update, and delete operations create audit logs.
- Creating an employee account links `employees.user_id`, assigns the `employee` role with `scope = self`, and creates an audit log.
- Creating an employee with a hire date creates the first `employee_service_periods` record.
- Contract extension updates the active service period end date and `employees.contract_end_date`.
- Rehire is allowed only when no active service period exists; it creates a new active period and returns the employee to `active`.
- Self-service users can retrieve their own profile through `/api/self/profile`.

Create employee account request:

```json
{
  "username": "emp.001",
  "email": "employee@example.com",
  "password": "employee1"
}
```

Extend contract request:

```json
{
  "end_date": "2027-12-31",
  "change_reason": "Fixed-term contract renewed"
}
```

Rehire request:

```json
{
  "start_date": "2026-02-01",
  "end_date": "2027-01-31",
  "employment_type": "full_time",
  "contract_type": "fixed_term",
  "basic_salary": 12000,
  "change_reason": "Rehired for new contract"
}
```

## Employee Self-Service

Employee login uses the same authentication endpoints:

- `GET /sanctum/csrf-cookie`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

Employee self-service can use:

- `GET /api/self/profile`
- `GET /api/attendance-records`
- `GET /api/documents`
- `POST /api/documents`
- `GET /api/leave-types`
- `GET /api/leave-requests`
- `POST /api/leave-requests`
- `GET /api/leave-balances`

Self-service restrictions:

- Employee users receive `scope = self`.
- Employee users can only list, view, upload, or submit records tied to their own employee record.
- Employee users cannot approve leave, edit attendance, delete documents, run payroll, or view company employee lists.

Implemented onboarding endpoints:

- `GET /api/onboarding-templates`
- `POST /api/onboarding-templates`
- `GET /api/onboarding-cases`
- `POST /api/employees/{employee}/onboarding/start`
- `POST /api/onboarding-tasks/{task}`
- `POST /api/onboarding-cases/{case}/complete`

Template request:

```json
{
  "name": "Standard hire",
  "description": "Default onboarding checklist.",
  "employment_type": null,
  "is_default": true,
  "tasks": [
    {
      "title": "Upload passport",
      "task_type": "document_upload",
      "assigned_to_role": "hr_manager",
      "required": true,
      "sort_order": 0,
      "due_days_after_start": 2
    }
  ]
}
```

Start onboarding request:

```json
{
  "onboarding_template_id": 1
}
```

Task update request:

```json
{
  "status": "completed"
}
```

Rules:

- Onboarding endpoints are scoped to the authenticated user's company.
- Starting onboarding generates employee onboarding tasks from the selected template.
- Starting onboarding moves employee status to `onboarding`.
- Completing onboarding requires all required tasks to be `completed` or `skipped`.
- Completing onboarding moves employee status to `active`.
- Onboarding template, task, start, and completion events create audit logs.

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
- `POST /api/leave-balances/accrue-annual`
- `POST /api/leave-balances`
- `GET /api/leave-requests`
- `POST /api/leave-requests`
- `GET /api/leave-requests/{leave_request}`
- `GET /api/leave-requests/{leave_request}/sick-pay`
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
  "medical_certificate_document_id": null,
  "reason": "Family travel."
}
```

Configure employee leave balance request:

```json
{
  "employee_id": 1,
  "leave_type_id": 1,
  "leave_year": 2026,
  "opening_balance": 0,
  "accrued_days": 30,
  "carried_forward_days": 0,
  "adjusted_days": 0,
  "encashed_days": 0,
  "note": "Initial annual leave entitlement."
}
```

Run UAE annual leave accrual request:

```json
{
  "leave_year": 2026,
  "employee_id": null,
  "accrual_date": "2026-12-31"
}
```

Annual leave accrual uses current UAE defaults:

```txt
Under 6 completed months of service: 0 days
6 to under 12 completed months of service: 2 days per completed month
12 or more completed months of service: 30 days
```

Sick leave pay preview:

```json
{
  "success": true,
  "message": "Sick leave pay calculated.",
  "data": {
    "calculation": {
      "eligible": true,
      "previously_used_days": 10,
      "items": [
        {
          "pay_tier": "full_pay",
          "days": 5,
          "pay_percentage": 100,
          "daily_wage": 300,
          "gross_pay_amount": 1500,
          "deduction_amount": 0,
          "calculation_basis": "basic_salary_30_day_divisor"
        }
      ]
    },
    "stored_items": []
  }
}
```

Approving sick leave stores auditable `leave_pay_calculation_items`. If UAE sick leave rules require medical documentation and the request has no `medical_certificate_document_id`, approval returns a validation error.

Approve request:

```json
{
  "approval_note": "Coverage confirmed with branch manager."
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
- `medical_certificate_document_id`, when provided, must be an uploaded `medical_certificate` document for the same employee.
- Leave types can be global statutory types or company-specific active types.
- Company admins can configure balance entitlement buckets through `POST /api/leave-balances`.
- Balance configuration recalculates closing balance and creates an audit log.
- Company admins can run annual leave accrual for all active/onboarding employees, or one employee, through `POST /api/leave-balances/accrue-annual`.
- New leave requests start as `pending` and reserve `pending_days` on the employee balance.
- Approval moves pending days to used days and stores an optional approval note.
- If a leave balance has configured entitlement, approval is rejected when the request exceeds the available balance.
- Rejection releases pending days and stores a rejection reason.
- Leave request responses include `status_events` when loaded, giving the frontend a request timeline for submission, approval, and rejection notes.
- Create, approve, and reject operations create audit logs.

## Employee Documents

Implemented endpoints:

- `GET /api/documents`
- `POST /api/documents`
- `GET /api/documents/{document}/preview`
- `GET /api/documents/{document}/download`
- `DELETE /api/documents/{document}`

Supported filters:

- `employee_id`
- `document_type`

Upload request uses `multipart/form-data`:

```txt
employee_id=1
document_type=medical_certificate
title=Clinic note
issue_date=2026-05-20
expiry_date=2026-12-31
file=@clinic-note.pdf
```

Allowed document types:

```txt
passport
visa
labor_card
emirates_id
medical_certificate
contract
other
```

Rules:

- Documents are stored through Laravel's configured `FILESYSTEM_DISK`; local storage is the development default.
- Files are private by default and downloaded through the authenticated API.
- Image documents expose an authenticated inline preview URL for thumbnails.
- Document responses include `expiry_status` values: `not_tracked`, `valid`, `expiring_soon`, and `expired`.
- Upload and delete operations create audit logs.
- The employee must belong to the authenticated user's current company.
- Accepted file types are PDF, JPG, PNG, and WebP up to 10 MB.

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
- `GET /api/employee-terminations`
- `POST /api/employees/{employee}/termination`
- `POST /api/employee-terminations/{termination}/mark-paid`

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
- Termination records persist the gratuity snapshot, final settlement amount, payment status, and update the employee to `terminated`.
- Termination closes the active employee service period; later gratuity estimates for rehired employees use the latest service period start.
- Marking a final settlement paid requires payroll approval permission and stores payment reference/date.
- Termination creation and payment recording create audit logs.

Every write endpoint must validate input, authorize server-side, and write audit logs for sensitive changes.
