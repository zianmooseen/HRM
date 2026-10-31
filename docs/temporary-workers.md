# Temporary workers

Company → People → Temporary Workers tracks daily/hourly workers separately from employees. No user account, onboarding case, payslip, recurring payroll, or bank transfer is created.

## Workflow

1. A company administrator or payroll manager creates a simple profile: name, branch, wage basis, rate; phone and work description are optional.
2. Record one work entry per calendar date. Daily entries accept fractions up to 1 day; hourly entries accept up to 24 hours. Dates cannot be in the future in the company timezone.
3. The server snapshots the current rate and wage basis and calculates earnings with integer half-up rounding to two decimal places. Currency is frozen on the worker profile.
4. Record payments only after they have been made. Partial payments are supported; payments cannot exceed the current unpaid earnings. No advances. The date records when payment occurred; this is a current balance ledger, not a historical accounting-period closing system.
5. Review each worker's cumulative days, hours, dates worked, first/last work date, earnings, paid amount and balance. Days and hours are separate units, not interchangeable. List counts respect company/branch scope; search and status filter the list.
6. Correct erroneous records by voiding them with a reason and entering replacements. Paid work cannot be voided unless sufficient unpaid earnings remain. Voiding a payment corrects the recorded ledger only; it does not reverse an actual transfer. Original records and audit events remain available.
7. Mark finished workers inactive; past records and payment settlement remain available. Reactivate before recording further work. A profile with ledger history cannot change branches; use a separate profile for work at another branch.

## Permissions and isolation

`temporary_workers.view`, `.manage`, and `.pay` are assigned to global super_admin, company_admin and payroll_manager roles. Manage/pay require view too. Employee and HR-manager roles receive no access by default. Backend company/branch checks apply to all reads and writes. Audit snapshots containing temporary-worker wages require view permission. Request UUIDs reject duplicate ledger submissions, and row locks serialize payment, work, rate changes and void operations.

## API

- `GET /api/temporary-workers`: paginated workers with cumulative totals, scoped counts and branch options; query `search`, `status`, `page`.
- `POST /api/temporary-workers`: name, branch_id, wage_basis (`daily|hourly`), rate, status (`active|inactive`), optional phone/job_description.
- `GET /api/temporary-workers/{id}`: profile, totals, paginated work/payment history.
- `PUT /api/temporary-workers/{id}`: same fields as creation; past rate snapshots stay unchanged.
- `POST /api/temporary-workers/{id}/entries`: UUID entry_key, kind (`work|payment`), date (`YYYY-MM-DD`), optional notes/reference; work requires quantity; payment requires amount and method (`cash|bank_transfer|cheque|other`). Rates/amounts for work are calculated by the server.
- `POST /api/temporary-workers/{id}/entries/{entry}/void`: reason.

Amounts and quantities are decimal strings in responses. All APIs use the standard success envelope. Existing organization scope query parameters apply.

## Deployment

Run the new `2026_10_10_000000_create_temporary_workers.php` migration in the legacy company database and in every provisioned tenant database through the existing maintenance/migration workflow (`clients:migrate --client=CODE --force`, with the client already paused). New clients receive the schema through provisioning. The migration adds permissions without resetting existing role customizations. Refresh the session/current-user permissions after deployment.
