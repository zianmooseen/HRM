# Product Requirements

## Product Vision

Build a secure UAE-focused HRM SaaS platform for employee records, attendance, leave, payroll foundations, onboarding, documents, audit logs, and configurable compliance guidance.

## Target Users

- SaaS platform operators managing customer companies.
- Company admins configuring company settings, branches, roles, and compliance policies.
- HR managers maintaining employees, onboarding, documents, leave, and attendance.
- Payroll managers running salary, leave pay, WPS-ready exports, and payslip workflows.
- Department and branch managers approving team requests.
- Employees using self-service for profile data, leave requests, documents, and payslips.

## MVP Modules

1. Authentication and role-aware session.
2. Company, branch, department, and job title setup.
3. Roles, permissions, and scoped user role assignment.
4. Employee profile and onboarding workflow.
5. Attendance records and manual correction audit trail.
6. Leave management and balances.
7. Payroll foundation with payslip calculation items.
8. UAE compliance engine for leave, sick leave, annual leave, gratuity, and Emiratisation.
9. Employee documents and expiry tracking.
10. Dashboard, reports, and audit logs.

## Key Workflows

### Employee Onboarding Flow

1. HR creates a draft employee.
2. HR selects company, branch, department, job title, manager, and employment details.
3. System assigns an onboarding template and generates tasks.
4. Employee receives an invitation when self-service is enabled.
5. Employee uploads required documents.
6. HR verifies personal details and documents.
7. Payroll manager reviews salary setup.
8. Admin creates login access where needed.
9. HR completes onboarding and activates the employee.

### Leave Request Flow

1. Employee submits leave request.
2. Backend validates dates, balance, policy, documentation, and employee status.
3. Manager or HR approves or rejects.
4. System stores audit logs and calculation snapshots.
5. Payroll consumes leave pay calculation items when applicable.

### Compliance Settings Flow

1. Admin views current UAE legal rule set.
2. Admin configures company policy values.
3. Backend rejects values below legal minimums.
4. System stores the setting and an audit event.
5. Reports show policy version and rule snapshots.

## Non-Functional Requirements

- Secure by default for employee, document, and payroll data.
- Consistent JSON API envelopes.
- PostgreSQL-compatible schema for reporting and JSON rule snapshots.
- Test coverage for authorization, validation, and compliance calculations.
- Docker-friendly local development.

## Future Modules

- Accounting.
- POS.
- Client billing.
- WPS file generation.
- MoHRE establishment integrations.
- AWS S3-backed company file storage.
