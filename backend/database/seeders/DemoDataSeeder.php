<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyComplianceSetting;
use App\Models\CompanySubscription;
use App\Models\Department;
use App\Models\Document;
use App\Models\EmiratisationSnapshot;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmployeeOnboardingTask;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeServicePeriod;
use App\Models\JobTitle;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LegalRuleSet;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', env('SEED_COMPANY_ADMIN_USERNAME', 'com.admin'))->first();

        if (! $admin) {
            return;
        }

        $company = Company::query()->firstOrCreate(
            ['name' => env('SEED_COMPANY_NAME', 'Demo UAE Company')],
            [
                'legal_name' => env('SEED_COMPANY_LEGAL_NAME', 'Demo UAE Company LLC'),
                'country' => 'AE',
                'emirate' => 'Dubai',
                'default_currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'status' => 'active',
            ],
        );

        // Feature flow step 1: make the demo tenant realistic enough for compliance and WPS screens.
        $company->update([
            'trade_license_number' => 'DED-123456',
            'tax_registration_number' => '100234567800003',
            'emiratisation_applicable' => true,
            'emiratisation_category' => 'large_50_plus',
            'economic_sector_code' => '6201',
            'mohre_establishment_number' => 'MOHRE-987654',
            'wps_bank_name' => 'Emirates NBD',
            'wps_agent_code' => 'ENBD',
            'wps_file_sender_id' => 'DEMOHRM01',
        ]);

        $branchDubai = $this->branch($company, $admin, 'DXB-HQ', 'Dubai HQ', 'Dubai');
        $branchAbuDhabi = $this->branch($company, $admin, 'AUH-OPS', 'Abu Dhabi Operations', 'Abu Dhabi');

        $departments = [
            'HR' => $this->department($company, $branchDubai, $admin, 'HR', 'Human Resources'),
            'FIN' => $this->department($company, $branchDubai, $admin, 'FIN', 'Finance'),
            'OPS' => $this->department($company, $branchAbuDhabi, $admin, 'OPS', 'Operations'),
            'SALES' => $this->department($company, $branchDubai, $admin, 'SALES', 'Sales'),
        ];

        $titles = [
            'HR-MGR' => $this->jobTitle($company, $admin, 'HR-MGR', 'HR Manager'),
            'PAY-OFF' => $this->jobTitle($company, $admin, 'PAY-OFF', 'Payroll Officer'),
            'OPS-SUP' => $this->jobTitle($company, $admin, 'OPS-SUP', 'Operations Supervisor'),
            'SALES-EXE' => $this->jobTitle($company, $admin, 'SALES-EXE', 'Sales Executive'),
            'SWE' => $this->jobTitle($company, $admin, 'SWE', 'Software Engineer'),
        ];

        // Feature flow step 2: create employee records across active, onboarding, leave, and terminated states.
        $employees = [
            'E1001' => $this->employee($company, $branchDubai, $departments['HR'], $titles['HR-MGR'], $admin, [
                'employee_code' => 'E1001',
                'first_name' => 'Aisha',
                'last_name' => 'Al Mansoori',
                'display_name' => 'Aisha Al Mansoori',
                'personal_email' => 'aisha.personal@example.local',
                'work_email' => 'aisha@example.local',
                'gender' => 'female',
                'nationality' => 'United Arab Emirates',
                'is_uae_citizen' => true,
                'skill_level' => '1',
                'is_skilled_worker' => true,
                'status' => 'active',
                'basic_salary' => 18000,
                'monthly_salary' => 26000,
                'hire_date' => '2022-02-01',
                'contract_start_date' => '2025-02-01',
                'contract_end_date' => '2027-01-31',
                'bank_iban' => 'AE070331234567890123456',
                'wps_person_id' => 'WPS1001',
            ]),
            'E1002' => $this->employee($company, $branchDubai, $departments['FIN'], $titles['PAY-OFF'], $admin, [
                'employee_code' => 'E1002',
                'first_name' => 'Omar',
                'last_name' => 'Khan',
                'display_name' => 'Omar Khan',
                'personal_email' => 'omar.personal@example.local',
                'work_email' => 'omar@example.local',
                'gender' => 'male',
                'nationality' => 'Pakistan',
                'is_uae_citizen' => false,
                'skill_level' => '2',
                'is_skilled_worker' => true,
                'status' => 'active',
                'basic_salary' => 12000,
                'monthly_salary' => 17500,
                'hire_date' => '2023-06-15',
                'contract_start_date' => '2025-06-15',
                'contract_end_date' => '2026-07-15',
                'bank_iban' => 'AE580331234567890123457',
                'wps_person_id' => 'WPS1002',
            ]),
            'E1003' => $this->employee($company, $branchAbuDhabi, $departments['OPS'], $titles['OPS-SUP'], $admin, [
                'employee_code' => 'E1003',
                'first_name' => 'Mariam',
                'last_name' => 'Haddad',
                'display_name' => 'Mariam Haddad',
                'personal_email' => 'mariam.personal@example.local',
                'work_email' => 'mariam@example.local',
                'gender' => 'female',
                'nationality' => 'Jordan',
                'status' => 'on_leave',
                'basic_salary' => 9500,
                'monthly_salary' => 14000,
                'hire_date' => '2024-01-10',
                'contract_start_date' => '2024-01-10',
                'contract_end_date' => '2026-01-09',
                'bank_iban' => 'AE420331234567890123458',
                'wps_person_id' => 'WPS1003',
            ]),
            'E1004' => $this->employee($company, $branchDubai, $departments['SALES'], $titles['SALES-EXE'], $admin, [
                'employee_code' => 'E1004',
                'first_name' => 'Noah',
                'last_name' => 'Silva',
                'display_name' => 'Noah Silva',
                'personal_email' => 'noah.personal@example.local',
                'work_email' => 'noah@example.local',
                'gender' => 'male',
                'nationality' => 'Brazil',
                'status' => 'onboarding',
                'basic_salary' => 8000,
                'monthly_salary' => 12000,
                'hire_date' => Carbon::today()->addDays(10)->toDateString(),
                'contract_start_date' => Carbon::today()->addDays(10)->toDateString(),
                'contract_end_date' => Carbon::today()->addYears(2)->toDateString(),
                'bank_iban' => 'AE930331234567890123459',
                'wps_person_id' => 'WPS1004',
            ]),
            'E1005' => $this->employee($company, $branchDubai, $departments['OPS'], $titles['SWE'], $admin, [
                'employee_code' => 'E1005',
                'first_name' => 'Sara',
                'last_name' => 'Mehta',
                'display_name' => 'Sara Mehta',
                'personal_email' => 'sara.personal@example.local',
                'work_email' => 'sara@example.local',
                'gender' => 'female',
                'nationality' => 'India',
                'status' => 'active',
                'basic_salary' => 15000,
                'monthly_salary' => 21000,
                'hire_date' => '2021-09-01',
                'contract_start_date' => '2025-09-01',
                'contract_end_date' => Carbon::today()->addDays(45)->toDateString(),
                'bank_iban' => 'AE250331234567890123450',
                'wps_person_id' => 'WPS1005',
            ]),
        ];

        $this->attachEmployeeLogin($employees['E1001']);
        $this->setManagers($employees);
        $this->seedServicePeriods($employees, $admin);
        $this->seedCompliance($company, $admin);
        $this->seedOnboarding($company, $admin, $employees['E1004']);
        $this->seedAttendance($company, $admin, $employees);
        $this->seedLeave($company, $admin, $employees);
        $this->seedPayroll($company, $admin, $employees);
        $this->seedDocuments($company, $admin, $employees);
        $this->seedPublicHolidays($company, $admin);
        $this->seedBilling($company, $admin);
        $this->seedAuditLogs($company, $admin, $employees);
    }

    private function branch(Company $company, User $admin, string $code, string $name, string $emirate): Branch
    {
        return Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'name' => $name,
                'emirate' => $emirate,
                'city' => $emirate,
                'address_line_1' => $emirate === 'Dubai' ? 'Sheikh Zayed Road' : 'Al Maryah Island',
                'phone' => '+97140000000',
                'email' => strtolower($code).'@example.local',
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function department(Company $company, Branch $branch, User $admin, string $code, string $name): Department
    {
        return Department::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'branch_id' => $branch->id,
                'name' => $name,
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function jobTitle(Company $company, User $admin, string $code, string $title): JobTitle
    {
        return JobTitle::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'title' => $title,
                'description' => $title.' demo role.',
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function employee(Company $company, Branch $branch, Department $department, JobTitle $title, User $admin, array $data): Employee
    {
        return Employee::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_code' => $data['employee_code']],
            [
                ...$data,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'job_title_id' => $title->id,
                'employment_type' => 'full_time',
                'contract_type' => 'fixed_term',
                'probation_end_date' => Carbon::parse($data['hire_date'])->addMonths(6)->toDateString(),
                'work_permit_type' => $data['is_uae_citizen'] ?? false ? 'uae_citizen' : 'mohre_work_permit',
                'bank_name' => 'Emirates NBD',
                'bank_routing_code' => 'EBILAEAD',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function attachEmployeeLogin(Employee $employee): void
    {
        $user = User::query()->updateOrCreate(
            ['username' => 'emp.aisha'],
            [
                'name' => $employee->display_name,
                'email' => 'emp.aisha@example.local',
                'password' => Hash::make('emp.aisha'),
                'status' => 'active',
            ],
        );

        $employee->update(['user_id' => $user->id]);

        $role = Role::query()->where('slug', 'employee')->first();

        if ($role) {
            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'company_id' => $employee->company_id,
                    'branch_id' => $employee->branch_id,
                    'department_id' => $employee->department_id,
                    'scope' => 'self',
                ],
            ]);
        }
    }

    private function setManagers(array $employees): void
    {
        foreach ($employees as $code => $employee) {
            if ($code === 'E1001') {
                continue;
            }

            $employee->update(['manager_employee_id' => $employees['E1001']->id]);
        }
    }

    private function seedServicePeriods(array $employees, User $admin): void
    {
        foreach ($employees as $employee) {
            EmployeeServicePeriod::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'start_date' => $employee->hire_date?->toDateString()],
                [
                    'company_id' => $employee->company_id,
                    'end_date' => null,
                    'employment_type' => $employee->employment_type,
                    'contract_type' => $employee->contract_type,
                    'status' => $employee->status === 'terminated' ? 'ended' : 'active',
                    'change_reason' => 'Demo employment period',
                    'created_by' => $admin->id,
                ],
            );
        }
    }

    private function seedCompliance(Company $company, User $admin): void
    {
        $ruleSet = LegalRuleSet::query()->where('country_code', 'AE')->where('status', 'active')->first();

        if ($ruleSet) {
            CompanyComplianceSetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'legal_rule_set_id' => $ruleSet->id],
                [
                    'payroll_day_divisor' => 'calendar_30',
                    'annual_leave_accrual_method' => 'monthly',
                    'annual_leave_carry_forward_allowed' => true,
                    'annual_leave_max_carry_forward_days' => 10,
                    'public_holidays_count_as_annual_leave' => false,
                    'sick_leave_requires_medical_certificate' => true,
                    'sick_leave_notification_days' => 3,
                    'emiratisation_monitoring_enabled' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        EmiratisationSnapshot::query()->updateOrCreate(
            ['company_id' => $company->id, 'snapshot_date' => Carbon::today()->toDateString()],
            [
                'total_active_workers' => 4,
                'total_skilled_workers' => 3,
                'total_active_uae_citizens' => 1,
                'total_skilled_uae_citizens' => 1,
                'required_uae_citizens' => 2,
                'missing_uae_citizens' => 1,
                'estimated_contribution_amount' => 72000,
                'compliance_status' => 'at_risk',
                'rule_snapshot_json' => ['category' => 'large_50_plus', 'source' => 'demo'],
            ],
        );
    }

    private function seedOnboarding(Company $company, User $admin, Employee $employee): void
    {
        $template = OnboardingTemplate::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Standard UAE Employee Onboarding'],
            [
                'description' => 'Demo checklist for document collection, HR review, payroll setup, and account creation.',
                'employment_type' => 'full_time',
                'is_default' => true,
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $tasks = [
            ['title' => 'Upload passport and visa copy', 'task_type' => 'document_upload', 'assigned_to_role' => 'employee', 'sort_order' => 1, 'due_days_after_start' => 2],
            ['title' => 'Verify HR profile details', 'task_type' => 'hr_review', 'assigned_to_role' => 'hr_manager', 'sort_order' => 2, 'due_days_after_start' => 3],
            ['title' => 'Set payroll and WPS details', 'task_type' => 'payroll_setup', 'assigned_to_role' => 'payroll_manager', 'sort_order' => 3, 'due_days_after_start' => 5],
        ];

        foreach ($tasks as $task) {
            OnboardingTemplateTask::query()->updateOrCreate(
                ['onboarding_template_id' => $template->id, 'title' => $task['title']],
                [
                    ...$task,
                    'description' => $task['title'].' for demo onboarding.',
                    'required' => true,
                ],
            );
        }

        $case = EmployeeOnboardingCase::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_id' => $employee->id],
            [
                'onboarding_template_id' => $template->id,
                'status' => 'in_progress',
                'started_at' => Carbon::today()->subDays(2),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        foreach ($tasks as $task) {
            EmployeeOnboardingTask::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_onboarding_case_id' => $case->id, 'title' => $task['title']],
                [
                    'employee_id' => $employee->id,
                    'description' => $task['title'].' for '.$employee->display_name.'.',
                    'task_type' => $task['task_type'],
                    'assigned_to_role' => $task['assigned_to_role'],
                    'required' => true,
                    'status' => $task['sort_order'] === 1 ? 'completed' : 'pending',
                    'due_date' => Carbon::today()->addDays($task['due_days_after_start'])->toDateString(),
                    'completed_at' => $task['sort_order'] === 1 ? Carbon::today()->subDay() : null,
                    'completed_by' => $task['sort_order'] === 1 ? $admin->id : null,
                ],
            );
        }
    }

    private function seedAttendance(Company $company, User $admin, array $employees): void
    {
        $today = Carbon::today();

        $records = [
            ['employee' => 'E1001', 'date' => $today, 'check_in' => '08:55:00', 'check_out' => null, 'break_minutes' => 0, 'status' => 'present'],
            ['employee' => 'E1002', 'date' => $today, 'check_in' => '09:28:00', 'check_out' => null, 'break_minutes' => 0, 'status' => 'late'],
            ['employee' => 'E1003', 'date' => $today, 'check_in' => null, 'check_out' => null, 'break_minutes' => 0, 'status' => 'on_leave'],
            ['employee' => 'E1005', 'date' => $today, 'check_in' => '08:40:00', 'check_out' => null, 'break_minutes' => 0, 'status' => 'remote'],
            ['employee' => 'E1001', 'date' => $today->copy()->subDay(), 'check_in' => '08:51:00', 'check_out' => '17:35:00', 'break_minutes' => 45, 'status' => 'present'],
            ['employee' => 'E1002', 'date' => $today->copy()->subDay(), 'check_in' => '09:00:00', 'check_out' => '17:20:00', 'break_minutes' => 45, 'status' => 'present'],
        ];

        foreach ($records as $record) {
            AttendanceRecord::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employees[$record['employee']]->id, 'date' => $record['date']->toDateString()],
                [
                    'check_in' => $record['check_in'],
                    'check_out' => $record['check_out'],
                    'break_minutes' => $record['break_minutes'],
                    'status' => $record['status'],
                    'source' => 'manual',
                    'notes' => 'Demo attendance record',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function seedLeave(Company $company, User $admin, array $employees): void
    {
        $annual = LeaveType::query()->where('code', 'annual_leave')->first();
        $sick = LeaveType::query()->where('code', 'sick_leave')->first();

        if (! $annual || ! $sick) {
            return;
        }

        foreach (['E1001' => 21, 'E1002' => 18, 'E1003' => 12, 'E1005' => 24] as $code => $closingBalance) {
            EmployeeLeaveBalance::query()->updateOrCreate(
                ['employee_id' => $employees[$code]->id, 'leave_type_id' => $annual->id, 'leave_year' => Carbon::today()->year],
                [
                    'company_id' => $company->id,
                    'opening_balance' => 5,
                    'accrued_days' => 12,
                    'used_days' => 2,
                    'pending_days' => $code === 'E1002' ? 3 : 0,
                    'carried_forward_days' => 3,
                    'encashed_days' => 0,
                    'adjusted_days' => 0,
                    'closing_balance' => $closingBalance,
                ],
            );
        }

        LeaveRequest::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_id' => $employees['E1002']->id, 'start_date' => Carbon::today()->addDays(7)->toDateString()],
            [
                'leave_type_id' => $annual->id,
                'end_date' => Carbon::today()->addDays(9)->toDateString(),
                'total_days' => 3,
                'working_days' => 3,
                'public_holidays_count' => 0,
                'day_calculation_json' => ['source' => 'demo', 'excluded_public_holidays' => 0],
                'status' => 'pending',
                'reason' => 'Family travel',
                'requested_by' => $admin->id,
            ],
        );

        LeaveRequest::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_id' => $employees['E1003']->id, 'start_date' => Carbon::today()->toDateString()],
            [
                'leave_type_id' => $sick->id,
                'end_date' => Carbon::today()->addDays(1)->toDateString(),
                'total_days' => 2,
                'working_days' => 2,
                'public_holidays_count' => 0,
                'day_calculation_json' => ['source' => 'demo'],
                'status' => 'approved',
                'reason' => 'Medical leave',
                'requested_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => Carbon::now()->subHours(4),
            ],
        );
    }

    private function seedPayroll(Company $company, User $admin, array $employees): void
    {
        $components = [
            'BASIC' => ['name' => 'Basic Salary', 'type' => 'earning'],
            'HOUSING' => ['name' => 'Housing Allowance', 'type' => 'earning'],
            'TRANSPORT' => ['name' => 'Transport Allowance', 'type' => 'earning'],
        ];

        foreach ($components as $code => $component) {
            SalaryComponent::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    ...$component,
                    'is_taxable' => false,
                    'is_recurring' => true,
                    'status' => 'active',
                ],
            );
        }

        $basic = SalaryComponent::query()->where('company_id', $company->id)->where('code', 'BASIC')->first();

        foreach ($employees as $employee) {
            if ($employee->status === 'onboarding') {
                continue;
            }

            EmployeeSalaryComponent::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employee->id, 'salary_component_id' => $basic->id],
                [
                    'amount' => $employee->basic_salary,
                    'effective_from' => $employee->hire_date?->toDateString() ?? Carbon::today()->toDateString(),
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        $period = PayrollPeriod::query()->updateOrCreate(
            ['company_id' => $company->id, 'period_start' => Carbon::today()->startOfMonth()->toDateString()],
            [
                'period_end' => Carbon::today()->endOfMonth()->toDateString(),
                'pay_date' => Carbon::today()->endOfMonth()->toDateString(),
                'status' => 'draft',
                'created_by' => $admin->id,
            ],
        );

        foreach (['E1001', 'E1002', 'E1003', 'E1005'] as $code) {
            $employee = $employees[$code];
            $gross = (float) $employee->monthly_salary;
            $deductions = $code === 'E1002' ? 250 : 0;

            $payslip = Payslip::query()->updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                [
                    'company_id' => $company->id,
                    'gross_pay' => $gross,
                    'total_deductions' => $deductions,
                    'net_pay' => $gross - $deductions,
                    'status' => 'draft',
                    'calculation_snapshot_json' => ['source' => 'demo', 'payroll_day_divisor' => 'calendar_30'],
                ],
            );

            PayslipItem::query()->updateOrCreate(
                ['payslip_id' => $payslip->id, 'label' => 'Monthly salary package'],
                [
                    'salary_component_id' => null,
                    'type' => 'earning',
                    'amount' => $gross,
                    'metadata_json' => ['source' => 'demo'],
                ],
            );
        }
    }

    private function seedDocuments(Company $company, User $admin, array $employees): void
    {
        $documents = [
            ['employee' => 'E1001', 'document_type' => 'passport', 'title' => 'Passport Copy', 'expiry_date' => Carbon::today()->addDays(180)->toDateString()],
            ['employee' => 'E1002', 'document_type' => 'visa', 'title' => 'Residence Visa', 'expiry_date' => Carbon::today()->addDays(35)->toDateString()],
            ['employee' => 'E1005', 'document_type' => 'labor_card', 'title' => 'Labor Card', 'expiry_date' => Carbon::today()->addDays(50)->toDateString()],
            ['employee' => 'E1004', 'document_type' => 'passport', 'title' => 'Passport Copy', 'expiry_date' => Carbon::today()->addYears(2)->toDateString()],
        ];

        foreach ($documents as $document) {
            Document::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employees[$document['employee']]->id, 'document_type' => $document['document_type']],
                [
                    'title' => $document['title'],
                    'original_file_name' => str_replace(' ', '_', strtolower($document['title'])).'.pdf',
                    'disk' => 'local',
                    'path' => 'demo/documents/'.strtolower($document['employee']).'/'.strtolower($document['document_type']).'.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 128000,
                    'issue_date' => Carbon::today()->subYear()->toDateString(),
                    'expiry_date' => $document['expiry_date'],
                    'status' => 'active',
                    'uploaded_by' => $admin->id,
                ],
            );
        }
    }

    private function seedPublicHolidays(Company $company, User $admin): void
    {
        $year = Carbon::today()->year;
        $holidays = [
            ['name' => 'New Year Holiday', 'holiday_date' => $year.'-01-01'],
            ['name' => 'Eid Al Fitr Holiday', 'holiday_date' => $year.'-03-20'],
            ['name' => 'Arafat Day', 'holiday_date' => $year.'-05-26'],
            ['name' => 'UAE National Day', 'holiday_date' => $year.'-12-02'],
        ];

        foreach ($holidays as $holiday) {
            PublicHoliday::query()->updateOrCreate(
                ['company_id' => $company->id, 'holiday_date' => $holiday['holiday_date'], 'name' => $holiday['name']],
                [
                    'country_code' => 'AE',
                    'emirate' => null,
                    'paid' => true,
                    'source' => 'demo',
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function seedBilling(Company $company, User $admin): void
    {
        $plan = SubscriptionPlan::query()->updateOrCreate(
            ['code' => 'growth-demo'],
            [
                'name' => 'Growth Demo',
                'billing_cycle' => 'monthly',
                'price' => 799,
                'currency' => 'AED',
                'max_employees' => 100,
                'features_json' => ['hrm', 'attendance', 'leave', 'payroll', 'compliance'],
                'status' => 'active',
            ],
        );

        $subscription = CompanySubscription::query()->updateOrCreate(
            ['company_id' => $company->id, 'subscription_plan_id' => $plan->id],
            [
                'status' => 'trialing',
                'starts_on' => Carbon::today()->subDays(10)->toDateString(),
                'trial_ends_on' => Carbon::today()->addDays(20)->toDateString(),
                'current_period_starts_on' => Carbon::today()->startOfMonth()->toDateString(),
                'current_period_ends_on' => Carbon::today()->endOfMonth()->toDateString(),
                'notes' => 'Demo subscription for local testing.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        BillingInvoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-DEMO-0001'],
            [
                'company_id' => $company->id,
                'company_subscription_id' => $subscription->id,
                'issue_date' => Carbon::today()->toDateString(),
                'due_date' => Carbon::today()->addDays(14)->toDateString(),
                'subtotal' => 799,
                'tax_amount' => 39.95,
                'total_amount' => 838.95,
                'currency' => 'AED',
                'status' => 'open',
                'notes' => 'Demo invoice.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function seedAuditLogs(Company $company, User $admin, array $employees): void
    {
        foreach ([
            ['action' => 'employee.created', 'employee' => 'E1004'],
            ['action' => 'attendance.updated', 'employee' => 'E1002'],
            ['action' => 'payroll.period.created', 'employee' => 'E1001'],
            ['action' => 'document.uploaded', 'employee' => 'E1005'],
        ] as $index => $event) {
            AuditLog::query()->updateOrCreate(
                ['company_id' => $company->id, 'action' => $event['action'], 'auditable_id' => $employees[$event['employee']]->id],
                [
                    'actor_user_id' => $admin->id,
                    'auditable_type' => Employee::class,
                    'before_json' => null,
                    'after_json' => ['source' => 'demo'],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'DemoDataSeeder',
                    'created_at' => Carbon::now()->subHours($index + 1),
                    'updated_at' => Carbon::now()->subHours($index + 1),
                ],
            );
        }
    }
}
