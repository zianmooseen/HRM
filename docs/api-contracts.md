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

## Dashboard

### `GET /api/dashboard`

Returns an operational dashboard summary. Requires `employees.view`.

Supported scope parameters:

- `company_id`: available only to `super_admin` users. Company admins cannot override their assigned company.
- `branch_id`: optional branch inside the selected company. Foreign-company branch IDs are rejected.

Role meaning:

- `super_admin` is the SaaS platform operator/system administrator responsible for maintaining the HRM service across customer organizations.
- `company_admin` is a customer administrator restricted to their assigned organization.

Includes:

- Employee counts by lifecycle status.
- Today's attendance counts.
- Pending leave and approved leave this month.
- Latest payroll period status.
- Latest saved Emiratisation snapshot.
- Contract and document expiry reminders for the next 60 days.
- Recent company audit events.
- Available organizations and branches for the dashboard scope selector.

Employee, attendance, leave, contract, document, and WPS-readiness metrics respect the selected branch. Payroll periods, WPS batch status, audit activity, and Emiratisation remain organization-level because those records are currently company-scoped.

## Audit Logs

### `GET /api/audit-logs`

Returns paginated company-scoped audit logs. Requires `audit_logs.view`.

Supported filters:

- `module`
- `action`
- `employee_id`
- `actor_user_id`
- `date_from`
- `date_to`
- `page`
- `per_page`

### `GET /api/audit-logs/{auditLog}`

Returns one company-scoped audit log with before/after snapshots when the user has permission to view the affected data. Payroll, salary, and document snapshots are redacted unless the viewer has the matching payroll, salary, or document permission.

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

## Platform Billing

Platform billing is restricted to `super_admin` users for plan management, subscription assignment, invoice creation, and payment recording.

Implemented endpoints:

- `GET /api/billing/current`
- `GET /api/platform/billing/plans`
- `POST /api/platform/billing/plans`
- `GET /api/platform/billing/subscriptions`
- `POST /api/platform/billing/companies/{company}/subscription`
- `GET /api/platform/billing/invoices`
- `POST /api/platform/billing/companies/{company}/invoices`
- `POST /api/platform/billing/invoices/{invoice}/mark-paid`

Create plan request:

```json
{
  "code": "growth",
  "name": "Growth",
  "description": "For growing UAE teams",
  "monthly_price": 499,
  "currency": "AED",
  "employee_limit": 100,
  "features": ["employees", "leave", "payroll"],
  "status": "active"
}
```

Assign subscription request:

```json
{
  "subscription_plan_id": 1,
  "status": "active",
  "billing_interval": "monthly",
  "trial_ends_at": null,
  "current_period_starts_at": "2026-05-01",
  "current_period_ends_at": "2026-05-31"
}
```

Create invoice request:

```json
{
  "company_subscription_id": 1,
  "invoice_number": "INV-2026-0001",
  "issued_at": "2026-05-01",
  "due_at": "2026-05-15",
  "amount": 499,
  "currency": "AED",
  "status": "issued",
  "notes": "May subscription"
}
```

Mark invoice paid request:

```json
{
  "payment_reference": "bank-transfer-123"
}
```

Rules:

- `GET /api/billing/current` returns the authenticated user's current company subscription and latest invoices when the user has `companies.view`.
- Subscription assignment closes any existing active or trialing subscription before creating the new company subscription.
- Invoice creation requires the selected subscription to belong to the target company.
- Marking an invoice paid stores `paid_at`, status, and payment reference.
- Subscription assignment, invoice creation, and payment recording create audit logs.

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
- `manager_employee_id`, when provided, must belong to the current company and cannot point to the same employee on update.
- Employee profile responses include personal details, reporting manager, UAE citizenship/skilled-worker compliance fields, contract dates, and contract expiry status.
- Salary fields are returned only when the user has `employees.view_salary`.
- `GET /api/employees?contract_expiring_days=60` returns active/onboarding/on-leave/suspended employees with contract end dates in the next 60 days.
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
- `GET /api/attendance-correction-requests`
- `POST /api/attendance-correction-requests`
- `POST /api/attendance-correction-requests/{correction}/approve`
- `POST /api/attendance-correction-requests/{correction}/reject`

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
- Correction requests are submitted as `pending` and do not mutate attendance until approval.
- Employees can submit correction requests for their own attendance through self-service.
- Approving a correction updates the existing attendance record or creates one for missed-punch days.
- Submitting, approving, rejecting, and applying corrections create audit logs.
- Manual attendance entry is ready for future web, mobile, biometric, and import sources.

## Leave Management

Implemented endpoints:

- `GET /api/leave-types`
- `GET /api/leave-balances`
- `POST /api/leave-balances/accrue-annual`
- `POST /api/leave-balances`
- `GET /api/leave-requests/day-count`
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
- `GET /api/leave-requests/day-count` previews calendar days, balance-impact days, and excluded public holidays before submission.
- Annual leave excludes active paid public holidays from balance days when company compliance settings say public holidays do not count as annual leave.
- Leave requests persist `public_holidays_count` and `day_calculation` so the balance impact can be audited later.
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
- `GET /api/wps-payroll-batches`
- `GET /api/wps-payroll-batches/{batch}`
- `GET /api/wps-payroll-batches/{batch}/download`
- `POST /api/wps-payroll-batches/{batch}/status`
- `POST /api/payroll-periods/{payroll_period}/wps-export`

Allowance or deduction request:

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

Employee allowance or deduction assignment request:

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
- Running payroll regenerates draft payslips for active employees and terminated employees with unpaid final settlements inside the period.
- Payslips include employee basic salary plus active recurring salary assignments effective during the period.
- Final settlement payslips include gratuity, leave encashment, notice pay, other earnings, and settlement deductions as auditable payslip items.
- Approving a payroll period marks included final settlement records as paid with `payment_reference = payroll_period:{id}`.
- Deductions reduce net pay; earnings increase gross pay.
- Approved payroll periods cannot be rerun.
- Salary setup, payroll runs, and payroll approvals create audit logs.

WPS export rules:

- WPS export requires `payroll.export`.
- Only approved payroll periods can be exported.
- Company WPS setup must include `mohre_establishment_number`, `wps_bank_name`, `wps_agent_code`, and `wps_file_sender_id`.
- Every exported employee must have a work permit or labor card number, a checksum-valid UAE `bank_iban`, `bank_routing_code`, and `wps_person_id`.
- UAE IBANs are normalized to uppercase without spaces during employee writes.
- Export files use the selected company `wps_provider` profile and download with a `.sif` extension.
- Bundled profiles must be checked against the current template supplied by the selected bank or WPS agent before production submission.
- One WPS batch is stored per payroll period; generated batches may be regenerated until they are submitted or accepted.
- Batch statuses are `generated`, `submitted`, `processing`, `accepted`, `partially_accepted`, `rejected`, `corrected`, and `cancelled`.
- Status updates may store bank references, response filenames, and structured response details.
- WPS generation and status changes create audit logs.
- `php artisan wps:monitor-deadlines` persists dashboard alerts after 3, 10, and 15 days from the payroll pay date and resolves them after accepted status.

Update WPS status request:

```json
{
  "status": "submitted",
  "rejection_reason": null,
  "bank_reference": "BANK-REFERENCE-123"
}
```

## Compliance Calculations

Implemented endpoints:

- `GET /api/compliance/legal-rules`
- `GET /api/compliance/settings`
- `PUT /api/compliance/settings`
- `POST /api/compliance/gratuity`
- `GET /api/compliance/emiratisation`
- `POST /api/compliance/emiratisation/snapshot`
- `GET /api/compliance/reports`
- `GET /api/compliance/reports/export?type=settings`
- `GET /api/compliance/reports/export?type=public_holidays`
- `GET /api/compliance/reports/export?type=emiratisation`
- `GET /api/compliance/reports/export?type=audit`
- `GET /api/public-holidays`
- `POST /api/public-holidays`
- `POST /api/public-holidays/import`
- `PUT /api/public-holidays/{public_holiday}`
- `DELETE /api/public-holidays/{public_holiday}`
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
- Company compliance settings expose payroll day divisor, annual leave accrual/carry-forward, public holiday treatment, sick leave document rules, and Emiratisation monitoring.
- Compliance setting updates require `settings.update` and create audit logs.
- Payroll policy screen uses `payroll_day_divisor` to control daily wage calculations for salary-linked leave pay.
- Emiratisation endpoints calculate active workers, skilled workers, UAE citizens, required UAE citizen count, missing count, estimated contribution exposure, and compliance status.
- Emiratisation snapshots persist the current guidance result and create an audit log.
- Compliance report endpoints require `settings.view`, are scoped to the current company, and expose CSV exports for settings, public holidays, Emiratisation snapshots, and compliance audit history.
- Public holiday reads require `settings.view`; create, update, and delete require `settings.update`.
- Public holiday records are scoped to the authenticated user's current company and are unique by company, date, and name.
- Public holiday import accepts up to 100 rows, creates non-duplicate holidays, skips duplicate company/date/name rows, and returns an import summary.
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
