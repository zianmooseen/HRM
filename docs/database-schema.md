# Database Schema

This document captures the intended relational model for the Laravel migrations. PostgreSQL is preferred.

## Core Company Tables

- `companies`: legal entity, trade license, tax registration, country, emirate, currency, timezone, status, Emiratisation fields.
- `branches`: company locations with optional manager employee.
- `departments`: company departments, optionally scoped to branches.
- `job_titles`: company job catalog.

## Identity And Authorization

- `users`: login accounts.
- `roles`: global or company-specific role definitions.
- `permissions`: module actions such as `employees.view_salary`.
- `role_permissions`: role-to-permission pivot.
- `user_roles`: scoped role assignments by company, branch, department, or self.

## Employee And Onboarding

- `employees`: HR records, optional login user, employment lifecycle, salary visibility protected by permission.
- `employee_service_periods`: auditable employment periods for initial hire, contract extension, termination, and rehire.
- `documents`: private employee files such as passports, visas, Emirates IDs, contracts, and medical certificates.
- `employee_invitations`: hashed invitation token lifecycle.
- `onboarding_templates`: reusable workflow checklist.
- `onboarding_template_tasks`: template task definitions.
- `employee_onboarding_cases`: one onboarding workflow per employee.
- `employee_onboarding_tasks`: generated workflow tasks.

## Attendance

- `attendance_records`: company-scoped daily employee attendance with date, check-in, check-out, break minutes, status, source, notes, creator/updater, soft deletes, and one record per employee per date.

## Compliance And Leave

- `legal_rule_sets`: versioned UAE legal rule sets by effective date.
- `legal_rule_items`: JSON values for rule keys.
- `company_compliance_settings`: configurable company policy knobs.
- `leave_types`: statutory and company leave types.
- `leave_policies`: policy scope and effective dates.
- `leave_policy_rules`: legal minimum snapshots and company values.
- `employee_leave_balances`: annual entitlement and usage ledger.
- `leave_requests`: leave approval records with approval/rejection notes.
- `leave_request_status_events`: leave request submission, approval, and rejection timeline.
- `leave_pay_calculation_items`: auditable payroll output for approved leave.

## Payroll Foundation

- `salary_components`
- `employee_salary_components`
- `payroll_periods`
- `payslips`
- `payslip_items`
- `employee_terminations`: employee termination and final settlement records with gratuity snapshots and payment status.

## Audit

- `audit_logs`: actor, company, auditable record, action, before/after snapshots, IP, user agent.
