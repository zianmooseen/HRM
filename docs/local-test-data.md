# Isolated local test clients

The `IsolatedDemoClientsSeeder` creates a fresh local/testing environment with three client databases. It refuses production environments or an existing central company/user registry. Never run a schema reset against production.

For an intentional reset of a disposable local database, stop workers, put the app into maintenance, and run:

```sh
cd backend
php artisan down
php artisan migrate:fresh --force
php artisan db:seed --class=IsolatedDemoClientsSeeder --force
php artisan clients:databases
php artisan up
```

`migrate:fresh` deletes all central records. It does not delete databases belonging to previously provisioned clients. Inspect `clients:databases` **before** any later reset and manage those obsolete local databases explicitly; never blindly drop all `hrm_client_*` databases. The seeder aborts on errors; keep maintenance enabled until the affected setup is repaired or the disposable test environment is reset again.

## Sign-in accounts

These are deliberately public credentials for synthetic local data only. Sign in using only username/email and password. The shared demo credentials prompt for a company choice: Demo UAE Company (`demo`), Gulf Retail Test LLC (`retail`), or Northern Logistics Test FZE (`logistics`). The platform account signs in directly.

| Role | Username | Password |
| --- | --- | --- |
| Platform admin | `sys.admin` | `sys.admin` |
| Company admin | `com.admin` | `com.admin` |
| HR manager | `hr.manager` | `hr.manager` |
| Payroll manager | `payroll.manager` | `payroll.manager` |
| Department manager | `department.manager` | `department.manager` |
| Employee | `employee.demo` | `employee.demo` |
| Employee (Aisha) | `emp.aisha` | `emp.aisha` |

`SEED_COMPANY_ADMIN_USERNAME` and `SEED_COMPANY_ADMIN_PASSWORD` override the company-admin credentials when configured. Each client deliberately reuses usernames so database isolation can be checked. Client databases contain no platform administrator login.

## Scenarios

Each client has 160 synthetic employees, multiple branches/departments/job titles, reporting managers, employee lifecycle states, onboarding tasks, attendance statuses/corrections, leave balances/requests, payroll periods/payslips, salary components, terminations, UAE compliance settings, Emiratisation snapshots, government profiles, WPS batches/proofs/alerts, and HR audit history. Two temporary workers demonstrate daily/hourly earnings and partial payment balances. Synthetic document PDFs have real file bytes under separate `companies/{id}/test-documents` paths for download/preview testing.

The central database holds only the platform user, company/client registry, access-control defaults, WPS provider catalog, a billing plan, subscriptions, and paid/overdue/open invoices. HR records live inside each client database. All subscriptions remain usable: `retail` is trialing; `demo` and `logistics` are active. Test suspension/reactivation through the platform UI when needed.

Use this dataset to exercise the screens and write workflows; it does not certify legal calculations, banking integration, email delivery, S3 connectivity or every edge case. Run the automated backend tests for those implemented rules and authorization checks. Scenario dates are relative to the seeding date, and the three clients share scenario shapes but never employee records or document paths.

Refresh DBeaver after enabling **Show all databases** to see `hrm_client_*` databases alongside `uae_hrm`. `php artisan clients:databases` maps client codes to their generated database names.
