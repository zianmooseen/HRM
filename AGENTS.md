# AGENTS.md

## Project Name

UAE HRM Platform

## Purpose

Build a modern Human Resource Management system designed primarily for the UAE market, with future expansion into accounting, payroll, POS, and business operations modules.

This repository should be optimized for AI-assisted development using Codex or similar coding agents. The frontend and backend should live in the same repository so the agent can understand the full system, maintain API contracts, and make coordinated changes safely.

---

## Recommended Repository Strategy

Use a monorepo.

The frontend and backend should be in the same top-level project folder, but kept as clearly separated applications.

Recommended structure:

```txt
uae-hrm-platform/
├── AGENTS.md
├── README.md
├── docker-compose.yml
├── .env.example
├── docs/
│   ├── product-requirements.md
│   ├── database-schema.md
│   ├── api-contracts.md
│   ├── uae-labor-law-notes.md
│   └── implementation-plan.md
├── frontend/
│   └── Nuxt application
├── backend/
│   └── Laravel API application
├── shared/
│   ├── types/
│   ├── validation/
│   └── constants/
└── scripts/
```

### Why monorepo is preferred

For this project, a monorepo is better because:

1. The AI agent can see the frontend, backend, database, API contracts, and documentation in one place.
2. Feature work becomes easier because HRM screens usually depend directly on backend models and APIs.
3. Authentication, roles, permissions, employee records, payroll, leave management, and attendance need tight frontend-backend coordination.
4. It reduces mismatch between frontend expectations and backend responses.
5. It makes local development easier with Docker.
6. It makes future modules like accounting and POS easier to connect later.
7. One repository is easier to review, test, and deploy during early-stage product development.

### Important rule

Do not mix frontend and backend code together.

Use one repo, but keep separate folders:

```txt
frontend/
backend/
shared/
docs/
```

---

## Tech Stack

### Frontend

Use:

- Nuxt 4 or latest stable Nuxt version
- Vue 3
- TypeScript
- Pinia for state management
- Tailwind CSS or Vuetify, depending on final UI choice
- Zod or equivalent validation if shared validation is needed
- Axios or native fetch wrapper for API calls

Frontend responsibilities:

- HRM dashboard
- Employee management UI
- Attendance UI
- Leave request UI
- Payroll UI
- Role-based navigation
- Auth screens
- Admin/company settings
- Responsive PWA-friendly layout

### Backend

Use:

- Laravel
- PHP 8.3+
- Laravel Sanctum for authentication
- MySQL or PostgreSQL
- Laravel Policies/Gates for permissions
- Laravel Form Requests for validation
- Laravel API Resources for consistent JSON responses
- Queue support for emails, payroll calculations, reports, and notifications

Backend responsibilities:

- Authentication
- Company/tenant management
- Employee records
- Roles and permissions
- Attendance
- Leave management
- Payroll rules
- UAE labor-law-related calculations
- Reports
- Audit logs
- API endpoints

### Database

Prefer PostgreSQL if advanced reporting, JSON fields, and future accounting features are important.

MySQL is acceptable if deployment simplicity is preferred.

For the first MVP, design the schema carefully around:

- companies
- branches
- users
- employees
- departments
- job titles
- roles
- permissions
- role_permissions
- user_roles
- employee_invitations
- onboarding_templates
- onboarding_template_tasks
- employee_onboarding_cases
- employee_onboarding_tasks
- attendance records
- leave requests
- payroll periods
- salary components
- payslips
- documents
- audit logs

---

## Product Context

The app is primarily targeted at UAE businesses.

Important UAE HRM concepts to support:

- Fixed-term employment contracts
- Probation period tracking
- Standard working hours
- Overtime tracking
- Leave management
- Maternity leave
- Sick leave
- Public holidays
- End-of-service gratuity
- Salary records
- Employee documents
- Visa/passport/labor card expiry tracking
- WPS/payroll export support in the future

The system should be designed so legal rules can be stored as configurable business rules instead of hardcoded everywhere.

---

## Development Principles

Codex must follow these rules:

1. Read this `AGENTS.md` before making changes.
2. Read relevant files before editing.
3. Do not rewrite large parts of the project without a reason.
4. Make small, safe, reviewable changes.
5. Keep frontend and backend responsibilities separate.
6. Use TypeScript on the frontend.
7. Use Laravel conventions on the backend.
8. Do not hardcode secrets.
9. Never commit `.env` files.
10. Keep API response shapes consistent.
11. Add tests for important backend logic.
12. Add validation for all write operations.
13. Use role-based permissions for sensitive HR data.
14. Keep UAE labor law rules configurable where possible.
15. Update documentation when adding major features.

---

## Security Rules

This project handles sensitive employee and payroll data.

Always prioritize security.

Required rules:

- Use server-side authorization for every protected action.
- Do not rely only on frontend role checks.
- Validate all input on the backend.
- Sanitize user-generated content where needed.
- Use HTTPS in production.
- Hash passwords using Laravel defaults.
- Use secure cookies/session handling.
- Avoid exposing salary, payroll, or document data unless the user has permission.
- Add audit logs for sensitive actions.
- Do not expose stack traces in production.
- Use environment variables for secrets.
- Use least-privilege database access.
- Do not store unnecessary personal data.
- Prepare for future data retention rules.

Sensitive actions that should create audit logs:

- Creating employees
- Updating employee salary
- Uploading or deleting documents
- Approving leave
- Rejecting leave
- Editing attendance records
- Running payroll
- Generating payslips
- Changing user roles
- Changing company settings

---

## AI Agent Workflow

When asked to build a feature, Codex should:

1. Understand the feature request.
2. Inspect relevant frontend, backend, docs, and database files.
3. Propose the implementation plan briefly.
4. Modify files in small steps.
5. Add or update tests when needed.
6. Run lint/test commands when possible.
7. Summarize what changed.
8. Mention any files that still need manual review.

---

## Commands

These commands may change after the project is initialized.

### Root

```bash
docker compose up -d
```

### Frontend

```bash
cd frontend
npm install
npm run dev
npm run build
npm run lint
```

### Backend

```bash
cd backend
composer install
php artisan serve
php artisan migrate
php artisan test
```

---

## Environment Files

Use separate environment examples.

```txt
.env.example
frontend/.env.example
backend/.env.example
```

Never commit real secrets.

Expected frontend environment variables:

```env
NUXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
```

Expected backend environment variables:

```env
APP_NAME="UAE HRM Platform"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=uae_hrm
DB_USERNAME=uae_hrm
DB_PASSWORD=secret

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
FRONTEND_URL=http://localhost:3000
```

---

## API Response Standard

All backend API responses should follow consistent shapes.

### Success response

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

### Validation error response

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

### Authorization error response

```json
{
  "success": false,
  "message": "You are not authorized to perform this action."
}
```

---

## Authentication Plan

Use Laravel Sanctum.

Frontend should call:

```txt
GET /sanctum/csrf-cookie
POST /api/login
POST /api/logout
GET /api/me
```

The authenticated user response should include:

```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "roles": ["company_admin"],
  "permissions": ["employees.view", "employees.create"]
}
```

---

## Role Model

Start with these roles:

1. Super Admin
2. Company Admin
3. HR Manager
4. Payroll Manager
5. Department Manager
6. Employee

Suggested permissions:

```txt
companies.view
companies.create
companies.update

employees.view
employees.create
employees.update
employees.delete
employees.view_salary

attendance.view
attendance.create
attendance.update
attendance.approve

leave.view
leave.create
leave.approve
leave.reject

payroll.view
payroll.run
payroll.approve
payroll.export

documents.view
documents.upload
documents.delete

settings.view
settings.update

audit_logs.view
```

---



---

## Required Core Database Tables

The backend must include explicit tables for branches, role assignment, and onboarding workflows.

### Companies

```txt
companies
- id
- name
- legal_name
- trade_license_number
- tax_registration_number
- country
- emirate
- default_currency
- timezone
- status
- created_at
- updated_at
- deleted_at
```

### Branches

Branches represent physical offices, stores, warehouses, worksites, or operating locations.

```txt
branches
- id
- company_id
- name
- code
- emirate
- city
- address_line_1
- address_line_2
- phone
- email
- manager_employee_id
- status
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

Rules:

- A company can have many branches.
- An employee should usually belong to one primary branch.
- Departments may optionally belong to a branch.
- Attendance, payroll, cost centers, approvals, and reports should be filterable by branch.

### Users

Users are login accounts.

```txt
users
- id
- name
- email
- password
- phone
- status
- last_login_at
- created_at
- updated_at
- deleted_at
```

### Employees

Employees are HR records. Not every employee has to be a login user at first.

```txt
employees
- id
- company_id
- branch_id
- department_id
- job_title_id
- manager_employee_id
- user_id
- employee_code
- first_name
- middle_name
- last_name
- display_name
- personal_email
- work_email
- phone
- gender
- nationality
- date_of_birth
- hire_date
- probation_end_date
- contract_start_date
- contract_end_date
- employment_type
- contract_type
- status
- basic_salary
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

Rules:

- `user_id` is nullable.
- An employee can exist before their login account is created.
- Salary visibility must be permission-protected.
- `manager_employee_id` allows approval workflows and reporting hierarchy.

### Departments

```txt
departments
- id
- company_id
- branch_id
- name
- code
- manager_employee_id
- status
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

### Job Titles

```txt
job_titles
- id
- company_id
- title
- code
- description
- status
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

### Roles

```txt
roles
- id
- company_id
- name
- slug
- description
- is_system_role
- created_at
- updated_at
```

Rules:

- System roles can be global.
- Company-specific roles should belong to a company.
- Example role slugs: `super_admin`, `company_admin`, `hr_manager`, `payroll_manager`, `branch_manager`, `department_manager`, `employee`.

### Permissions

```txt
permissions
- id
- name
- slug
- description
- module
- created_at
- updated_at
```

### Role Permissions

```txt
role_permissions
- id
- role_id
- permission_id
- created_at
- updated_at
```

### User Roles

This is the required pivot table that assigns roles to users.

```txt
user_roles
- id
- user_id
- company_id
- branch_id
- department_id
- role_id
- scope
- created_by
- created_at
- updated_at
```

Recommended `scope` values:

```txt
global
company
branch
department
self
```

Rules:

- `branch_id` is nullable.
- `department_id` is nullable.
- A company admin may have company-level access.
- A branch manager may have branch-level access.
- A department manager may have department-level access.
- An employee may have self-only access.
- Authorization must check the user's role, company, branch, department, and permission.

---

## Employee Onboarding Model

Employee onboarding should be treated as a workflow, not just as a row in the `employees` table.

The system should support:

1. Creating a draft employee profile.
2. Selecting company, branch, department, job title, and reporting manager.
3. Assigning an onboarding template.
4. Generating onboarding tasks.
5. Collecting employee documents.
6. Creating a login account if needed.
7. Reviewing HR and payroll details.
8. Marking onboarding as completed.
9. Activating the employee.

### Employee Invitations

Used when the company invites a new employee to complete their profile or create an account.

```txt
employee_invitations
- id
- company_id
- employee_id
- invited_email
- token_hash
- expires_at
- accepted_at
- status
- invited_by
- created_at
- updated_at
```

### Onboarding Templates

Reusable onboarding checklists.

```txt
onboarding_templates
- id
- company_id
- name
- description
- employment_type
- is_default
- status
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

### Onboarding Template Tasks

Tasks inside a reusable template.

```txt
onboarding_template_tasks
- id
- onboarding_template_id
- title
- description
- task_type
- assigned_to_role
- required
- sort_order
- due_days_after_start
- created_at
- updated_at
```

Suggested `task_type` values:

```txt
document_upload
hr_review
payroll_setup
account_creation
policy_acknowledgement
asset_assignment
training
custom
```

### Employee Onboarding Cases

One onboarding workflow for one employee.

```txt
employee_onboarding_cases
- id
- company_id
- employee_id
- onboarding_template_id
- status
- started_at
- completed_at
- cancelled_at
- created_by
- updated_by
- created_at
- updated_at
```

Suggested `status` values:

```txt
draft
in_progress
waiting_for_employee
waiting_for_hr
waiting_for_payroll
completed
cancelled
```

### Employee Onboarding Tasks

Actual tasks generated for a specific employee.

```txt
employee_onboarding_tasks
- id
- company_id
- employee_onboarding_case_id
- employee_id
- title
- description
- task_type
- assigned_to_user_id
- assigned_to_role
- required
- status
- due_date
- completed_at
- completed_by
- created_at
- updated_at
```

Suggested `status` values:

```txt
pending
in_progress
blocked
completed
skipped
cancelled
```

### Onboarding Flow

```txt
HR creates employee draft
        ↓
HR selects branch, department, job title, manager, and employment details
        ↓
System assigns onboarding template
        ↓
System generates onboarding tasks
        ↓
Employee receives invitation if self-service is enabled
        ↓
Employee uploads required documents
        ↓
HR verifies documents and personal details
        ↓
Payroll manager sets salary components
        ↓
IT/Admin creates login access if needed
        ↓
Manager/HR approves onboarding completion
        ↓
Employee status changes from draft/onboarding to active
```

### Employee Status Lifecycle

```txt
draft
onboarding
active
on_leave
suspended
terminated
archived
```

Rules:

- Payroll should not run for `draft` employees.
- Attendance should usually start only after employee is `active`.
- Documents can be uploaded during onboarding.
- Audit logs must track onboarding changes.



---

## UAE Compliance Engine

The HRM platform must include a configurable compliance engine for UAE labour-law-related rules.

Important principle:

Do not hardcode all legal values directly inside controllers or frontend screens.

Instead, store legal rule defaults in versioned rule tables or config seeders, then allow company admins to configure company policies only when those policies are equal to or more generous than the legal minimum.

The system should support:

- UAE annual leave rules
- sick leave pay calculation
- public holiday handling
- maternity leave
- end-of-service gratuity
- probation restrictions
- Emiratisation monitoring
- company-specific leave policies
- company-specific payroll policies
- branch-level reporting
- audit logs for compliance setting changes

All compliance calculations should be implemented in backend service classes and covered by tests.

---

## Compliance Rule Tables

### Legal Rule Sets

Use this to version legal rules over time.

```txt
legal_rule_sets
- id
- country_code
- jurisdiction
- name
- version
- effective_from
- effective_to
- source_reference
- status
- created_at
- updated_at
```

Example:

```txt
country_code: AE
jurisdiction: UAE_PRIVATE_SECTOR
name: UAE Labour Law Private Sector Rules
version: 2026.1
effective_from: 2026-01-01
```

### Legal Rule Items

```txt
legal_rule_items
- id
- legal_rule_set_id
- rule_key
- rule_type
- value_json
- description
- source_reference
- created_at
- updated_at
```

Example `rule_key` values:

```txt
annual_leave.full_year_days
annual_leave.after_6_months_days_per_month
sick_leave.max_days_per_year
sick_leave.full_pay_days
sick_leave.half_pay_days
sick_leave.unpaid_days
maternity_leave.full_pay_days
maternity_leave.half_pay_days
emiratisation.large_company_annual_growth_percent
emiratisation.large_company_semi_annual_growth_percent
```

### Company Compliance Settings

```txt
company_compliance_settings
- id
- company_id
- legal_rule_set_id
- payroll_day_divisor
- annual_leave_accrual_method
- annual_leave_carry_forward_allowed
- annual_leave_max_carry_forward_days
- public_holidays_count_as_annual_leave
- sick_leave_requires_medical_certificate
- sick_leave_notification_days
- emiratisation_monitoring_enabled
- created_by
- updated_by
- created_at
- updated_at
```

Suggested `payroll_day_divisor` options:

```txt
calendar_30
actual_calendar_days
working_days
```

Rules:

- The company can choose calculation methods where legally allowed.
- The company cannot reduce statutory leave or pay below legal minimums.
- Changes must be audit logged.

---

## Leave Policy Tables

### Leave Types

```txt
leave_types
- id
- company_id
- code
- name
- category
- paid_type
- requires_approval
- requires_document
- is_statutory
- status
- created_at
- updated_at
```

Suggested leave type codes:

```txt
annual_leave
sick_leave
maternity_leave
parental_leave
bereavement_leave
hajj_leave
unpaid_leave
public_holiday
study_leave
```

### Leave Policies

```txt
leave_policies
- id
- company_id
- legal_rule_set_id
- name
- description
- applies_to_employment_type
- applies_to_branch_id
- applies_to_department_id
- effective_from
- effective_to
- status
- created_by
- updated_by
- created_at
- updated_at
```

### Leave Policy Rules

```txt
leave_policy_rules
- id
- leave_policy_id
- leave_type_id
- rule_key
- value_json
- minimum_legal_value_json
- validation_behavior
- created_at
- updated_at
```

Example annual leave policy rules:

```txt
annual_leave.days_after_one_year = 30
annual_leave.days_per_month_after_six_months = 2
annual_leave.allow_carry_forward = true
annual_leave.max_carry_forward_days = company_defined
annual_leave.encashment_pay_basis = basic_salary
annual_leave.active_leave_pay_basis = full_wage
```

Example sick leave policy rules:

```txt
sick_leave.max_days_per_year = 90
sick_leave.full_pay_days = 15
sick_leave.half_pay_days = 30
sick_leave.unpaid_days = 45
sick_leave.no_paid_sick_leave_during_probation = true
sick_leave.requires_medical_certificate = true
sick_leave.notification_days = 3
```

Rules:

- Admins may offer more annual leave than the statutory minimum.
- Admins may offer more paid sick leave than the statutory minimum.
- Admins may not configure less than the statutory minimum.
- If a company wants a stricter document policy, it can require documents, but legal eligibility must still be respected.

### Employee Leave Balances

```txt
employee_leave_balances
- id
- company_id
- employee_id
- leave_type_id
- leave_year
- opening_balance
- accrued_days
- used_days
- pending_days
- carried_forward_days
- encashed_days
- adjusted_days
- closing_balance
- created_at
- updated_at
```

### Leave Requests

```txt
leave_requests
- id
- company_id
- employee_id
- leave_type_id
- start_date
- end_date
- total_days
- working_days
- status
- reason
- medical_certificate_document_id
- requested_by
- approved_by
- approved_at
- rejected_by
- rejected_at
- rejection_reason
- created_at
- updated_at
```

### Leave Pay Calculation Items

Store the calculation result so payroll is auditable.

```txt
leave_pay_calculation_items
- id
- company_id
- leave_request_id
- employee_id
- payroll_period_id
- leave_type_id
- pay_tier
- days
- pay_percentage
- daily_wage
- gross_pay_amount
- deduction_amount
- calculation_basis
- rule_snapshot_json
- created_at
- updated_at
```

Suggested `pay_tier` values:

```txt
full_pay
half_pay
unpaid
```

---

## Sick Leave Calculation Requirement

Create a backend service:

```txt
App\Services\Leave\SickLeaveCalculator
```

The service should:

1. Check whether the employee is still in probation.
2. Check whether the sick leave is work-injury-related.
3. Check whether required medical documentation exists.
4. Count sick leave already used in the current leave year.
5. Split the new sick leave request across pay tiers.
6. Return payroll calculation items.
7. Store a snapshot of the rules used at the time of calculation.

Default UAE private-sector sick leave rule:

```txt
maximum sick leave per year: 90 days
first 15 days: full pay
next 30 days: half pay
remaining 45 days: unpaid
probation period: no paid sick leave by default
employee notification: within 3 working days
medical report: required
```

Example:

Employee requests 20 sick leave days and has used 10 sick leave days already this year.

```txt
Previously used: 10 days
New request: 20 days

Remaining full-pay tier: 5 days
Half-pay tier used in this request: 15 days
Unpaid tier used in this request: 0 days
```

Payroll result:

```txt
5 days × 100% daily wage
15 days × 50% daily wage
0 days × 0% daily wage
```

If monthly payroll already includes full monthly salary, the payroll engine should generate deductions instead:

```txt
5 full-pay sick days: no deduction
15 half-pay sick days: deduct 50% daily wage for each day
0 unpaid sick days: deduct 100% daily wage for each day
```

---

## Annual Leave / Vacation Configuration Requirement

Company admins should be able to configure vacation policies while staying compliant with UAE labour law.

Create screens and backend services for:

- annual leave entitlement
- accrual method
- carry-forward rules
- encashment rules
- leave approval workflow
- public holiday treatment
- branch/department-specific policies
- employee-specific adjustments

Default UAE private-sector annual leave rule:

```txt
after 1 year of service: 30 days annual leave
after more than 6 months but less than 1 year: 2 days per month
unused leave on termination: paid based on basic salary
annual leave while employed: full wage
```

Validation rules:

- Company cannot set annual leave below legal minimum.
- Company can set annual leave above legal minimum.
- Company can create approval workflows.
- Company can define carry-forward limits, but should not prevent legal entitlement.
- Company can configure whether leave is calculated using calendar days or working days, but the policy must be clear and auditable.
- Public holidays inside annual leave should follow UAE legal defaults unless the contract/company policy gives a more favorable result to the employee.

---

## Emiratisation Compliance Monitoring

Create a backend service:

```txt
App\Services\Compliance\EmiratisationComplianceService
```

The service should monitor Emiratisation obligations.

Required data fields on employees:

```txt
employees.nationality
employees.is_uae_citizen
employees.skill_level
employees.is_skilled_worker
employees.employment_status
employees.work_permit_type
employees.monthly_salary
```

Required company fields:

```txt
companies.emiratisation_applicable
companies.emiratisation_category
companies.economic_sector_code
companies.mohre_establishment_number
```

Suggested company category values:

```txt
large_50_plus
selected_20_to_49
not_applicable
```

### Emiratisation Rule Tables

```txt
emiratisation_rules
- id
- legal_rule_set_id
- category
- min_employee_count
- max_employee_count
- sector_codes_json
- annual_growth_percent
- semi_annual_growth_percent
- required_uae_citizens
- contribution_amount_per_missing_citizen
- contribution_frequency
- effective_from
- effective_to
- status
- created_at
- updated_at
```

### Emiratisation Snapshots

```txt
emiratisation_snapshots
- id
- company_id
- snapshot_date
- total_active_workers
- total_skilled_workers
- total_active_uae_citizens
- total_skilled_uae_citizens
- required_uae_citizens
- missing_uae_citizens
- estimated_contribution_amount
- compliance_status
- rule_snapshot_json
- created_at
- updated_at
```

Suggested `compliance_status` values:

```txt
not_applicable
compliant
at_risk
non_compliant
needs_review
```

### Emiratisation Calculation Logic

For companies with 50 or more employees:

1. Count active employees.
2. Count active skilled workers.
3. Count active UAE citizens in skilled roles.
4. Apply the configured Emiratisation rule for the period.
5. Calculate required UAE citizen count.
6. Compare actual UAE skilled employee count against required count.
7. Show missing count and estimated financial contribution if non-compliant.

For selected companies with 20 to 49 employees:

1. Check whether the company's sector is included in the Emiratisation rule.
2. Count active UAE citizen employees.
3. Require at least one UAE citizen, or the configured legal requirement for that period.
4. Show compliant, at risk, or non-compliant status.

Important:

- The system should treat Emiratisation results as compliance guidance, not legal certification.
- MoHRE rules and contribution amounts can change, so all values must be configurable by effective date.
- Admins should be able to manually mark a company as `needs_review` if the automated result does not match MoHRE records.

---

## Compliance Admin Screens

Build admin screens for:

```txt
Company Settings > Compliance
Company Settings > Leave Policies
Company Settings > Payroll Policies
Company Settings > Emiratisation
Company Settings > Public Holidays
```

Admin abilities:

- View default UAE legal rules.
- Configure company leave policies.
- Configure payroll calculation basis.
- Configure branch-specific policies.
- View Emiratisation status.
- View missing Emirati citizen count.
- View estimated contribution exposure.
- Export compliance reports.
- See audit history for compliance setting changes.

Restrictions:

- Only company admins and authorized HR/payroll users can change compliance settings.
- Settings below UAE legal minimum must be rejected by backend validation.
- Every compliance setting change must be audit logged.

## Core MVP Modules

Build the MVP in this order:

1. Project setup
2. Authentication
3. Company setup
4. User roles and permissions
5. Employee management
6. Departments and job titles
7. Attendance tracking
8. Leave management
9. Payroll foundation
10. UAE gratuity calculator
11. UAE compliance engine for leave, sick leave, vacation pay, and Emiratisation
12. Employee documents
12. Dashboard and reports
13. Audit logs
14. Attendance correction workflow
15. SaaS platform admin and client billing
16. MoHRE establishment and WPS payroll compliance
17. Numbered feature flows and developer comments
18. Employee self-service and company file storage
19. AWS S3 default storage setup
20. Production deployment setup

---

## Codex Prompt 1: Initialize Monorepo

Use this prompt to start the project.

```txt
You are working inside a new repository for a UAE-focused HRM platform.

Create a monorepo structure with:

- frontend/ for a Nuxt 4 + Vue 3 + TypeScript app
- backend/ for a Laravel API app
- docs/ for product and technical documentation
- shared/ for shared types/constants
- scripts/ for helper scripts
- root docker-compose.yml
- root README.md
- root .env.example

Do not mix frontend and backend code.

Use clean folder naming and prepare the project for AI-assisted development.

After creating the structure, add basic README instructions for local development.
```

---

## Codex Prompt 2: Create Product Requirements Document

```txt
Create docs/product-requirements.md for a UAE-focused HRM SaaS platform.

Include:

- Product vision
- Target users
- Business model
- Core MVP modules
- Future modules
- UAE market focus
- Role types
- Key workflows
- Non-functional requirements
- Security requirements
- Future expansion into accounting and POS

Keep the document practical and implementation-focused.
```

---

## Codex Prompt 3: Create Backend Laravel API

```txt
Inside backend/, initialize a Laravel API application.

Set up:

- Laravel Sanctum authentication
- API route structure
- Form Request validation pattern
- API Resource response pattern
- basic User model updates
- database migrations for companies, branches, users, employees, departments, job_titles, roles, permissions, role_permissions, user_roles, employee_invitations, onboarding_templates, onboarding_template_tasks, employee_onboarding_cases, employee_onboarding_tasks, and audit_logs
- seeders for default roles and permissions
- PHPUnit/Pest test setup

Follow Laravel conventions.

Do not build frontend code in this step.
```

---

## Codex Prompt 4: Create Frontend Nuxt App

```txt
Inside frontend/, initialize a Nuxt app using Vue 3 and TypeScript.

Set up:

- app layout
- auth pages
- dashboard shell
- protected route middleware
- Pinia store
- API client wrapper
- role-aware navigation structure
- basic reusable UI components

Do not hardcode backend responses.

Use NUXT_PUBLIC_API_BASE_URL for the backend URL.
```

---

## Codex Prompt 5: Implement Authentication End-to-End

```txt
Implement authentication end-to-end across Laravel and Nuxt.

Backend requirements:

- Laravel Sanctum login
- logout
- /api/me endpoint
- validation
- consistent JSON responses
- tests for login, logout, and me endpoint

Frontend requirements:

- login page
- auth store
- API client
- route middleware
- logout action
- display logged-in user in dashboard

Use secure cookie-based authentication where appropriate.

Update docs/api-contracts.md with the authentication endpoints.
```

---

## Codex Prompt 6: Implement Company and Employee Module

```txt
Build the company and employee management module.

Backend:

- companies table
- branches table
- employees table
- departments table
- job_titles table
- role and permission tables
- user_roles pivot table
- onboarding workflow tables
- relationships
- CRUD endpoints
- Form Request validation
- API Resources
- authorization policies
- audit logs for create/update/delete
- tests

Frontend:

- employee list page
- employee create page
- employee detail page
- employee edit page
- department and job title dropdowns
- role-based visibility
- loading and error states

Update docs/database-schema.md and docs/api-contracts.md.
```

---

## Codex Prompt 7: Implement Attendance Module

```txt
Build the attendance module.

Backend:

- attendance_records table
- fields for employee_id, date, check_in, check_out, break_minutes, status, source, notes
- CRUD endpoints
- manager approval flow if needed
- policies
- audit logging for manual edits
- tests

Frontend:

- attendance list
- daily attendance view
- manual attendance entry form
- employee attendance profile section
- filters by employee, department, and date range

Keep the design ready for future biometric or mobile check-in integration.
```

---

## Codex Prompt 8: Implement Leave Management

```txt
Build the leave management module.

Backend:

- leave_types table
- leave_balances table
- leave_requests table
- approval workflow
- validation to prevent invalid dates
- policies
- audit logs
- tests

Frontend:

- leave request form
- leave request list
- manager approval screen
- leave balance display
- status badges

Include UAE-relevant leave types such as annual leave, sick leave, maternity leave, unpaid leave, and public holiday.
```

---

## Codex Prompt 9: Implement Payroll Foundation

```txt
Build the payroll foundation.

Backend:

- salary_components table
- employee_salary_components table
- payroll_periods table
- payslips table
- payslip_items table
- payroll calculation service
- policies
- audit logs
- tests for calculation logic

Frontend:

- payroll periods list
- run payroll screen
- payslip detail screen
- employee salary setup section

Keep this flexible for UAE payroll, WPS export, allowances, deductions, and future accounting integration.
```

---

## Codex Prompt 10: UAE End-of-Service Gratuity Calculator

```txt
Implement a UAE end-of-service gratuity calculator.

Backend:

- create a service class for gratuity calculation
- support employee start date, end date, basic salary, contract type, and termination reason where needed
- keep labor law rules configurable
- add tests with multiple employment duration scenarios
- expose an API endpoint for calculation preview

Frontend:

- gratuity calculator screen
- employee-linked calculation
- manual calculation mode
- clear breakdown of the formula

Add notes in docs/uae-labor-law-notes.md explaining assumptions and reminding that legal rules must be reviewed before production use.
```

---

## Codex Prompt 11: Employee Documents and Expiry Tracking

```txt
Build employee document management.

Backend:

- employee_documents table
- fields for employee_id, document_type, file_path, issue_date, expiry_date, status
- secure upload endpoint
- secure download endpoint
- policies
- audit logs
- tests

Frontend:

- document upload form
- employee document list
- expiry badges
- dashboard reminders for documents expiring soon

Support document types such as passport, visa, Emirates ID, labor card, contract, and certificates.
```

---

## Codex Prompt 12: Dashboard and Reports

```txt
Build the first dashboard and reporting screens.

Backend:

- dashboard summary endpoint
- employee count
- active employees
- pending leave requests
- today attendance summary
- upcoming document expiries
- payroll period status
- basic reports endpoints

Frontend:

- dashboard cards
- charts if appropriate
- pending approvals section
- expiring documents section
- quick actions

Keep data permission-aware.
```

---

## Codex Prompt 13: Audit Logs

```txt
Implement audit logging across sensitive HR actions.

Backend:

- audit_logs table
- AuditLogService
- trait or helper for logging model changes
- log actor_id, company_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent
- policy-protected audit log index endpoint

Frontend:

- audit log viewer
- filters by actor, action, entity type, and date range

Make sure salary and document-related changes are logged.
```

---

## Codex Prompt 14: Testing and Quality Setup

```txt
Set up quality tooling.

Backend:

- PHP CS Fixer or Laravel Pint
- PHPUnit/Pest tests
- feature tests for core API endpoints
- unit tests for payroll and gratuity services

Frontend:

- ESLint
- TypeScript checking
- component structure
- basic tests if test framework is installed

Root:

- add scripts or documentation for running all checks
- update README with test commands
```

---

## Codex Prompt 15: Docker Local Development

```txt
Create Docker-based local development setup.

Include:

- backend PHP/Laravel service
- frontend Nuxt service
- PostgreSQL database
- Redis service
- mail testing service if appropriate
- named volumes
- network configuration

Update README with exact commands to run the project locally.

Make sure .env.example files match Docker service names.
```

---

## Codex Prompt 16: Deployment Preparation

```txt
Prepare the project for production deployment.

Include:

- production environment variable checklist
- frontend build instructions
- backend deployment instructions
- database migration instructions
- queue worker notes
- scheduler notes
- storage symlink notes
- HTTPS requirement
- backup notes
- logging notes

Do not include real secrets.

Add docs/deployment.md.
```

---

## Coding Style Guidelines

### Frontend

- Use composition API.
- Use TypeScript types for API responses.
- Keep pages thin.
- Put API logic in composables or services.
- Put reusable UI in components.
- Avoid duplicating business rules in frontend.
- Handle loading, empty, and error states.
- Use role permissions to control UI visibility, but rely on backend authorization for real security.

### Backend

- Use controllers for HTTP coordination only.
- Put business logic in service classes.
- Use Form Requests for validation.
- Use Policies for authorization.
- Use API Resources for output formatting.
- Use migrations for schema changes.
- Use seeders for default data.
- Use tests for important rules.
- Keep payroll and legal calculations isolated in service classes.

---

## Database Design Guidelines

Use company_id on business records to support multi-company or future SaaS tenancy.

Important tables should include:

```txt
id
company_id
created_by
updated_by
created_at
updated_at
deleted_at, where soft deletes are useful
```

Use soft deletes for:

- employees
- departments
- job titles
- documents
- payroll periods
- payslips

Avoid soft deletes for:

- audit logs
- permission records
- immutable payroll history, unless legally reviewed

---

## Future Expansion

Design the HRM system so these modules can be added later:

1. Accounting
2. POS
3. Inventory
4. Invoicing
5. Expense management
6. CRM
7. Contractor management
8. Mobile app
9. Biometric attendance integration
10. WPS payroll export
11. AI assistant for HR documents and reports

Do not build these future modules in the MVP unless explicitly requested.

---



---

## Attendance Correction Workflow

Attendance correction must be handled as an approval workflow, not as direct editing of attendance records.

Important principle:

Raw attendance records should be preserved. Corrections should create auditable adjustment records or correction requests. Managers and HR users should approve corrections according to company policy.

### Attendance Records

```txt
attendance_records
- id
- company_id
- branch_id
- employee_id
- attendance_date
- check_in_at
- check_out_at
- break_minutes
- total_work_minutes
- late_minutes
- overtime_minutes
- status
- source
- device_id
- location_latitude
- location_longitude
- notes
- is_locked
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

Suggested `source` values:

```txt
manual
web
mobile
biometric
import
system_generated
correction
```

Suggested `status` values:

```txt
present
absent
late
half_day
on_leave
holiday
weekend
missing_check_in
missing_check_out
pending_correction
corrected
```

Rules:

- Payroll should use locked or approved attendance records.
- Once payroll is processed, attendance records for that payroll period should be locked.
- Locked records can only be changed through a correction workflow with special permission.

### Attendance Correction Requests

```txt
attendance_correction_requests
- id
- company_id
- branch_id
- employee_id
- attendance_record_id
- correction_type
- requested_check_in_at
- requested_check_out_at
- requested_break_minutes
- requested_status
- reason
- attachment_document_id
- status
- requested_by
- reviewed_by
- reviewed_at
- rejection_reason
- created_at
- updated_at
```

Suggested `correction_type` values:

```txt
missed_check_in
missed_check_out
wrong_check_in
wrong_check_out
break_time_adjustment
status_change
absence_dispute
overtime_adjustment
manual_hr_correction
```

Suggested `status` values:

```txt
draft
submitted
manager_approved
hr_approved
rejected
cancelled
applied
```

### Attendance Correction Approval Steps

```txt
attendance_correction_approval_steps
- id
- company_id
- attendance_correction_request_id
- step_order
- approver_role
- approver_user_id
- status
- approved_at
- rejected_at
- rejection_reason
- created_at
- updated_at
```

### Attendance Adjustments

When a correction is approved, create an adjustment snapshot instead of silently overwriting history.

```txt
attendance_adjustments
- id
- company_id
- attendance_record_id
- attendance_correction_request_id
- employee_id
- old_values_json
- new_values_json
- applied_by
- applied_at
- payroll_impact_status
- created_at
- updated_at
```

Suggested `payroll_impact_status` values:

```txt
not_applicable
pending_recalculation
recalculated
requires_manual_review
```

### Attendance Correction Flow

```txt
Employee notices missing or wrong attendance
        ↓
Employee submits correction request with reason
        ↓
Manager reviews request
        ↓
HR reviews request if company policy requires HR approval
        ↓
System applies approved correction
        ↓
System stores old and new values in attendance_adjustments
        ↓
Attendance record status changes to corrected
        ↓
If payroll period is open, payroll calculation updates
        ↓
If payroll period is locked, correction is marked as payroll adjustment for next period
```

### Manual HR Correction Flow

```txt
HR user opens attendance record
        ↓
HR submits manual correction reason
        ↓
System checks permissions
        ↓
If payroll is not locked, correction can be applied with audit log
        ↓
If payroll is locked, correction requires payroll manager approval
        ↓
System creates attendance_adjustment
        ↓
Audit log records old values and new values
```

Rules:

- Employees can request corrections for their own records only.
- Managers can approve corrections for employees under their scope.
- HR can approve or apply corrections based on permission.
- Payroll managers must review corrections that affect processed payroll.
- Every correction must be audit logged.
- The original record values must be recoverable.
- The frontend must clearly show original values, requested values, and approved values.

Required permissions:

```txt
attendance_corrections.view
attendance_corrections.create
attendance_corrections.approve_manager
attendance_corrections.approve_hr
attendance_corrections.apply
attendance_corrections.override_locked_period
```

---

## SaaS Platform Administration

The system needs two levels of administration:

1. Platform/System Admin dashboard
2. Client/Company Admin dashboard

### Platform/System Admin

This is for the SaaS owner/operator.

Platform admins manage:

- client companies
- subscription plans
- monthly/yearly billing
- invoices
- payments
- failed payment handling
- service status
- notices
- tenant provisioning
- client onboarding
- system-wide announcements
- support access
- compliance rule versions

### Client/Company Admin

This is for the HRM customer.

Company admins manage:

- company profile
- branches
- departments
- employees
- roles
- attendance
- leave policies
- payroll settings
- documents
- compliance settings

Platform admins should not be mixed with company admins.

---

## Multi-Tenant Client Initialization

Every client must be initialized as a tenant/company account.

### Platform Clients

```txt
platform_clients
- id
- client_code
- legal_name
- trade_name
- primary_contact_name
- primary_contact_email
- primary_contact_phone
- billing_email
- country
- emirate
- address_line_1
- address_line_2
- status
- onboarding_status
- service_status
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

Suggested `status` values:

```txt
lead
trial
active
suspended
terminated
cancelled
archived
```

Suggested `service_status` values:

```txt
provisioning
active
payment_past_due
grace_period
read_only
suspended
terminated
```

### Tenant Companies

The existing `companies` table should link to the SaaS client.

Add:

```txt
companies.platform_client_id
companies.subscription_status
companies.service_status
```

Rules:

- One platform client can have one or many companies.
- For MVP, assume one platform client has one company.
- The architecture should allow multi-company clients later.

### Tenant Provisioning Runs

```txt
tenant_provisioning_runs
- id
- platform_client_id
- company_id
- status
- started_at
- completed_at
- failed_at
- error_message
- provisioned_by
- provisioning_snapshot_json
- created_at
- updated_at
```

Provisioning should create:

```txt
company
default branch
default departments if configured
default roles
default permissions
company admin user
default leave policy
default payroll policy
default compliance settings
default onboarding templates
subscription record
```

### Client Initialization Flow

```txt
System admin creates platform client
        ↓
System admin selects subscription plan
        ↓
System enters billing contact and payment terms
        ↓
System provisions tenant/company
        ↓
System creates company admin user
        ↓
System sends onboarding invitation
        ↓
Company admin logs in
        ↓
Company admin completes company profile
        ↓
Company admin creates branches, departments, employees, and HR policies
```

---

## Subscription Billing

The SaaS platform must support recurring monthly and yearly fees.

### Subscription Plans

```txt
subscription_plans
- id
- name
- code
- billing_interval
- price
- currency
- employee_limit
- branch_limit
- included_modules_json
- is_active
- created_at
- updated_at
```

Suggested `billing_interval` values:

```txt
monthly
yearly
```

### Client Subscriptions

```txt
client_subscriptions
- id
- platform_client_id
- subscription_plan_id
- status
- billing_interval
- current_period_start
- current_period_end
- trial_ends_at
- grace_period_ends_at
- cancelled_at
- terminated_at
- auto_renew
- created_at
- updated_at
```

Suggested `status` values:

```txt
trialing
active
past_due
grace_period
read_only
suspended
cancelled
terminated
```

### Subscription Invoices

```txt
subscription_invoices
- id
- platform_client_id
- client_subscription_id
- invoice_number
- billing_period_start
- billing_period_end
- subtotal
- tax_amount
- discount_amount
- total
- currency
- due_date
- paid_at
- status
- created_at
- updated_at
```

Suggested `status` values:

```txt
draft
open
paid
past_due
void
uncollectible
refunded
```

### Subscription Payments

```txt
subscription_payments
- id
- platform_client_id
- subscription_invoice_id
- payment_provider
- provider_payment_id
- amount
- currency
- status
- paid_at
- failed_at
- failure_reason
- raw_provider_payload_json
- created_at
- updated_at
```

Suggested `status` values:

```txt
pending
succeeded
failed
refunded
cancelled
```

### Payment Methods

```txt
client_payment_methods
- id
- platform_client_id
- payment_provider
- provider_customer_id
- provider_payment_method_id
- brand
- last_four
- expiry_month
- expiry_year
- is_default
- status
- created_at
- updated_at
```

Important:

- Do not store raw card numbers.
- Use a payment provider token/customer ID.
- Keep payment logic isolated in a billing service.

---

## Failed Payment and Service Termination

Failure to pay should trigger a controlled dunning workflow.

Do not immediately delete the client data when a payment fails.

Recommended lifecycle:

```txt
active
        ↓ failed payment
past_due
        ↓ after configured reminder period
grace_period
        ↓ after grace period ends
read_only
        ↓ continued non-payment
suspended
        ↓ final notice period ends
terminated
```

### Dunning Settings

```txt
billing_dunning_settings
- id
- reminder_1_days_after_due
- reminder_2_days_after_due
- grace_period_days
- read_only_days_after_due
- suspension_days_after_due
- termination_days_after_due
- created_at
- updated_at
```

Suggested default:

```txt
Due date passed: mark invoice past_due
After 1 day: send first reminder
After 7 days: send second reminder
After 14 days: move to grace_period
After 21 days: move to read_only
After 30 days: suspend service
After 60 days: mark eligible for termination
```

The actual values should be configurable by the platform admin.

### Service Status Enforcement

Create middleware:

```txt
EnsureClientSubscriptionIsActive
```

Behavior:

```txt
active: full access
trialing: full access
past_due: full access with billing warning
grace_period: full access with stronger warning
read_only: view/export only, no create/update/delete
suspended: block company users from app except billing/payment page
terminated: block access; platform admin only
```

Important rules:

- Suspended clients should still be able to access the payment page.
- Platform admins must be able to reactivate clients manually.
- Termination should not instantly hard-delete data.
- Data retention rules must be configurable.
- Export-before-termination should be available if business policy allows it.
- Every service status change must be audit logged.

### Client Notices

```txt
client_notices
- id
- platform_client_id
- notice_type
- title
- message
- severity
- starts_at
- ends_at
- acknowledged_at
- acknowledged_by
- created_by
- created_at
- updated_at
```

Suggested `notice_type` values:

```txt
billing_reminder
payment_failed
service_grace_period
read_only_warning
suspension_notice
termination_notice
system_maintenance
legal_update
custom
```

Suggested `severity` values:

```txt
info
warning
critical
blocking
```

### Service Status History

```txt
client_service_status_history
- id
- platform_client_id
- old_status
- new_status
- reason
- changed_by
- changed_at
- metadata_json
- created_at
```

---

## Platform Admin Dashboard Screens

Build a separate dashboard for SaaS system admins.

Required screens:

```txt
Platform Admin > Overview
Platform Admin > Clients
Platform Admin > Client Details
Platform Admin > Create Client
Platform Admin > Subscription Plans
Platform Admin > Invoices
Platform Admin > Payments
Platform Admin > Failed Payments
Platform Admin > Notices
Platform Admin > Service Status
Platform Admin > Tenant Provisioning
Platform Admin > Compliance Rule Versions
```

Platform Admin overview should show:

```txt
active clients
trial clients
past due clients
suspended clients
monthly recurring revenue
yearly recurring revenue
open invoices
failed payments
upcoming renewals
clients needing attention
```

Client details should show:

```txt
company information
subscription status
service status
billing history
payment history
notices sent
branches count
employees count
last login activity
support notes
audit history
```

Required platform permissions:

```txt
platform.clients.view
platform.clients.create
platform.clients.update
platform.clients.suspend
platform.clients.terminate
platform.billing.view
platform.billing.update
platform.invoices.view
platform.payments.view
platform.notices.create
platform.notices.update
platform.provisioning.run
platform.compliance_rules.manage
```

---

## Billing Services

Create backend services:

```txt
App\Services\Billing\SubscriptionService
App\Services\Billing\InvoiceService
App\Services\Billing\PaymentService
App\Services\Billing\DunningService
App\Services\Billing\ServiceAccessService
```

Responsibilities:

- create subscriptions
- generate monthly/yearly invoices
- record payments
- handle payment provider webhooks
- detect failed payments
- move clients through dunning lifecycle
- enforce service status
- reactivate service after successful payment
- audit billing and service changes

---

## Codex Prompt 18: Attendance Correction Workflow

```txt
Build the attendance correction workflow.

Backend requirements:

- attendance_records table update
- attendance_correction_requests table
- attendance_correction_approval_steps table
- attendance_adjustments table
- correction request CRUD endpoints
- manager approval endpoint
- HR approval endpoint
- apply correction service
- payroll impact detection
- policy checks for locked payroll periods
- audit logs for every correction
- tests for employee request, manager approval, HR approval, locked-period correction, and payroll impact

Frontend requirements:

- employee attendance correction request form
- manager correction approval screen
- HR attendance correction screen
- original vs requested vs approved value comparison
- correction status badges
- payroll impact warning
- permission-aware actions

Rules:

- Do not silently overwrite original attendance values.
- Preserve old and new values.
- Lock records after payroll is processed.
- Require payroll manager approval for corrections affecting closed payroll.
```
```

---

## Codex Prompt 19: SaaS Platform Admin and Billing

```txt
Build the SaaS platform administration and billing foundation.

Backend requirements:

- platform_clients table
- tenant provisioning flow
- companies.platform_client_id
- subscription_plans table
- client_subscriptions table
- subscription_invoices table
- subscription_payments table
- client_payment_methods table
- billing_dunning_settings table
- client_notices table
- client_service_status_history table
- platform admin roles and permissions
- EnsureClientSubscriptionIsActive middleware
- SubscriptionService
- InvoiceService
- PaymentService
- DunningService
- ServiceAccessService
- audit logs for billing and service-status changes
- tests for tenant provisioning, invoice generation, failed payment lifecycle, suspension, read-only access, and reactivation

Frontend requirements:

- separate Platform Admin dashboard
- clients list
- create client flow
- client detail screen
- subscription plan management
- invoices screen
- payments screen
- failed payments screen
- notices screen
- service status controls
- tenant provisioning status screen

Rules:

- Platform admins are different from company admins.
- A client can be active, past_due, grace_period, read_only, suspended, or terminated.
- Failed payment should trigger reminders and status changes.
- Suspended clients should still be able to access billing/payment pages.
- Do not hard-delete tenant data immediately after termination.
```
```



---

## MoHRE Establishment and WPS Payroll Compliance

Payroll must not stop at internal payslip generation.

For UAE private-sector clients, the system must track MoHRE establishment details, labour file references, WPS provider setup, payroll due dates, wage transfer status, and proof/reference of salary transfer.

Important principle:

The HRM system is not the WPS provider itself unless a direct provider integration is built later. The system should first act as a compliance tracking, payroll preparation, export, reconciliation, and proof-storage layer.

The system should support:

- MoHRE establishment details
- establishment card reference
- labour file reference
- WPS provider selection
- payroll due date tracking
- salary file/export preparation
- wage transfer status tracking
- salary transfer proof/reference storage
- late payment warnings
- payroll compliance dashboard
- audit logs for payroll transfer status changes

---

## MoHRE Establishment Tables

### MoHRE Establishments

A client company may have one or more MoHRE establishment/labour file records.

```txt
mohre_establishments
- id
- company_id
- branch_id
- establishment_name
- mohre_establishment_number
- labour_file_number
- establishment_card_number
- trade_license_number
- emirate
- status
- issue_date
- expiry_date
- wps_required
- wps_exemption_reason
- notes
- created_by
- updated_by
- created_at
- updated_at
- deleted_at
```

Suggested `status` values:

```txt
active
inactive
expired
under_review
```

Rules:

- `company_id` is required.
- `branch_id` is nullable.
- A company can have multiple establishment/labour file records.
- Payroll periods should be linked to the correct MoHRE establishment where applicable.
- Expired establishment cards should appear in compliance reminders.

### Employee MoHRE Identifiers

Recommended separate table:

```txt
employee_government_profiles
- id
- company_id
- employee_id
- mohre_establishment_id
- labour_card_number
- work_permit_number
- person_code
- emirates_id_number_encrypted
- visa_file_number
- passport_number_encrypted
- wps_employee_identifier
- created_by
- updated_by
- created_at
- updated_at
```

Rules:

- Sensitive identifiers should be encrypted where appropriate.
- Access must be permission-protected.
- Changes must be audit logged.

---

## WPS Provider Setup

### WPS Providers

Seed known/approved provider options as reference data, but allow platform admins to maintain the list.

```txt
wps_providers
- id
- name
- provider_type
- website
- contact_phone
- contact_email
- integration_type
- status
- created_at
- updated_at
```

Suggested `provider_type` values:

```txt
bank
exchange_house
financial_institution
digital_wallet
other
```

Suggested `integration_type` values:

```txt
manual_upload
file_export
api
```

### Company WPS Settings

```txt
company_wps_settings
- id
- company_id
- mohre_establishment_id
- wps_provider_id
- payroll_due_day
- salary_period_type
- payment_currency
- sif_export_enabled
- provider_portal_url
- provider_customer_reference
- auto_mark_paid_allowed
- status
- created_by
- updated_by
- created_at
- updated_at
```

Suggested `salary_period_type` values:

```txt
monthly
weekly
biweekly
custom
```

Rules:

- Company admins should select the WPS provider used for salary transfers.
- Payroll due date must be stored per company or establishment.
- System should warn when payroll is approaching due date.
- System should warn when wage transfer proof is missing.
- System should warn when wage transfer status is still not confirmed after due date.

---

## Payroll Period WPS Fields

Extend payroll periods:

```txt
payroll_periods
- id
- company_id
- mohre_establishment_id
- period_start
- period_end
- payroll_due_date
- processing_status
- approval_status
- wps_status
- wps_provider_id
- salary_transfer_batch_id
- locked_at
- locked_by
- created_at
- updated_at
```

Suggested `processing_status` values:

```txt
draft
calculated
reviewed
approved
locked
cancelled
```

Suggested `wps_status` values:

```txt
not_required
not_started
file_generated
submitted_to_provider
accepted_by_provider
rejected_by_provider
processing
paid
partially_paid
failed
late
manual_override
```

Rules:

- Payroll should not be considered fully complete until salary transfer status is confirmed or manually overridden by an authorized payroll admin.
- If payroll is due and WPS status is not `paid`, `accepted_by_provider`, or equivalent, show compliance warning.
- Status changes must be audit logged.

---

## Salary Transfer Batches

A salary transfer batch represents one WPS/provider submission for one payroll period.

```txt
salary_transfer_batches
- id
- company_id
- payroll_period_id
- mohre_establishment_id
- wps_provider_id
- batch_number
- provider_reference
- salary_file_path
- salary_file_hash
- total_employees
- total_amount
- currency
- status
- generated_at
- submitted_at
- accepted_at
- rejected_at
- paid_at
- failure_reason
- created_by
- updated_by
- created_at
- updated_at
```

Suggested `status` values:

```txt
draft
generated
submitted
accepted
rejected
processing
paid
partially_paid
failed
cancelled
```

Rules:

- Store the generated salary file path if the app produces a file for provider upload.
- Store file hash for audit integrity.
- Store provider reference once submitted.
- Do not store raw bank card data.
- Bank account details, if stored, must be encrypted and permission-protected.

---

## Salary Transfer Records

Each employee payment inside a transfer batch should have its own record.

```txt
salary_transfer_records
- id
- company_id
- salary_transfer_batch_id
- payroll_period_id
- payslip_id
- employee_id
- wps_employee_identifier
- employee_bank_name
- employee_iban_encrypted
- salary_amount
- currency
- provider_employee_reference
- provider_transaction_reference
- status
- paid_at
- failure_reason
- created_at
- updated_at
```

Suggested `status` values:

```txt
pending
submitted
accepted
paid
failed
rejected
held
```

Rules:

- Use encryption for bank/account identifiers.
- Payroll managers can view payment status.
- Regular HR users should not automatically see bank/payment details unless permitted.
- Failed employee-level transfers should appear in payroll warnings.

---

## WPS Transfer Proofs

Store proof/reference of salary transfer through the chosen provider.

```txt
wps_transfer_proofs
- id
- company_id
- salary_transfer_batch_id
- payroll_period_id
- wps_provider_id
- proof_type
- provider_reference
- transaction_reference
- proof_file_path
- proof_file_hash
- uploaded_by
- verified_by
- verified_at
- status
- notes
- created_at
- updated_at
```

Suggested `proof_type` values:

```txt
provider_receipt
bank_confirmation
exchange_house_receipt
wps_report
manual_reference
api_confirmation
```

Suggested `status` values:

```txt
uploaded
verified
rejected
needs_review
```

Rules:

- Payroll should show proof missing until at least one proof/reference is attached or received through integration.
- Proof uploads must be audit logged.
- Proof files must be permission-protected.
- The system should store a provider reference even if no file is uploaded.

---

## Payroll Due Date and Late Wage Monitoring

Create a backend service:

```txt
App\Services\Payroll\WpsComplianceService
```

Responsibilities:

- determine payroll due date
- check whether salary transfer has been generated
- check whether transfer was submitted to the provider
- check whether transfer has been accepted/paid
- detect missing proof/reference
- mark payroll as late when applicable
- create notices/reminders for company admins
- create platform admin alerts for risky clients if needed

Default behavior:

```txt
Before due date:
- show upcoming payroll due reminder

On due date:
- show payroll due today warning if transfer is not submitted

After due date:
- show overdue payroll warning if transfer is not confirmed

After configured late threshold:
- mark wage transfer status as late or needs_review
```

Important:

- The system should track due dates based on company settings and employment contracts.
- The system should allow manual override only with permission and reason.
- Manual overrides must be audit logged.
- If direct API integration with WPS providers is unavailable, the app should still support manual proof upload and provider reference entry.

---

## WPS Payroll Workflow

```txt
Company admin enters MoHRE establishment / labour file details
        ↓
Company admin selects WPS provider
        ↓
Company admin sets payroll due date policy
        ↓
Payroll manager runs payroll
        ↓
System calculates payslips
        ↓
Payroll manager reviews and approves payroll
        ↓
System generates salary transfer batch / WPS export if enabled
        ↓
Payroll manager submits salary file through chosen provider
        ↓
Payroll manager records provider reference or uploads proof
        ↓
System updates wage transfer status
        ↓
System verifies proof manually or through provider integration
        ↓
Payroll period is marked paid/completed
```

---

## Required Payroll Compliance Screens

Build frontend screens for:

```txt
Company Settings > MoHRE Establishments
Company Settings > WPS Provider
Payroll > Payroll Period Detail
Payroll > Salary Transfer Batch
Payroll > Upload Transfer Proof
Payroll > WPS Compliance Status
Platform Admin > Clients > WPS/Payroll Risk
```

Payroll period detail should show:

```txt
MoHRE establishment
labour file reference
chosen WPS provider
payroll due date
wage transfer status
provider reference
proof upload status
late payment warning
employee-level failed transfers
```

Required permissions:

```txt
mohre_establishments.view
mohre_establishments.create
mohre_establishments.update
wps_settings.view
wps_settings.update
salary_transfers.view
salary_transfers.generate
salary_transfers.submit
salary_transfers.update_status
salary_transfers.upload_proof
salary_transfers.verify_proof
salary_transfers.manual_override
```

---

## Codex Prompt 20: MoHRE and WPS Payroll Compliance

```txt
Build the MoHRE establishment and WPS payroll compliance module.

Backend requirements:

- mohre_establishments table
- employee_government_profiles table
- wps_providers table
- company_wps_settings table
- payroll_periods WPS fields
- salary_transfer_batches table
- salary_transfer_records table
- wps_transfer_proofs table
- WpsComplianceService
- salary transfer status lifecycle
- transfer proof upload endpoint
- provider reference tracking
- payroll due date warnings
- late wage monitoring
- audit logs for all status/proof changes
- permissions for MoHRE/WPS/payroll transfer actions
- tests for payroll due date, missing proof, late status, proof upload, and manual override

Frontend requirements:

- Company Settings > MoHRE Establishments screen
- Company Settings > WPS Provider screen
- Payroll Period WPS status panel
- Salary Transfer Batch screen
- Upload Transfer Proof screen
- WPS Compliance Status dashboard
- Platform Admin payroll-risk view

Rules:

- The app should not pretend to be a WPS provider unless direct integration exists.
- Track chosen WPS provider and provider references.
- Store proof/reference of salary transfer.
- Warn about missing or late salary transfer confirmation.
- Do not store raw card data.
- Encrypt sensitive employee government and bank identifiers.
```
```



---

## Numbered Feature Flows and Developer Comments

Every major feature must include a numbered flow so developers and AI agents can understand the exact process before writing code.

For each feature, add flow comments in the relevant documentation and, where helpful, inside service classes.

Required format:

```txt
Feature: Employee Onboarding

1. HR creates a draft employee profile.
2. HR assigns branch, department, job title, and manager.
3. System assigns onboarding template.
4. System generates onboarding tasks.
5. Employee receives invitation if self-service is enabled.
6. Employee uploads required documents.
7. HR verifies documents and employee details.
8. Payroll manager sets salary components.
9. Admin creates login access if needed.
10. HR completes onboarding.
11. System changes employee status to active.
```

### Code Comment Standard

Backend service classes should include short numbered comments for complex workflows.

Example:

```php
// 1. Load employee and company policy.
// 2. Validate employee eligibility.
// 3. Calculate entitlement.
// 4. Split amount into payroll items.
// 5. Store calculation snapshot.
// 6. Return calculation result.
```

Frontend pages with multi-step flows should include comments around major UI stages.

Example:

```ts
// 1. Load employee profile.
// 2. Load onboarding task list.
// 3. Submit updated employee details.
// 4. Upload required documents.
// 5. Mark task as completed.
// 6. Refresh onboarding status.
```

Rules:

- Do not over-comment obvious single-line code.
- Comment business workflows, approval steps, calculations, and state transitions.
- Keep comments accurate when code changes.
- Every workflow that affects payroll, leave, compliance, billing, or employee status must have a numbered flow.
- Documentation should explain the business process before implementation details.

---

## Numbered Core Feature Flows

### 1. Platform Client Creation Flow

```txt
1. Platform admin opens Create Client screen.
2. Platform admin enters legal name, trade name, billing contact, country, emirate, and primary contact.
3. Platform admin selects subscription plan.
4. System validates required client and billing fields.
5. System creates platform_client record.
6. System creates client_subscription record.
7. System starts tenant_provisioning_run.
8. System creates company record linked to platform_client.
9. System creates default branch.
10. System creates default roles and permissions.
11. System creates company admin user.
12. System creates default compliance, leave, payroll, and WPS settings.
13. System sends onboarding invitation to company admin.
14. System marks tenant_provisioning_run as completed.
15. Platform admin sees client status as active or trialing.
```

Developer notes:

- This flow belongs to Platform Admin, not Company Admin.
- Tenant provisioning must be idempotent where possible.
- Failed provisioning should be resumable.
- Every provisioning run should store a provisioning snapshot.

---

### 2. Company Initialization Flow

```txt
1. Company admin accepts invitation.
2. Company admin creates password and logs in.
3. System loads company setup checklist.
4. Company admin confirms company profile.
5. Company admin creates or confirms branches.
6. Company admin creates departments.
7. Company admin creates job titles.
8. Company admin configures leave policies.
9. Company admin configures payroll settings.
10. Company admin enters MoHRE establishment details.
11. Company admin selects WPS provider.
12. Company admin sets payroll due date.
13. Company admin invites HR/payroll/admin users.
14. System marks company onboarding as completed.
```

Developer notes:

- The setup checklist should be stored as state, not just frontend UI.
- Incomplete setup should show dashboard warnings.
- Payroll should not run until minimum payroll setup is complete.

---

### 3. Authentication and Role Loading Flow

```txt
1. User opens login page.
2. Frontend requests CSRF cookie if using Sanctum cookie auth.
3. User submits email and password.
4. Backend validates credentials.
5. Backend checks user status.
6. Backend checks client service status.
7. Backend creates authenticated session/token.
8. Frontend calls /api/me.
9. Backend returns user, company, roles, permissions, and service status.
10. Frontend stores auth state in Pinia.
11. Frontend builds permission-aware navigation.
12. User is redirected to correct dashboard.
```

Developer notes:

- Platform admins and company users should be routed to different dashboards.
- Backend authorization is mandatory even if frontend hides menu items.
- Suspended clients should only access allowed billing/payment routes.

---

### 4. Branch and Department Setup Flow

```txt
1. Company admin opens Branches screen.
2. Company admin creates branch with emirate, city, address, and manager.
3. System validates branch code uniqueness within company.
4. System creates branch.
5. Company admin opens Departments screen.
6. Company admin creates department and optionally links it to branch.
7. Company admin assigns department manager.
8. System updates reporting and approval scope.
9. Branch and department become available in employee onboarding.
```

Developer notes:

- Branch filters should apply to employees, attendance, payroll, and reports.
- Department managers should only access employees in their scope unless given wider permissions.

---

### 5. Role and Permission Assignment Flow

```txt
1. Company admin opens Users and Roles screen.
2. Company admin selects a user.
3. Company admin selects role.
4. Company admin selects scope: company, branch, department, or self.
5. System validates that the role can be assigned by the current user.
6. System creates user_roles record.
7. System refreshes permission cache if used.
8. System audit logs the role assignment.
9. User receives updated permissions on next /api/me refresh or next login.
```

Developer notes:

- Use `user_roles` as the role assignment pivot.
- Always check company_id, branch_id, department_id, and scope during authorization.
- Role changes must be audit logged.

---

### 6. Employee Onboarding Flow

```txt
1. HR creates draft employee profile.
2. HR enters personal details.
3. HR selects company, branch, department, job title, and manager.
4. HR enters employment contract details.
5. HR selects onboarding template.
6. System creates employee_onboarding_case.
7. System generates employee_onboarding_tasks from template.
8. System sends employee invitation if self-service is enabled.
9. Employee completes profile fields if allowed.
10. Employee uploads required documents.
11. HR reviews documents and personal details.
12. Payroll manager sets salary components.
13. Admin creates user login if employee needs portal access.
14. HR completes all required onboarding tasks.
15. System changes employee status from onboarding to active.
16. System audit logs activation.
```

Developer notes:

- Employee can exist without a login user.
- Payroll and attendance should usually start only after employee status is active.
- Required onboarding tasks must be completed before activation unless an authorized user overrides with reason.

---

### 7. Employee Document Upload Flow

```txt
1. Employee or HR opens employee document section.
2. User selects document type.
3. User uploads file.
4. Backend validates file type, size, and permission.
5. Backend stores file securely.
6. Backend creates employee_documents record.
7. System calculates expiry status if expiry date exists.
8. HR verifies document if required.
9. System shows document in employee profile.
10. System creates reminder if document is expiring soon.
11. System audit logs upload and verification.
```

Developer notes:

- Passport, Emirates ID, visa, labour card, and contract documents are sensitive.
- Downloads must be protected by backend authorization.
- Expiry reminders should appear on dashboard.

---

### 8. Attendance Check-In/Check-Out Flow

```txt
1. Employee opens attendance screen.
2. System checks employee status.
3. System checks whether attendance is allowed for current date.
4. Employee checks in.
5. System records check_in_at, source, device, and optional location.
6. Employee checks out.
7. System records check_out_at.
8. System calculates total work minutes, break minutes, late minutes, and overtime minutes.
9. System sets attendance status.
10. Manager/HR can review attendance.
11. Payroll uses approved or locked attendance data.
```

Developer notes:

- Do not trust frontend time blindly for sensitive attendance.
- Attendance records should support web, mobile, biometric, import, and manual sources.
- Payroll periods should lock related attendance records.

---

### 9. Attendance Correction Flow

```txt
1. Employee notices missing or incorrect attendance.
2. Employee opens correction request form.
3. Employee selects attendance date and correction type.
4. Employee enters requested check-in/check-out/status change.
5. Employee submits reason and optional attachment.
6. System creates attendance_correction_request.
7. Manager reviews request.
8. HR reviews request if policy requires HR approval.
9. System compares original values with requested values.
10. System applies approved correction.
11. System creates attendance_adjustment with old and new values.
12. System updates attendance record status to corrected.
13. System checks payroll impact.
14. If payroll is open, payroll recalculation can happen.
15. If payroll is locked, correction is marked for payroll adjustment or payroll manager review.
16. System audit logs all actions.
```

Developer notes:

- Never silently overwrite attendance history.
- Original values must remain recoverable.
- Corrections affecting locked payroll need stronger approval.

---

### 10. Leave Request Flow

```txt
1. Employee opens leave request form.
2. System loads leave types and balances.
3. Employee selects leave type and date range.
4. System calculates total days and working days.
5. System checks leave eligibility.
6. System checks available balance.
7. System checks required documents.
8. Employee submits request.
9. Manager reviews request.
10. HR reviews request if required.
11. System approves or rejects leave.
12. System updates leave balance.
13. System creates payroll impact records if leave affects pay.
14. System updates attendance status for approved leave dates.
15. System audit logs decision.
```

Developer notes:

- Leave approval should be policy-driven.
- Sick leave, maternity leave, annual leave, and unpaid leave may have different calculation rules.
- Leave balances should be auditable.

---

### 11. Annual Leave/Vacation Policy Flow

```txt
1. Company admin opens Leave Policies screen.
2. Admin selects annual leave policy.
3. System loads UAE legal minimum rule set.
4. Admin configures entitlement after one year.
5. Admin configures entitlement after six months.
6. Admin configures accrual method.
7. Admin configures carry-forward rules.
8. Admin configures encashment basis.
9. Backend validates policy against legal minimums.
10. If below minimum, backend rejects save.
11. If compliant, backend saves policy.
12. System audit logs policy change.
13. Future leave calculations use the new effective policy.
```

Developer notes:

- Admin can make policy more generous.
- Admin cannot save policy below UAE legal minimum.
- Legal rules must be versioned by effective date.

---

### 12. Sick Leave Calculation Flow

```txt
1. Employee submits sick leave request.
2. System checks employee probation status.
3. System checks whether medical certificate is required.
4. System counts sick leave already used in current leave year.
5. System loads sick leave legal/company policy.
6. System splits requested days into full-pay, half-pay, and unpaid tiers.
7. System creates leave_pay_calculation_items.
8. System stores rule_snapshot_json.
9. Payroll reads leave pay calculation items.
10. Payroll applies no deduction, half deduction, or full deduction as required.
11. System audit logs calculation and approval.
```

Developer notes:

- UAE default: first 15 days full pay, next 30 days half pay, remaining 45 days unpaid after probation.
- Do not hardcode values in frontend.
- Store calculation snapshots for auditability.

---

### 13. Payroll Run Flow

```txt
1. Payroll manager opens payroll period.
2. System loads active employees for company/branch/establishment.
3. System loads salary components.
4. System loads approved attendance.
5. System loads approved leave and leave pay calculation items.
6. System calculates gross pay.
7. System calculates deductions.
8. System calculates allowances.
9. System calculates net pay.
10. System creates draft payslips.
11. Payroll manager reviews exceptions.
12. Payroll manager approves payroll.
13. System locks payroll period.
14. System prepares salary transfer batch if WPS is required.
15. System audit logs payroll approval.
```

Developer notes:

- Payroll calculation should happen in backend services.
- Payslip values should be snapshotted.
- Locked payroll should not change without adjustment workflow.

---

### 14. MoHRE and WPS Payroll Compliance Flow

```txt
1. Company admin enters MoHRE establishment details.
2. Company admin enters establishment card and labour file references.
3. Company admin selects WPS provider.
4. Company admin sets payroll due date.
5. Payroll manager runs payroll.
6. System generates payslips.
7. Payroll manager approves payroll.
8. System creates salary transfer batch.
9. System generates salary transfer file if enabled.
10. Payroll manager submits salary transfer through chosen WPS provider.
11. Payroll manager records provider reference.
12. Payroll manager uploads proof or WPS report.
13. System updates wage transfer status.
14. System checks whether proof/reference is missing.
15. System checks whether payroll is late.
16. System marks payroll as paid, failed, partially paid, late, or needs review.
17. System audit logs all WPS status changes.
```

Developer notes:

- The app is a compliance tracking layer unless direct WPS provider API integration is built.
- Proof/reference is required for compliance trail.
- Sensitive employee bank/government identifiers must be encrypted.

---

### 15. Emiratisation Monitoring Flow

```txt
1. System loads company Emiratisation settings.
2. System checks company size and applicable sector.
3. System counts active employees.
4. System counts skilled workers.
5. System counts active UAE citizens.
6. System counts UAE citizens in skilled roles.
7. System loads applicable Emiratisation rule version.
8. System calculates required UAE citizen count.
9. System compares required count against actual count.
10. System calculates missing count.
11. System estimates contribution exposure if configured.
12. System creates emiratisation_snapshot.
13. Dashboard shows compliant, at risk, non-compliant, not applicable, or needs review.
14. Authorized admin can mark needs_review with reason.
```

Developer notes:

- Treat result as compliance guidance, not legal certification.
- Rule values must be configurable by effective date.
- Keep historical snapshots.

---

### 16. Subscription Billing Flow

```txt
1. Platform admin creates or selects client.
2. Platform admin assigns subscription plan.
3. System creates client_subscription.
4. System generates invoice for monthly or yearly period.
5. System sends invoice/payment notice.
6. Client pays invoice.
7. System records subscription_payment.
8. System marks invoice as paid.
9. System keeps client service_status active.
10. System renews subscription on next billing period.
```

Developer notes:

- Billing should be separate from HR/payroll.
- Do not store raw card data.
- Use provider customer/payment method references.

---

### 17. Failed Payment and Service Suspension Flow

```txt
1. Invoice due date passes unpaid.
2. System marks invoice as past_due.
3. System sends first payment reminder.
4. System updates client service_status to payment_past_due.
5. After configured days, system sends second reminder.
6. System moves client to grace_period.
7. If still unpaid, system moves client to read_only.
8. If still unpaid, system moves client to suspended.
9. Suspended client can access billing/payment page only.
10. Platform admin can manually reactivate or extend grace period.
11. If payment succeeds, system restores active service.
12. If final notice period ends, system marks client terminated.
13. System keeps data according to retention policy.
14. Every status change is audit logged.
```

Developer notes:

- Do not instantly delete client data.
- Read-only mode should block create/update/delete actions.
- Suspension middleware must enforce service status on backend.

---

### 18. Client Notice Flow

```txt
1. Platform admin creates notice.
2. Admin selects client or client group.
3. Admin selects notice type and severity.
4. Admin sets start and end date.
5. System publishes notice.
6. Company users see notice in dashboard.
7. Blocking notices require acknowledgement or action.
8. System stores acknowledgement.
9. Platform admin can review notice history.
```

Developer notes:

- Notices support billing, legal updates, maintenance, suspension, and custom messages.
- Critical notices should be visible before normal dashboard content.

---

### 19. Compliance Settings Change Flow

```txt
1. Company admin opens compliance settings.
2. System loads legal rule set and company policy.
3. Admin changes policy value.
4. Backend validates value against legal minimum.
5. Backend rejects non-compliant value.
6. Backend saves compliant value.
7. System stores effective date.
8. System audit logs old value and new value.
9. Future calculations use new policy.
10. Previous calculations keep their original rule snapshots.
```

Developer notes:

- Never overwrite historical calculation assumptions.
- Use rule snapshots for payroll, leave, gratuity, and compliance outputs.

---

### 20. Audit Log Flow

```txt
1. User performs sensitive action.
2. Backend identifies actor, company, branch, and permission.
3. Backend captures old values if record existed.
4. Backend applies change.
5. Backend captures new values.
6. Backend creates audit_logs record.
7. Audit log stores IP address and user agent where available.
8. Authorized users can view audit log.
9. Audit log cannot be edited by normal users.
```

Developer notes:

- Audit logs are required for salary, payroll, documents, roles, attendance corrections, compliance settings, billing, and service status.
- Audit logs should not be soft deleted.

---

### 21. Dashboard and Report Flow

```txt
1. User opens dashboard.
2. Backend checks user role and scope.
3. Backend loads only permitted company/branch/department data.
4. Backend calculates summary cards.
5. Backend returns pending approvals.
6. Backend returns expiring documents.
7. Backend returns attendance/payroll/compliance warnings.
8. Frontend displays dashboard widgets.
9. User clicks widget to open detailed screen.
10. Detailed screen applies same backend authorization rules.
```

Developer notes:

- Dashboard data must be permission-aware.
- Do not expose payroll or salary data to unauthorized roles.
- Platform admin dashboard and company dashboard must remain separate.

---

## Codex Prompt 21: Add Numbered Flow Comments to Every Feature

```txt
Review the project documentation and code.

For every major module, add numbered business-flow comments and documentation.

Modules that require numbered flows:

- platform client creation
- company initialization
- authentication
- roles and permissions
- branches and departments
- employee onboarding
- employee documents
- attendance
- attendance correction
- leave request
- annual leave policy
- sick leave calculation
- payroll run
- MoHRE/WPS payroll compliance
- Emiratisation monitoring
- subscription billing
- failed payment and service suspension
- client notices
- compliance settings
- audit logs
- dashboard and reports

Rules:

- Add numbered flows in docs first.
- Add short numbered comments in backend service classes for complex workflows.
- Add short comments in frontend pages for multi-step UI flows.
- Do not over-comment obvious code.
- Keep comments synchronized with implementation.
- Make flows easy for a new developer to follow.
```
```



---

## Employee Self-Service Portal

The system must support employee self-service.

Important principle:

Employees should be able to perform simple HR actions themselves, but HR, managers, payroll, and company admins must control what is editable, approvable, and visible.

Employee self-service should support:

- completing onboarding profile fields
- uploading onboarding documents
- acknowledging policies
- requesting leave
- viewing leave balances
- submitting attendance correction requests
- viewing attendance history
- viewing payslips
- downloading approved documents
- updating limited personal information if company policy allows
- viewing company notices
- viewing assigned tasks

Employee self-service must not allow:

- changing salary
- changing job title
- changing branch or department
- changing manager
- activating themselves
- approving their own leave
- approving their own attendance correction
- viewing other employees unless given manager permissions
- deleting verified documents
- changing compliance/payroll/company settings

### Employee Portal Permissions

```txt
employee_portal.profile.view_self
employee_portal.profile.update_limited_self
employee_portal.documents.upload_self
employee_portal.documents.view_self
employee_portal.leave.request_self
employee_portal.leave.view_self
employee_portal.attendance.view_self
employee_portal.attendance_correction.request_self
employee_portal.payslips.view_self
employee_portal.notices.view_self
employee_portal.tasks.view_self
employee_portal.tasks.complete_self
```

### Employee Editable Fields

Company admins should be able to configure which fields employees can edit.

```txt
employee_self_service_settings
- id
- company_id
- allow_profile_self_update
- allowed_profile_fields_json
- require_hr_approval_for_profile_changes
- allow_document_self_upload
- allow_leave_self_request
- allow_attendance_correction_self_request
- allow_payslip_self_view
- allow_policy_acknowledgement
- created_by
- updated_by
- created_at
- updated_at
```

Suggested employee-editable fields:

```txt
personal_email
phone
emergency_contact_name
emergency_contact_phone
address
marital_status
dependents_count
bank_account_update_request
```

Sensitive updates should create a request instead of immediately changing the employee profile.

```txt
employee_profile_change_requests
- id
- company_id
- employee_id
- field_name
- old_value_json
- requested_value_json
- reason
- status
- requested_by
- reviewed_by
- reviewed_at
- rejection_reason
- created_at
- updated_at
```

Suggested `status` values:

```txt
submitted
approved
rejected
cancelled
applied
```

---

## Employee Self-Service Leave Request Flow

```txt
1. Employee logs into employee portal.
2. Employee opens Leave Request screen.
3. System loads employee leave balances.
4. System loads leave types available to the employee.
5. Employee selects leave type.
6. Employee selects start date and end date.
7. System calculates total days and working days.
8. System checks eligibility and available balance.
9. System checks whether document upload is required.
10. Employee submits request.
11. System creates leave_request with status submitted.
12. Manager receives approval task.
13. HR receives approval task if policy requires HR approval.
14. System approves or rejects leave.
15. If approved, system updates leave balance.
16. If approved, system updates attendance calendar for leave dates.
17. If leave affects pay, system creates payroll calculation items.
18. Employee receives approval/rejection notification.
19. System audit logs the request and decision.
```

Developer notes:

- Employee can request leave for themselves only.
- Manager and HR approval rules should be policy-driven.
- Backend must validate eligibility and balance.
- Frontend should show balance preview before submission.

---

## Employee Self-Service Onboarding Flow

```txt
1. HR creates draft employee record.
2. HR chooses whether self-service onboarding is enabled.
3. System creates employee_onboarding_case.
4. System generates onboarding tasks.
5. System sends secure invitation to employee.
6. Employee accepts invitation.
7. Employee creates login password or one-time access session.
8. Employee completes allowed profile fields.
9. Employee uploads required documents.
10. Employee acknowledges policies if required.
11. System marks employee-side tasks completed.
12. HR reviews submitted details and documents.
13. Payroll manager sets salary and payment details.
14. HR completes final onboarding checklist.
15. System activates employee.
16. Employee portal access remains active based on company policy.
```

Developer notes:

- Employee cannot activate themselves.
- HR controls final verification.
- Employee uploaded documents should be stored in secure company-isolated storage.
- Invitation tokens must be hashed and expire.

---

## Document and Image Storage Architecture

The system must store company documents and images in secure object storage, not inside the application server filesystem.

Use an object storage provider similar to:

```txt
Amazon S3
Azure Blob Storage
Oracle Cloud Object Storage
Cloudflare R2
Backblaze B2
Wasabi
MinIO for local development
```

Important principle:

Use a storage abstraction layer so the provider can be changed later.

Laravel should use a filesystem disk abstraction. The database should store metadata and object keys, not raw files.

For UAE and Middle East performance, prefer a provider with a UAE or nearby Middle East region for private HR documents. Public assets may use a CDN.

Recommended MVP approach:

```txt
Production private HR documents: AWS S3 Middle East UAE or Azure Blob Storage UAE North
Public/static assets: CDN-backed object storage
Local development: MinIO
Backup/cross-region copy: optional second provider or second region
```

### Storage Provider Table

```txt
storage_providers
- id
- name
- provider_type
- region
- endpoint_url
- supports_s3_api
- supports_private_buckets
- supports_cdn
- supports_encryption
- status
- created_at
- updated_at
```

Suggested `provider_type` values:

```txt
aws_s3
azure_blob
oracle_object_storage
cloudflare_r2
backblaze_b2
wasabi
minio
local
```

### Company Storage Buckets

Each company should have isolated storage configuration.

```txt
company_storage_buckets
- id
- company_id
- storage_provider_id
- bucket_name
- bucket_region
- bucket_prefix
- visibility
- encryption_mode
- cdn_domain
- is_default
- status
- created_by
- updated_by
- created_at
- updated_at
```

Suggested `visibility` values:

```txt
private
public
internal
```

Suggested `encryption_mode` values:

```txt
provider_managed
customer_managed_key
application_level_encryption
```

Rules:

- HR documents must use private storage.
- Public company logos may use public/CDN storage.
- Each company should have its own bucket or at minimum a strict prefix.
- Company data must never be mixed without tenant isolation.
- Direct public URLs must not be used for private documents.
- Use signed URLs for temporary access.
- Store provider object keys, not permanent public links.
- All downloads must go through backend authorization.
- File uploads must validate MIME type, extension, file size, and malware scanning status if scanning is enabled.

### File Objects Table

All uploaded files should be tracked centrally.

```txt
file_objects
- id
- company_id
- uploaded_by
- storage_provider_id
- company_storage_bucket_id
- disk
- bucket_name
- object_key
- original_filename
- stored_filename
- mime_type
- extension
- size_bytes
- checksum_sha256
- visibility
- file_category
- scan_status
- encryption_mode
- metadata_json
- created_at
- updated_at
- deleted_at
```

Suggested `file_category` values:

```txt
employee_document
profile_image
company_logo
attendance_attachment
leave_attachment
wps_transfer_proof
contract
policy_document
system_notice_attachment
```

Suggested `scan_status` values:

```txt
not_scanned
pending
clean
infected
failed
skipped
```

### Employee Documents

Employee document rows should reference `file_objects`.

```txt
employee_documents
- id
- company_id
- employee_id
- file_object_id
- document_type
- document_number_encrypted
- issue_date
- expiry_date
- verified_status
- verified_by
- verified_at
- notes
- created_at
- updated_at
- deleted_at
```

### Profile Images

```txt
employee_profile_images
- id
- company_id
- employee_id
- file_object_id
- status
- uploaded_by
- approved_by
- approved_at
- created_at
- updated_at
```

Rules:

- Profile image uploads may require HR approval.
- Images should be resized/optimized into variants.
- Store original and processed variants as separate file_objects or metadata references.

### File Access Logs

```txt
file_access_logs
- id
- company_id
- file_object_id
- accessed_by
- action
- ip_address
- user_agent
- created_at
```

Suggested `action` values:

```txt
upload
download
preview
delete
restore
signed_url_generated
```

Rules:

- Access to passports, Emirates ID, visas, contracts, salary documents, and WPS proofs must be logged.
- File access logs should not be editable by normal users.

---

## Storage Upload Flow

```txt
1. User opens document upload screen.
2. Frontend requests allowed document types and file limits.
3. User selects file.
4. Frontend performs basic file size/type validation.
5. Frontend sends file to backend upload endpoint.
6. Backend checks authentication and authorization.
7. Backend validates company scope.
8. Backend validates MIME type, extension, and size.
9. Backend chooses company storage bucket.
10. Backend generates safe object key.
11. Backend uploads file to object storage.
12. Backend stores file_objects metadata.
13. Backend links file_object to employee document, leave request, WPS proof, or other business record.
14. Backend creates audit log.
15. Backend returns file metadata, not raw storage credentials.
```

Developer notes:

- Never expose permanent private object URLs.
- For large files, use controlled pre-signed upload flow if needed.
- Pre-signed uploads must still create a pending file record and be finalized after upload.
- Use UUID-based object keys, not original filenames.

---

## Storage Download/Preview Flow

```txt
1. User clicks download or preview.
2. Frontend requests file access from backend.
3. Backend checks user permission and tenant scope.
4. Backend checks whether file belongs to the user's company.
5. Backend records file access log.
6. Backend returns temporary signed URL or streams file.
7. Signed URL expires quickly.
8. User downloads or previews file.
```

Developer notes:

- Private documents should never be served from public buckets.
- Signed URLs should be short-lived.
- Salary and government documents require stricter permission checks.

---

## Company Storage Provisioning Flow

```txt
1. Platform admin creates client.
2. Tenant provisioning starts.
3. System selects default storage provider and region.
4. System creates company bucket or company prefix.
5. System creates company_storage_buckets record.
6. System tests write/read/delete permissions.
7. System stores storage configuration.
8. System creates default folders/prefixes if needed.
9. System marks storage provisioning completed.
10. Company can now upload documents and images.
```

Suggested object key structure:

```txt
companies/{company_id}/employees/{employee_id}/documents/{uuid}.{extension}
companies/{company_id}/employees/{employee_id}/profile-images/{uuid}.{extension}
companies/{company_id}/attendance/{attendance_record_id}/attachments/{uuid}.{extension}
companies/{company_id}/leave/{leave_request_id}/attachments/{uuid}.{extension}
companies/{company_id}/payroll/{payroll_period_id}/wps-proofs/{uuid}.{extension}
companies/{company_id}/company-assets/logos/{uuid}.{extension}
```

Developer notes:

- If using one bucket for all companies, strict prefix isolation is mandatory.
- If using one bucket per company, provisioning needs provider API permissions.
- Bucket-per-company gives cleaner isolation but can be operationally heavier.
- Prefix-per-company is easier but requires stronger application-level authorization.

---

## Codex Prompt 22: Employee Self-Service and Company File Storage

```txt
Build employee self-service and secure company file storage.

Backend requirements:

- employee_self_service_settings table
- employee_profile_change_requests table
- storage_providers table
- company_storage_buckets table
- file_objects table
- employee_profile_images table
- file_access_logs table
- update employee_documents to reference file_object_id
- employee self-service permissions
- secure upload endpoint
- secure download/preview endpoint
- signed URL generation
- company bucket/prefix resolution
- file metadata tracking
- file access logging
- tests for tenant isolation, upload authorization, download authorization, signed URL expiry, and employee self-service restrictions

Frontend requirements:

- Employee Portal dashboard
- employee leave request screen
- employee onboarding task screen
- employee document upload screen
- employee attendance correction request screen
- employee payslip view screen
- Company Settings > Employee Self-Service screen
- Company Settings > Storage screen
- Platform Admin > Storage Providers screen

Rules:

- Employees can request leave themselves.
- Employees can complete assigned onboarding tasks themselves.
- Employees can upload required documents themselves.
- HR must verify documents and activate employees.
- Employees cannot change salary, branch, department, job title, or manager.
- Store files in object storage, not local server storage.
- Use company-isolated bucket or prefix.
- Private files require backend authorization and signed URLs.
- Store file metadata and object keys in database.
- Do not store raw files in database.
- Do not expose permanent public URLs for private HR files.
```
```



---

## Default Storage Provider: AWS S3

Use AWS S3 as the default production storage provider.

Important principle:

The application should depend on a storage abstraction, not directly on AWS-specific code everywhere. AWS S3 is the first provider, but the system must remain portable to other S3-compatible or object storage providers later.

### Default Production Choice

```txt
Provider: AWS S3
Recommended region for UAE/Middle East clients: me-central-1
Bucket visibility: private
Access method: backend-authorized signed URLs
Local development: MinIO or local disk
```

### Required Backend Package

For Laravel, install the S3 filesystem adapter:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### Laravel Filesystem Disk

Configure S3 in `backend/config/filesystems.php`.

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'me-central-1'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => true,
],
```

Default filesystem disk:

```env
FILESYSTEM_DISK=s3
```

### Backend Environment Variables

Add to `backend/.env.example`:

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=me-central-1
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

For local MinIO development:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minio
AWS_SECRET_ACCESS_KEY=minio-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=uae-hrm-local
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### S3 Bucket Strategy

For MVP, use one private S3 bucket with strict company prefixes.

```txt
Bucket: uae-hrm-production-private
Prefix pattern: companies/{company_id}/...
```

Example object keys:

```txt
companies/{company_id}/employees/{employee_id}/documents/{uuid}.{extension}
companies/{company_id}/employees/{employee_id}/profile-images/{uuid}.{extension}
companies/{company_id}/attendance/{attendance_record_id}/attachments/{uuid}.{extension}
companies/{company_id}/leave/{leave_request_id}/attachments/{uuid}.{extension}
companies/{company_id}/payroll/{payroll_period_id}/wps-proofs/{uuid}.{extension}
companies/{company_id}/company-assets/logos/{uuid}.{extension}
```

Future enterprise option:

```txt
One dedicated bucket per company
or
one dedicated AWS account/project per enterprise client
```

### Required S3 Bucket Settings

Production bucket should use:

```txt
Block all public access: enabled
Bucket versioning: enabled if budget allows
Server-side encryption: enabled
Object ownership: bucket owner enforced
Public ACLs: disabled
Bucket policy: least privilege
Lifecycle rules: enabled for deleted/temp files
Access logging or CloudTrail: enabled if required
```

### IAM User / Role Rules

Use least-privilege IAM access.

The application should only have permission to:

```txt
s3:PutObject
s3:GetObject
s3:DeleteObject
s3:ListBucket with restricted prefix
s3:AbortMultipartUpload
s3:ListBucketMultipartUploads
```

Do not give broad administrator permissions to the app.

For production, prefer IAM role-based access where possible. If using access keys, store them only in environment variables or a secret manager.

### S3 Upload Service

Create a storage service:

```txt
App\Services\Storage\FileStorageService
```

Responsibilities:

```txt
1. Validate company scope.
2. Resolve company storage bucket.
3. Generate safe object key.
4. Upload file to S3.
5. Calculate checksum.
6. Store file_objects metadata.
7. Link file to business entity.
8. Create audit log.
9. Return metadata only.
```

Do not return raw permanent S3 URLs for private files.

### S3 Download / Preview Service

Create:

```txt
App\Services\Storage\FileAccessService
```

Responsibilities:

```txt
1. Validate authenticated user.
2. Validate file belongs to user's company.
3. Check permission for file category.
4. Create file_access_logs record.
5. Generate temporary signed URL or stream file.
6. Return temporary access response.
```

Signed URLs should be short-lived.

Recommended default:

```txt
Preview URL expiry: 5 minutes
Download URL expiry: 10 minutes
```

### Upload Modes

Support two upload modes.

#### Mode 1: Backend Proxy Upload

Best for MVP.

```txt
1. Frontend sends file to backend.
2. Backend validates file.
3. Backend uploads file to S3.
4. Backend stores metadata.
```

Advantages:

```txt
simple
secure
easy to audit
easy to validate
```

Disadvantages:

```txt
large files pass through backend
```

#### Mode 2: Pre-Signed Direct Upload

Add later if needed for larger files.

```txt
1. Frontend requests upload slot.
2. Backend creates pending file_objects record.
3. Backend returns pre-signed upload URL.
4. Frontend uploads directly to S3.
5. Frontend calls finalize upload endpoint.
6. Backend verifies object exists.
7. Backend finalizes metadata and business link.
```

Use Mode 1 first. Add Mode 2 later.

### File Metadata Rules

Every S3 object must have a database record in `file_objects`.

```txt
file_objects.object_key
file_objects.bucket_name
file_objects.storage_provider_id
file_objects.company_storage_bucket_id
file_objects.mime_type
file_objects.size_bytes
file_objects.checksum_sha256
file_objects.visibility
file_objects.file_category
file_objects.scan_status
```

The database is the source of truth for file ownership and permissions.

### S3 Provider Seeding

Seed the default storage provider:

```txt
storage_providers
- name: AWS S3
- provider_type: aws_s3
- region: me-central-1
- supports_s3_api: true
- supports_private_buckets: true
- supports_cdn: true
- supports_encryption: true
- status: active
```

During tenant provisioning, create a `company_storage_buckets` record:

```txt
company_id: current company
storage_provider_id: AWS S3 provider ID
bucket_name: env AWS_BUCKET
bucket_region: me-central-1
bucket_prefix: companies/{company_id}
visibility: private
encryption_mode: provider_managed
is_default: true
status: active
```

### Local Development with MinIO

Add MinIO to Docker for local development.

```yaml
minio:
  image: minio/minio:latest
  command: server /data --console-address ":9001"
  environment:
    MINIO_ROOT_USER: minio
    MINIO_ROOT_PASSWORD: minio-secret
  ports:
    - "9000:9000"
    - "9001:9001"
  volumes:
    - minio_data:/data
```

Create local bucket:

```bash
uae-hrm-local
```

The app should use the same S3 disk interface for MinIO and AWS S3.

### Security Rules for S3 Files

```txt
1. Never make HR document buckets public.
2. Never store permanent S3 URLs for private files.
3. Never expose AWS credentials to frontend.
4. Use signed URLs or backend streaming only.
5. Use UUID object names, not original filenames.
6. Validate MIME type and extension.
7. Enforce max file size per file category.
8. Encrypt sensitive identifiers and documents where needed.
9. Log access to sensitive documents.
10. Soft-delete database file records before deleting S3 object if retention is required.
```

### File Size Defaults

Recommended starting limits:

```txt
profile_image: 5 MB
employee_document: 15 MB
leave_attachment: 10 MB
attendance_attachment: 10 MB
wps_transfer_proof: 20 MB
policy_document: 25 MB
```

### Allowed File Types

Recommended starting allowed types:

```txt
Images:
- jpg
- jpeg
- png
- webp

Documents:
- pdf
- doc
- docx
- xls
- xlsx
- csv

Avoid allowing executable files.
```

### Codex Prompt 23: Configure AWS S3 Storage

```txt
Configure AWS S3 as the default storage provider while keeping the app storage-provider agnostic.

Backend requirements:

- install league/flysystem-aws-s3-v3
- configure Laravel s3 filesystem disk
- update backend/.env.example with S3 variables
- create StorageProviderSeeder for AWS S3
- create company_storage_buckets record during tenant provisioning
- create FileStorageService
- create FileAccessService
- create secure upload endpoint
- create secure download/preview endpoint
- store all file metadata in file_objects
- use short-lived signed URLs for private file access
- add file_access_logs for sensitive file access
- add tests for upload, download authorization, tenant isolation, signed URL generation, and blocked public access assumptions

Docker/local requirements:

- add MinIO service to docker-compose.yml
- document local bucket setup
- configure local .env.example for MinIO-compatible S3

Rules:

- Use backend proxy upload for MVP.
- Do not expose AWS credentials to the frontend.
- Do not store raw files in the database.
- Do not store permanent public S3 URLs for private files.
- Use object keys with company prefixes.
- Make it easy to switch to another S3-compatible provider later.
```
```

## Current Priority

The first goal is not to build every feature.

The first goal is to create a strong foundation:

1. Clean monorepo
2. Clear docs
3. Secure authentication
4. Good database structure
5. Employee module
6. Attendance
7. Leave
8. Payroll foundation
9. UAE-specific gratuity calculation
10. Audit logs
11. Attendance correction workflow
12. SaaS platform admin dashboard
13. Client subscription billing and service suspension
14. MoHRE establishment and WPS payroll compliance
15. Numbered feature flows and developer comments
16. Employee self-service portal
17. Company-isolated object storage for documents and images
18. AWS S3 default storage setup

---

## Final Instruction to Codex

When working on this project, prioritize correctness, security, and maintainability over speed.

This is an HRM product handling sensitive employee, salary, and document data.

Always keep the system modular, documented, and easy to extend.


---

## Codex Prompt 17: UAE Compliance Engine

```txt
Build the UAE compliance engine.

Backend requirements:

- legal_rule_sets table
- legal_rule_items table
- company_compliance_settings table
- leave_types table
- leave_policies table
- leave_policy_rules table
- employee_leave_balances table
- leave_requests table
- leave_pay_calculation_items table
- emiratisation_rules table
- emiratisation_snapshots table
- SickLeaveCalculator service
- AnnualLeaveCalculator service
- EmiratisationComplianceService service
- validation that prevents company policy from going below UAE legal minimums
- audit logs for compliance setting changes
- tests for sick leave tier calculation
- tests for annual leave entitlement
- tests for Emiratisation status calculation

Frontend requirements:

- Company Settings > Compliance screen
- Company Settings > Leave Policies screen
- Company Settings > Emiratisation screen
- sick leave calculation preview
- annual leave policy setup
- Emiratisation compliance dashboard
- warning messages when configuration is below legal minimum

Important:

- Keep legal rules configurable by effective date.
- Do not hardcode legal values inside frontend components.
- Store calculation snapshots for auditability.
- Display disclaimer that calculations are guidance and should be legally reviewed before production use.
```
