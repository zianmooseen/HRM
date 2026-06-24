# Dashboard Scenario Test Report

Test date: 2026-06-15

## Dataset

- 4 companies
- 160 employees in the primary demo company
- 5 branches and 8 departments
- All employee lifecycle statuses
- 1,712 attendance records
- 12 attendance correction requests across pending, approved, and rejected states
- 38 leave requests across pending, approved, rejected, and cancelled states
- 112 employee documents, including 21 expired documents
- 18 onboarding cases across every supported workflow status
- 7 payroll periods and 840 payslips
- 6 WPS batches and 720 employee transfer records
- Paid, accepted, submitted, generated, partially accepted, and rejected WPS scenarios
- Verified, uploaded, rejected, and missing WPS proof scenarios
- 5 employee termination/final-settlement scenarios
- 87 audit log records
- Paid, open, and overdue billing invoices

## Verification

- Laravel: 83 tests passed, 463 assertions.
- Nuxt production build: passed.
- ESLint: 0 errors, 39 existing HTML self-closing warnings.
- Company-admin API smoke test: all dashboard endpoints returned HTTP 200.
- System-admin API smoke test: company selection, billing, provider, and WPS risk endpoints returned HTTP 200.
- Employee API smoke test: self-service endpoints returned HTTP 200 and administrative endpoints correctly returned HTTP 403.
- All company-admin, system-admin, and employee dashboard routes rendered successfully with authenticated sessions.

## Issue Found

Protected pages redirected to login when refreshed because Nuxt SSR did not forward the incoming session cookie and frontend origin to Laravel. The API client now forwards those headers during server-side rendering.
