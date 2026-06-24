# Database Schema

This document captures the intended relational model for the Laravel migrations. PostgreSQL is preferred.

## Core Company Tables

- `companies`: legal entity, trade license, tax registration, country, emirate, currency, timezone, status, Emiratisation fields, MoHRE establishment number, WPS provider profile, and WPS sender/agent setup.
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

- `employees`: HR records, optional login user, employment lifecycle, explicit work permit/labor card identifiers, salary visibility protected by permission, and WPS bank identifiers.
- `employee_government_profiles`: encrypted MoHRE, work-permit, Emirates ID, visa, passport, and WPS employee identifiers, optionally assigned to one MoHRE establishment.
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
- `public_holidays`: company holiday calendar with country/emirate scope, paid flag, source, and status.
- `emiratisation_rules`: configurable Emiratisation thresholds and contribution assumptions by company category.
- `emiratisation_snapshots`: dated company compliance guidance snapshots.
- `leave_types`: statutory and company leave types.
- `leave_policies`: policy scope and effective dates.
- `leave_policy_rules`: legal minimum snapshots and company values.
- `employee_leave_balances`: annual entitlement and usage ledger.
- `leave_requests`: leave approval records with approval/rejection notes, balance-impact day counts, public holiday exclusions, and a calculation snapshot.
- `leave_request_status_events`: leave request submission, approval, and rejection timeline.
- `leave_pay_calculation_items`: auditable payroll output for approved leave.

## Payroll Foundation

- `salary_components`
- `employee_salary_components`
- `payroll_periods`
- `payslips`
- `payslip_items`
- `employee_terminations`: employee termination and final settlement records with gratuity snapshots and payment status.
- `attendance_correction_requests`: employee/HR attendance correction workflow with approval and rejection state.
- `wps_payroll_batches`: WPS export batch per approved payroll period with batch number, salary month, totals, generated file content, status lifecycle, and validation snapshot.
- `wps_payroll_batch_items`: employee-level WPS export rows linked to payslips with bank identifiers, fixed/variable income, net pay, and row payload.
- `wps_compliance_alerts`: persisted warning, urgent, and overdue alerts for payroll periods that have not reached accepted WPS status.
- `mohre_establishments`: company and optional branch-level MoHRE registration records, expiry dates, WPS applicability, and exemption notes.
- `wps_providers`: platform-managed bank, exchange-house, or financial-institution provider catalog and export profile.
- `company_wps_settings`: establishment-to-provider mapping, payroll due day, currency, sender/agent values, and portal/customer references.
- `wps_transfer_proofs`: private transfer evidence or external references with file hashes and verification status.

WPS security and ownership rules:

- Establishments, settings, government profiles, payroll batches, and proofs are company-scoped.
- Employee government identifiers and payroll bank IBANs use encrypted model casts.
- Sensitive setup, status, proof, and override actions create audit logs.
- Payroll periods retain the selected establishment, provider, due date, WPS status, and lock state.
- Transfer proof files use the configured private filesystem disk and store a SHA-256 hash.

## Platform Billing

- `subscription_plans`: SaaS plan catalog with code, name, monthly price, currency, employee limit, feature flags, and status.
- `company_subscriptions`: company plan assignments with lifecycle status, billing interval, trial dates, current period dates, cancellation date, and assignee.
- `billing_invoices`: company-scoped SaaS invoices linked to subscriptions with invoice number, issue/due dates, amount, currency, status, paid date, and payment reference.

## Audit

- `audit_logs`: actor, company, auditable record, action, before/after snapshots, IP, user agent.
