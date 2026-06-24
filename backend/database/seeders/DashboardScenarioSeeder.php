<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\CompanyWpsSetting;
use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeGovernmentProfile;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeOnboardingCase;
use App\Models\EmployeeOnboardingTask;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeServicePeriod;
use App\Models\EmployeeTermination;
use App\Models\JobTitle;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStatusEvent;
use App\Models\LeaveType;
use App\Models\MohreEstablishment;
use App\Models\OnboardingTemplate;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WpsComplianceAlert;
use App\Models\WpsPayrollBatch;
use App\Models\WpsPayrollBatchItem;
use App\Models\WpsProvider;
use App\Models\WpsTransferProof;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DashboardScenarioSeeder extends Seeder
{
    private const EMPLOYEE_TARGET = 160;

    private const FIRST_NAMES = [
        'Ahmed', 'Fatima', 'Yousef', 'Layla', 'Khalid', 'Noura', 'Hassan', 'Maya',
        'Ravi', 'Priya', 'Arjun', 'Ananya', 'Bilal', 'Sana', 'Omar', 'Mariam',
        'Daniel', 'Grace', 'Samuel', 'Sophia', 'Ali', 'Zainab', 'Tariq', 'Huda',
    ];

    private const LAST_NAMES = [
        'Al Mazrouei', 'Al Nuaimi', 'Rahman', 'Khan', 'Patel', 'Sharma',
        'Fernandes', 'Silva', 'Haddad', 'Nasser', 'Thomas', 'George',
        'Ibrahim', 'Malik', 'Joseph', 'Santos', 'Mensah', 'Kim',
    ];

    private const NATIONALITIES = [
        'United Arab Emirates', 'India', 'Pakistan', 'Philippines', 'Egypt',
        'Jordan', 'Lebanon', 'Bangladesh', 'Sri Lanka', 'Nepal', 'United Kingdom',
        'South Africa', 'Kenya', 'Nigeria',
    ];

    public function run(): void
    {
        $admin = User::query()->where('username', env('SEED_COMPANY_ADMIN_USERNAME', 'com.admin'))->first();
        $company = Company::query()->where('name', env('SEED_COMPANY_NAME', 'Demo UAE Company'))->first();

        if (! $admin || ! $company) {
            return;
        }

        $branches = $this->seedOrganization($company, $admin);
        $departments = Department::query()->where('company_id', $company->id)->orderBy('id')->get()->values();
        $titles = JobTitle::query()->where('company_id', $company->id)->orderBy('id')->get()->values();
        $establishments = $this->seedWpsSetup($company, $admin, $branches);
        $employees = $this->seedEmployees($company, $admin, $branches, $departments, $titles, $establishments);

        $this->seedRoleUsers($company, $employees, $admin);
        $this->seedOnboarding($company, $admin, $employees);
        $this->seedAttendance($company, $admin, $employees);
        $this->seedLeave($company, $admin, $employees);
        $this->seedDocuments($company, $admin, $employees);
        $this->seedTerminations($company, $admin, $employees);
        $this->seedPayrollAndWps($company, $admin, $employees, $establishments);
        $this->seedBillingScenarios($company, $admin);
        $this->seedPlatformCompanies($admin);
        $this->seedAuditHistory($company, $admin, $employees);
    }

    private function seedOrganization(Company $company, User $admin)
    {
        $branchDefinitions = [
            ['code' => 'DXB-HQ', 'name' => 'Dubai HQ', 'emirate' => 'Dubai', 'city' => 'Dubai'],
            ['code' => 'AUH-OPS', 'name' => 'Abu Dhabi Operations', 'emirate' => 'Abu Dhabi', 'city' => 'Abu Dhabi'],
            ['code' => 'SHJ-WH', 'name' => 'Sharjah Warehouse', 'emirate' => 'Sharjah', 'city' => 'Sharjah'],
            ['code' => 'RAK-SALES', 'name' => 'Ras Al Khaimah Sales', 'emirate' => 'Ras Al Khaimah', 'city' => 'Ras Al Khaimah'],
            ['code' => 'DXB-REMOTE', 'name' => 'Remote Workforce', 'emirate' => 'Dubai', 'city' => 'Dubai'],
        ];

        foreach ($branchDefinitions as $definition) {
            Branch::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                [
                    ...$definition,
                    'address_line_1' => $definition['name'].' Business District',
                    'phone' => '+9714'.str_pad((string) (1000000 + crc32($definition['code']) % 8999999), 7, '0'),
                    'email' => strtolower($definition['code']).'@example.local',
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        $branches = Branch::query()->where('company_id', $company->id)->orderBy('id')->get()->values();
        $departmentDefinitions = [
            ['code' => 'HR', 'name' => 'Human Resources', 'branch' => 0],
            ['code' => 'FIN', 'name' => 'Finance', 'branch' => 0],
            ['code' => 'IT', 'name' => 'Information Technology', 'branch' => 0],
            ['code' => 'SALES', 'name' => 'Sales', 'branch' => 3],
            ['code' => 'OPS', 'name' => 'Operations', 'branch' => 1],
            ['code' => 'LOG', 'name' => 'Logistics', 'branch' => 2],
            ['code' => 'CS', 'name' => 'Customer Service', 'branch' => 4],
            ['code' => 'MKT', 'name' => 'Marketing', 'branch' => 0],
        ];

        foreach ($departmentDefinitions as $definition) {
            Department::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                [
                    'branch_id' => $branches[$definition['branch']]->id,
                    'name' => $definition['name'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        foreach ([
            ['code' => 'HR-MGR', 'title' => 'HR Manager'],
            ['code' => 'PAY-OFF', 'title' => 'Payroll Officer'],
            ['code' => 'OPS-SUP', 'title' => 'Operations Supervisor'],
            ['code' => 'SALES-EXE', 'title' => 'Sales Executive'],
            ['code' => 'SWE', 'title' => 'Software Engineer'],
            ['code' => 'ACC', 'title' => 'Accountant'],
            ['code' => 'DRV', 'title' => 'Driver'],
            ['code' => 'WH-ASSOC', 'title' => 'Warehouse Associate'],
            ['code' => 'CS-REP', 'title' => 'Customer Service Representative'],
            ['code' => 'MKT-SPEC', 'title' => 'Marketing Specialist'],
            ['code' => 'BR-MGR', 'title' => 'Branch Manager'],
            ['code' => 'GEN-MGR', 'title' => 'General Manager'],
        ] as $definition) {
            JobTitle::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                [
                    'title' => $definition['title'],
                    'description' => $definition['title'].' scenario role.',
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $branches;
    }

    private function seedWpsSetup(Company $company, User $admin, $branches)
    {
        $provider = WpsProvider::query()->where('status', 'active')->orderBy('id')->first();

        $definitions = [
            [
                'branch' => 0,
                'establishment_name' => 'Dubai Main Establishment',
                'mohre_establishment_number' => 'MOHRE-DXB-10001',
                'status' => 'active',
                'expiry_date' => Carbon::today()->addYear(),
                'wps_required' => true,
            ],
            [
                'branch' => 1,
                'establishment_name' => 'Abu Dhabi Operations Establishment',
                'mohre_establishment_number' => 'MOHRE-AUH-20002',
                'status' => 'active',
                'expiry_date' => Carbon::today()->addDays(45),
                'wps_required' => true,
            ],
            [
                'branch' => 2,
                'establishment_name' => 'Sharjah Warehouse Establishment',
                'mohre_establishment_number' => 'MOHRE-SHJ-30003',
                'status' => 'expired',
                'expiry_date' => Carbon::today()->subDays(10),
                'wps_required' => true,
            ],
            [
                'branch' => 4,
                'establishment_name' => 'Remote Workforce Exemption',
                'mohre_establishment_number' => 'MOHRE-DXB-40004',
                'status' => 'under_review',
                'expiry_date' => Carbon::today()->addMonths(6),
                'wps_required' => false,
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $establishment = MohreEstablishment::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'mohre_establishment_number' => $definition['mohre_establishment_number'],
                ],
                [
                    'branch_id' => $branches[$definition['branch']]->id,
                    'establishment_name' => $definition['establishment_name'],
                    'labour_file_number' => 'LF-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'establishment_card_number' => 'EC-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'trade_license_number' => 'TL-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'emirate' => $branches[$definition['branch']]->emirate,
                    'status' => $definition['status'],
                    'issue_date' => Carbon::today()->subYear(),
                    'expiry_date' => $definition['expiry_date'],
                    'wps_required' => $definition['wps_required'],
                    'wps_exemption_reason' => $definition['wps_required'] ? null : 'Scenario exemption pending MoHRE review.',
                    'notes' => 'Dashboard scenario establishment.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            CompanyWpsSetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'mohre_establishment_id' => $establishment->id],
                [
                    'wps_provider_id' => $provider?->id,
                    'payroll_due_day' => $index === 1 ? 5 : 1,
                    'salary_period_type' => 'monthly',
                    'payment_currency' => 'AED',
                    'sif_export_enabled' => $definition['wps_required'],
                    'provider_portal_url' => $provider?->website,
                    'provider_customer_reference' => 'CUST-'.$company->id.'-'.($index + 1),
                    'auto_mark_paid_allowed' => false,
                    'agent_code' => 'AG'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'sender_id' => 'HRM'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return MohreEstablishment::query()->where('company_id', $company->id)->orderBy('id')->get()->values();
    }

    private function seedEmployees(
        Company $company,
        User $admin,
        $branches,
        $departments,
        $titles,
        $establishments,
    ) {
        $existingCount = Employee::query()->where('company_id', $company->id)->count();

        for ($number = $existingCount + 1; $number <= self::EMPLOYEE_TARGET; $number++) {
            $code = 'E'.str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT);
            $firstName = self::FIRST_NAMES[($number - 1) % count(self::FIRST_NAMES)];
            $lastName = self::LAST_NAMES[(int) floor(($number - 1) / count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];
            $status = $this->employeeStatus($number);
            $isCitizen = $number % 10 === 0 || $number % 17 === 0;
            $hireDate = Carbon::today()->subMonths(6 + ($number % 96))->subDays($number % 27);
            $branch = $branches[($number - 1) % $branches->count()];
            $department = $departments[($number - 1) % $departments->count()];
            $title = $titles[($number - 1) % $titles->count()];
            $basicSalary = 3000 + (($number % 18) * 750);
            $monthlySalary = $basicSalary + 1500 + (($number % 6) * 500);
            $missingIban = $number % 37 === 0;
            $invalidIban = $number % 41 === 0;
            $missingPermit = ! $isCitizen && $number % 43 === 0;
            $iban = $missingIban ? null : ($invalidIban ? 'AE120420000000001234567' : $this->uaeIban($number));

            $employee = Employee::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_code' => $code],
                [
                    'branch_id' => $branch->id,
                    'department_id' => $department->id,
                    'job_title_id' => $title->id,
                    'employee_code' => $code,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $firstName.' '.$lastName.' '.$number,
                    'personal_email' => strtolower($code).'@personal.example.local',
                    'work_email' => strtolower($code).'@example.local',
                    'phone' => '+9715'.str_pad((string) (10000000 + $number), 8, '0', STR_PAD_LEFT),
                    'gender' => $number % 2 === 0 ? 'female' : 'male',
                    'nationality' => $isCitizen ? 'United Arab Emirates' : self::NATIONALITIES[$number % count(self::NATIONALITIES)],
                    'is_uae_citizen' => $isCitizen,
                    'skill_level' => (string) (($number % 5) + 1),
                    'is_skilled_worker' => $number % 4 !== 0,
                    'work_permit_type' => $isCitizen ? 'uae_citizen' : 'mohre_work_permit',
                    'work_permit_number' => $missingPermit ? null : 'WP-'.$code,
                    'labor_card_number' => $missingPermit ? null : 'LC-'.$code,
                    'date_of_birth' => Carbon::today()->subYears(22 + ($number % 35))->subDays($number),
                    'hire_date' => $hireDate,
                    'probation_end_date' => $hireDate->copy()->addMonths(6),
                    'contract_start_date' => $hireDate,
                    'contract_end_date' => $this->contractEndDate($number, $hireDate),
                    'employment_type' => $number % 13 === 0 ? 'part_time' : 'full_time',
                    'contract_type' => $number % 11 === 0 ? 'unlimited' : 'fixed_term',
                    'status' => $status,
                    'basic_salary' => $basicSalary,
                    'monthly_salary' => $monthlySalary,
                    'bank_name' => $iban ? 'Scenario UAE Bank' : null,
                    'bank_iban' => $iban,
                    'bank_routing_code' => $iban ? 'SCENAEAD' : null,
                    'wps_person_id' => $missingPermit ? null : 'WPS-'.$code,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            EmployeeGovernmentProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'company_id' => $company->id,
                    'mohre_establishment_id' => $establishments[($number - 1) % $establishments->count()]->id,
                    'labour_card_number' => $missingPermit ? null : 'LC-'.$code,
                    'work_permit_number' => $missingPermit ? null : 'WP-'.$code,
                    'person_code' => 'PC-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT),
                    'emirates_id_number' => '784-'.(1980 + $number % 25).'-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT).'-'.($number % 10),
                    'visa_file_number' => $isCitizen ? null : 'VISA-'.$code,
                    'passport_number' => 'P'.str_pad((string) (9000000 + $number), 8, '0', STR_PAD_LEFT),
                    'wps_employee_identifier' => $missingPermit ? null : 'WPS-'.$code,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            EmployeeServicePeriod::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'start_date' => $hireDate->toDateString()],
                [
                    'company_id' => $company->id,
                    'end_date' => $status === 'terminated' ? Carbon::today()->subDays($number % 30 + 1) : null,
                    'employment_type' => $employee->employment_type,
                    'contract_type' => $employee->contract_type,
                    'status' => $status === 'terminated' ? 'ended' : 'active',
                    'change_reason' => 'Dashboard scenario service period.',
                    'created_by' => $admin->id,
                    'ended_by' => $status === 'terminated' ? $admin->id : null,
                ],
            );
        }

        $employees = Employee::query()->where('company_id', $company->id)->orderBy('id')->get()->values();
        $managers = $employees->whereIn('status', ['active', 'on_leave'])->take(8)->values();

        foreach ($employees as $index => $employee) {
            if (! $managers->contains('id', $employee->id)) {
                $employee->update(['manager_employee_id' => $managers[$index % $managers->count()]->id]);
            }
        }

        foreach ($branches as $index => $branch) {
            $branch->update(['manager_employee_id' => $managers[$index % $managers->count()]->id]);
        }

        foreach ($departments as $index => $department) {
            $department->update(['manager_employee_id' => $managers[$index % $managers->count()]->id]);
        }

        return $employees;
    }

    private function seedRoleUsers(Company $company, $employees, User $admin): void
    {
        $definitions = [
            ['username' => 'hr.manager', 'role' => 'hr_manager', 'employee' => 5],
            ['username' => 'payroll.manager', 'role' => 'payroll_manager', 'employee' => 6],
            ['username' => 'department.manager', 'role' => 'department_manager', 'employee' => 7],
            ['username' => 'employee.demo', 'role' => 'employee', 'employee' => 8],
        ];

        foreach ($definitions as $definition) {
            $employee = $employees[$definition['employee']];
            $user = User::query()->updateOrCreate(
                ['username' => $definition['username']],
                [
                    'name' => $employee->display_name,
                    'email' => $definition['username'].'@example.local',
                    'password' => Hash::make($definition['username']),
                    'status' => 'active',
                ],
            );
            $employee->update(['user_id' => $user->id]);
            $role = Role::query()->where('slug', $definition['role'])->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => [
                        'company_id' => $company->id,
                        'branch_id' => $definition['role'] === 'department_manager' ? $employee->branch_id : null,
                        'department_id' => $definition['role'] === 'department_manager' ? $employee->department_id : null,
                        'scope' => $definition['role'] === 'department_manager' ? 'department' : ($definition['role'] === 'employee' ? 'self' : 'company'),
                        'created_by' => $admin->id,
                    ],
                ]);
            }
        }
    }

    private function seedOnboarding(Company $company, User $admin, $employees): void
    {
        $template = OnboardingTemplate::query()->where('company_id', $company->id)->where('is_default', true)->first();

        if (! $template) {
            return;
        }

        $templateTasks = $template->tasks()->orderBy('sort_order')->get();
        $candidates = $employees->whereIn('status', ['draft', 'onboarding'])->take(18)->values();
        $caseStatuses = ['draft', 'in_progress', 'waiting_for_employee', 'waiting_for_hr', 'waiting_for_payroll', 'completed', 'cancelled'];

        foreach ($candidates as $index => $employee) {
            $status = $caseStatuses[$index % count($caseStatuses)];
            $case = EmployeeOnboardingCase::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employee->id],
                [
                    'onboarding_template_id' => $template->id,
                    'status' => $status,
                    'started_at' => Carbon::now()->subDays(10 + $index),
                    'completed_at' => $status === 'completed' ? Carbon::now()->subDays(2) : null,
                    'cancelled_at' => $status === 'cancelled' ? Carbon::now()->subDay() : null,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            foreach ($templateTasks as $taskIndex => $task) {
                $taskStatus = match (true) {
                    $status === 'completed' => 'completed',
                    $status === 'cancelled' => 'cancelled',
                    $taskIndex === 0 => 'completed',
                    $taskIndex === 1 && $index % 3 === 0 => 'in_progress',
                    $taskIndex === 2 && $index % 4 === 0 => 'blocked',
                    default => 'pending',
                };

                EmployeeOnboardingTask::query()->updateOrCreate(
                    ['company_id' => $company->id, 'employee_onboarding_case_id' => $case->id, 'title' => $task->title],
                    [
                        'employee_id' => $employee->id,
                        'description' => $task->description,
                        'task_type' => $task->task_type,
                        'assigned_to_role' => $task->assigned_to_role,
                        'required' => $task->required,
                        'status' => $taskStatus,
                        'due_date' => Carbon::today()->addDays(($taskIndex - 1) * 3 - $index % 5),
                        'completed_at' => $taskStatus === 'completed' ? Carbon::now()->subDays(2) : null,
                        'completed_by' => $taskStatus === 'completed' ? $admin->id : null,
                    ],
                );
            }
        }
    }

    private function seedAttendance(Company $company, User $admin, $employees): void
    {
        $eligible = $employees->whereIn('status', ['active', 'on_leave'])->take(130)->values();
        $statuses = ['present', 'present', 'present', 'late', 'remote', 'absent', 'on_leave'];

        foreach ($eligible as $employeeIndex => $employee) {
            for ($day = 0; $day < 21; $day++) {
                $date = Carbon::today()->subDays($day);

                if ($date->isWeekend()) {
                    continue;
                }

                $status = $statuses[($employeeIndex + $day) % count($statuses)];
                $hasTime = in_array($status, ['present', 'late', 'remote'], true);
                $record = AttendanceRecord::query()->updateOrCreate(
                    ['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => $date->toDateString()],
                    [
                        'check_in' => $hasTime ? ($status === 'late' ? '09:35:00' : '08:55:00') : null,
                        'check_out' => $hasTime && $day > 0 ? '17:35:00' : null,
                        'break_minutes' => $hasTime ? 45 : 0,
                        'status' => $status,
                        'source' => $employeeIndex % 3 === 0 ? 'biometric' : ($employeeIndex % 3 === 1 ? 'mobile' : 'manual'),
                        'notes' => 'Dashboard scenario attendance.',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ],
                );

                if ($day === 3 && $employeeIndex < 12) {
                    $correctionStatus = ['pending', 'approved', 'rejected'][$employeeIndex % 3];
                    AttendanceCorrectionRequest::query()->updateOrCreate(
                        ['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => $date->toDateString()],
                        [
                            'attendance_record_id' => $record->id,
                            'correction_type' => 'times',
                            'requested_check_in' => '08:45:00',
                            'requested_check_out' => '17:45:00',
                            'requested_break_minutes' => 45,
                            'requested_status' => 'present',
                            'reason' => 'Biometric terminal did not record the correct time.',
                            'status' => $correctionStatus,
                            'requested_by' => $employee->user_id ?: $admin->id,
                            'approved_by' => $correctionStatus === 'approved' ? $admin->id : null,
                            'approved_at' => $correctionStatus === 'approved' ? Carbon::now()->subDay() : null,
                            'rejected_by' => $correctionStatus === 'rejected' ? $admin->id : null,
                            'rejected_at' => $correctionStatus === 'rejected' ? Carbon::now()->subDay() : null,
                            'rejection_reason' => $correctionStatus === 'rejected' ? 'Submitted evidence did not match access logs.' : null,
                        ],
                    );
                }
            }
        }
    }

    private function seedLeave(Company $company, User $admin, $employees): void
    {
        $leaveTypes = LeaveType::query()->whereNull('company_id')->where('status', 'active')->get()->keyBy('code');
        $annual = $leaveTypes->get('annual_leave');

        if (! $annual) {
            return;
        }

        foreach ($employees->whereIn('status', ['active', 'on_leave'])->take(140)->values() as $index => $employee) {
            EmployeeLeaveBalance::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'leave_year' => Carbon::today()->year],
                [
                    'company_id' => $company->id,
                    'opening_balance' => $index % 4,
                    'accrued_days' => 15 + ($index % 16),
                    'used_days' => $index % 12,
                    'pending_days' => $index % 9 === 0 ? 3 : 0,
                    'carried_forward_days' => $index % 6,
                    'encashed_days' => $index % 17 === 0 ? 2 : 0,
                    'adjusted_days' => $index % 19 === 0 ? 1 : 0,
                    'closing_balance' => max(0, 18 + ($index % 10) - ($index % 12)),
                ],
            );

            if ($index >= 36) {
                continue;
            }

            $codes = ['annual_leave', 'sick_leave', 'maternity_leave', 'unpaid_leave'];
            $leaveType = $leaveTypes->get($codes[$index % count($codes)], $annual);
            $status = ['pending', 'approved', 'rejected', 'cancelled'][$index % 4];
            $start = Carbon::today()->addDays(($index % 15) - 7);
            $request = LeaveRequest::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employee->id, 'start_date' => $start->toDateString()],
                [
                    'leave_type_id' => $leaveType->id,
                    'end_date' => $start->copy()->addDays(2),
                    'total_days' => 3,
                    'working_days' => 3,
                    'public_holidays_count' => 0,
                    'day_calculation_json' => ['source' => 'dashboard_scenario', 'calendar_days' => 3],
                    'status' => $status,
                    'reason' => $leaveType->name.' scenario request.',
                    'requested_by' => $employee->user_id ?: $admin->id,
                    'approved_by' => $status === 'approved' ? $admin->id : null,
                    'approved_at' => $status === 'approved' ? Carbon::now()->subDays(2) : null,
                    'approval_note' => $status === 'approved' ? 'Approved for scenario testing.' : null,
                    'rejected_by' => $status === 'rejected' ? $admin->id : null,
                    'rejected_at' => $status === 'rejected' ? Carbon::now()->subDays(2) : null,
                    'rejection_reason' => $status === 'rejected' ? 'Insufficient coverage during requested dates.' : null,
                ],
            );

            LeaveRequestStatusEvent::query()->updateOrCreate(
                ['company_id' => $company->id, 'leave_request_id' => $request->id, 'status' => $status],
                ['actor_user_id' => $admin->id, 'note' => 'Dashboard scenario status event.'],
            );
        }
    }

    private function seedDocuments(Company $company, User $admin, $employees): void
    {
        $types = ['passport', 'visa', 'labor_card', 'emirates_id', 'contract', 'medical_certificate'];

        foreach ($employees->take(120)->values() as $index => $employee) {
            if ($index % 11 === 0) {
                continue;
            }

            $type = $types[$index % count($types)];
            $expiry = match ($index % 5) {
                0 => Carbon::today()->subDays(15),
                1 => Carbon::today()->addDays(15),
                2 => Carbon::today()->addDays(45),
                default => Carbon::today()->addYears(2),
            };

            Document::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employee->id, 'document_type' => $type],
                [
                    'title' => str($type)->replace('_', ' ')->title().' - '.$employee->employee_code,
                    'original_file_name' => strtolower($employee->employee_code.'_'.$type).'.pdf',
                    'disk' => 'local',
                    'path' => 'demo/documents/'.strtolower($employee->employee_code).'/'.$type.'.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 64000 + $index * 100,
                    'issue_date' => Carbon::today()->subYear(),
                    'expiry_date' => $expiry,
                    'status' => $expiry->isPast() ? 'expired' : 'active',
                    'uploaded_by' => $admin->id,
                ],
            );
        }
    }

    private function seedTerminations(Company $company, User $admin, $employees): void
    {
        foreach ($employees->where('status', 'terminated')->values() as $index => $employee) {
            $paid = $index % 2 === 0;
            $terminationDate = Carbon::today()->subDays(20 + $index);
            $gratuity = round(((float) $employee->basic_salary / 30) * 21 * 2, 2);
            $settlement = $gratuity + 2500 - 300;

            EmployeeTermination::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_id' => $employee->id, 'termination_date' => $terminationDate],
                [
                    'last_working_date' => $terminationDate,
                    'termination_type' => $index % 3 === 0 ? 'resignation' : 'company_initiated',
                    'termination_reason' => 'Dashboard scenario termination.',
                    'basic_salary' => $employee->basic_salary,
                    'unpaid_leave_days' => $index % 4,
                    'gratuity_amount' => $gratuity,
                    'leave_encashment_amount' => 1500,
                    'notice_paid_amount' => 1000,
                    'other_earnings_amount' => 0,
                    'deductions_amount' => 300,
                    'final_settlement_amount' => $settlement,
                    'paid_amount' => $paid ? $settlement : 0,
                    'paid_at' => $paid ? Carbon::now()->subDays(5) : null,
                    'payment_reference' => $paid ? 'EOS-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT) : null,
                    'status' => $paid ? 'paid' : 'approved',
                    'calculation_snapshot_json' => ['source' => 'dashboard_scenario', 'service_years' => 2],
                    'notes' => 'Final settlement scenario.',
                    'created_by' => $admin->id,
                    'paid_by' => $paid ? $admin->id : null,
                ],
            );
        }
    }

    private function seedPayrollAndWps(Company $company, User $admin, $employees, $establishments): void
    {
        $components = [
            ['code' => 'BASIC', 'name' => 'Basic Salary', 'type' => 'earning'],
            ['code' => 'HOUSING', 'name' => 'Housing Allowance', 'type' => 'earning'],
            ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'type' => 'earning'],
            ['code' => 'OVERTIME', 'name' => 'Overtime', 'type' => 'earning'],
            ['code' => 'LOAN', 'name' => 'Loan Deduction', 'type' => 'deduction'],
            ['code' => 'UNPAID', 'name' => 'Unpaid Leave Deduction', 'type' => 'deduction'],
        ];

        foreach ($components as $component) {
            SalaryComponent::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $component['code']],
                [...$component, 'is_taxable' => false, 'is_recurring' => $component['code'] !== 'OVERTIME', 'status' => 'active'],
            );
        }

        $salaryComponents = SalaryComponent::query()->where('company_id', $company->id)->get()->keyBy('code');

        foreach ($employees->whereIn('status', ['active', 'on_leave', 'suspended'])->take(135) as $index => $employee) {
            foreach (['BASIC', 'HOUSING', 'TRANSPORT'] as $componentIndex => $code) {
                $amount = match ($code) {
                    'BASIC' => (float) $employee->basic_salary,
                    'HOUSING' => round((float) $employee->basic_salary * 0.30, 2),
                    default => 750,
                };
                EmployeeSalaryComponent::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'employee_id' => $employee->id,
                        'salary_component_id' => $salaryComponents[$code]->id,
                    ],
                    [
                        'amount' => $amount,
                        'effective_from' => $employee->hire_date,
                        'status' => 'active',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ],
                );
            }
        }

        $provider = WpsProvider::query()->where('status', 'active')->orderBy('id')->first();
        $periodDefinitions = [
            ['offset' => -5, 'status' => 'approved', 'wps' => 'paid', 'batch' => 'paid', 'proof' => 'verified'],
            ['offset' => -4, 'status' => 'approved', 'wps' => 'accepted', 'batch' => 'accepted', 'proof' => 'verified'],
            ['offset' => -3, 'status' => 'approved', 'wps' => 'rejected', 'batch' => 'rejected', 'proof' => 'rejected'],
            ['offset' => -2, 'status' => 'approved', 'wps' => 'partially_accepted', 'batch' => 'partially_accepted', 'proof' => 'uploaded'],
            ['offset' => -1, 'status' => 'approved', 'wps' => 'submitted', 'batch' => 'submitted', 'proof' => 'missing'],
            ['offset' => 0, 'status' => 'approved', 'wps' => 'generated', 'batch' => 'generated', 'proof' => 'missing'],
            ['offset' => 1, 'status' => 'draft', 'wps' => 'not_started', 'batch' => null, 'proof' => null],
        ];

        foreach ($periodDefinitions as $periodIndex => $definition) {
            $start = Carbon::today()->startOfMonth()->addMonths($definition['offset']);
            $end = $start->copy()->endOfMonth();
            $establishment = $establishments[$periodIndex % min(3, $establishments->count())];
            $dueDate = $end->copy()->addDays($periodIndex === 4 ? -10 : 1);
            $period = PayrollPeriod::query()->updateOrCreate(
                ['company_id' => $company->id, 'period_start' => $start->toDateString()],
                [
                    'mohre_establishment_id' => $establishment->id,
                    'wps_provider_id' => $provider?->id,
                    'period_end' => $end,
                    'pay_date' => $end,
                    'payroll_due_date' => $dueDate,
                    'status' => $definition['status'],
                    'wps_status' => $definition['wps'],
                    'created_by' => $admin->id,
                    'approved_by' => $definition['status'] === 'approved' ? $admin->id : null,
                    'approved_at' => $definition['status'] === 'approved' ? $end->copy()->subDays(2) : null,
                ],
            );

            $periodEmployees = $employees->whereIn('status', ['active', 'on_leave', 'suspended'])->take(125)->values();
            foreach ($periodEmployees as $employeeIndex => $employee) {
                $gross = (float) $employee->monthly_salary;
                $deductions = ($employeeIndex + $periodIndex) % 17 === 0 ? 500 : 0;
                $payslip = Payslip::query()->updateOrCreate(
                    ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                    [
                        'company_id' => $company->id,
                        'gross_pay' => $gross,
                        'total_deductions' => $deductions,
                        'net_pay' => $gross - $deductions,
                        'status' => $definition['status'] === 'approved' ? 'approved' : 'draft',
                        'calculation_snapshot_json' => ['source' => 'dashboard_scenario', 'period' => $start->format('Y-m')],
                    ],
                );

                PayslipItem::query()->updateOrCreate(
                    ['payslip_id' => $payslip->id, 'label' => 'Monthly salary package'],
                    ['type' => 'earning', 'amount' => $gross, 'metadata_json' => ['source' => 'dashboard_scenario']],
                );
            }

            if (! $definition['batch']) {
                continue;
            }

            $batch = WpsPayrollBatch::query()->updateOrCreate(
                ['company_id' => $company->id, 'payroll_period_id' => $period->id],
                [
                    'mohre_establishment_id' => $establishment->id,
                    'wps_provider_id' => $provider?->id,
                    'batch_number' => 'WPS-'.$start->format('Ym').'-001',
                    'status' => $definition['batch'],
                    'proof_status' => $definition['proof'],
                    'file_format' => 'sif',
                    'provider' => $provider?->code ?? 'generic',
                    'salary_month' => $start->format('Y-m'),
                    'payroll_due_date' => $dueDate,
                    'record_count' => $periodEmployees->count(),
                    'total_amount' => $period->payslips()->sum('net_pay'),
                    'generated_at' => $end->copy()->subDays(3),
                    'submitted_at' => in_array($definition['batch'], ['submitted', 'processing', 'accepted', 'partially_accepted', 'rejected', 'paid'], true) ? $end->copy()->subDays(2) : null,
                    'accepted_at' => in_array($definition['batch'], ['accepted', 'partially_accepted', 'paid'], true) ? $end->copy()->subDay() : null,
                    'paid_at' => $definition['batch'] === 'paid' ? $end : null,
                    'rejected_at' => $definition['batch'] === 'rejected' ? $end->copy()->subDay() : null,
                    'rejection_reason' => $definition['batch'] === 'rejected' ? 'Provider rejected records with identifier mismatches.' : null,
                    'bank_reference' => 'BANK-'.$start->format('Ym'),
                    'provider_reference' => in_array($definition['batch'], ['generated'], true) ? null : 'PROV-'.$start->format('Ym'),
                    'file_content' => "SCR,{$start->format('Ym')},{$periodEmployees->count()}\n",
                    'file_hash' => hash('sha256', 'scenario-'.$start->format('Ym')),
                    'generated_by' => $admin->id,
                    'status_updated_by' => $admin->id,
                ],
            );

            foreach ($period->payslips()->with('employee')->get() as $itemIndex => $payslip) {
                $itemStatus = $definition['batch'] === 'partially_accepted' && $itemIndex % 12 === 0 ? 'failed' : $definition['batch'];
                WpsPayrollBatchItem::query()->updateOrCreate(
                    ['wps_payroll_batch_id' => $batch->id, 'payslip_id' => $payslip->id],
                    [
                        'employee_id' => $payslip->employee_id,
                        'employee_code' => $payslip->employee->employee_code,
                        'employee_name' => $payslip->employee->display_name,
                        'bank_iban' => $payslip->employee->bank_iban ?: $this->uaeIban($itemIndex + 500),
                        'bank_routing_code' => $payslip->employee->bank_routing_code ?: 'SCENAEAD',
                        'wps_person_id' => $payslip->employee->wps_person_id ?: 'WPS-'.$payslip->employee->employee_code,
                        'provider_employee_reference' => 'EMPREF-'.$payslip->employee_id,
                        'provider_transaction_reference' => $itemStatus === 'paid' ? 'TX-'.$batch->id.'-'.$payslip->employee_id : null,
                        'fixed_income' => $payslip->net_pay,
                        'variable_income' => 0,
                        'net_pay' => $payslip->net_pay,
                        'days_in_period' => $start->daysInMonth,
                        'row_payload_json' => ['source' => 'dashboard_scenario'],
                        'status' => $itemStatus,
                        'paid_at' => $itemStatus === 'paid' ? $end : null,
                        'failure_reason' => $itemStatus === 'failed' ? 'Employee bank account rejected by provider.' : null,
                    ],
                );
            }

            if ($definition['proof'] !== 'missing') {
                WpsTransferProof::query()->updateOrCreate(
                    ['company_id' => $company->id, 'wps_payroll_batch_id' => $batch->id, 'proof_type' => 'manual_reference'],
                    [
                        'payroll_period_id' => $period->id,
                        'wps_provider_id' => $provider?->id,
                        'provider_reference' => 'PROOF-'.$start->format('Ym'),
                        'transaction_reference' => 'TXN-'.$start->format('Ym'),
                        'uploaded_by' => $admin->id,
                        'verified_by' => $definition['proof'] === 'verified' ? $admin->id : null,
                        'verified_at' => $definition['proof'] === 'verified' ? $end : null,
                        'status' => $definition['proof'],
                        'notes' => 'Dashboard scenario WPS evidence.',
                    ],
                );
            }

            if ($definition['proof'] === 'missing' || $definition['batch'] === 'rejected') {
                WpsComplianceAlert::query()->updateOrCreate(
                    ['company_id' => $company->id, 'payroll_period_id' => $period->id, 'type' => $definition['batch'] === 'rejected' ? 'provider_rejection' : 'missing_transfer_proof'],
                    [
                        'severity' => $dueDate->isPast() ? 'overdue' : 'warning',
                        'message' => $definition['batch'] === 'rejected'
                            ? 'Provider rejected the salary transfer batch.'
                            : 'WPS transfer proof or provider reference is missing.',
                        'due_date' => $dueDate,
                        'resolved_at' => null,
                    ],
                );
            }
        }
    }

    private function seedBillingScenarios(Company $company, User $admin): void
    {
        $plan = SubscriptionPlan::query()->where('code', 'growth-demo')->first();

        if (! $plan) {
            return;
        }

        $plan->update(['max_employees' => 250]);
        $subscription = CompanySubscription::query()->where('company_id', $company->id)->latest('id')->first();

        foreach ([
            ['number' => 'INV-SCENARIO-PAID', 'status' => 'paid', 'offset' => -60],
            ['number' => 'INV-SCENARIO-OVERDUE', 'status' => 'overdue', 'offset' => -30],
            ['number' => 'INV-SCENARIO-OPEN', 'status' => 'open', 'offset' => 0],
        ] as $invoice) {
            BillingInvoice::query()->updateOrCreate(
                ['invoice_number' => $invoice['number']],
                [
                    'company_id' => $company->id,
                    'company_subscription_id' => $subscription?->id,
                    'issue_date' => Carbon::today()->addDays($invoice['offset']),
                    'due_date' => Carbon::today()->addDays($invoice['offset'] + 14),
                    'paid_at' => $invoice['status'] === 'paid' ? Carbon::now()->subDays(40) : null,
                    'subtotal' => 799,
                    'tax_amount' => 39.95,
                    'total_amount' => 838.95,
                    'currency' => 'AED',
                    'status' => $invoice['status'],
                    'notes' => 'Dashboard scenario invoice.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function seedPlatformCompanies(User $admin): void
    {
        foreach ([
            ['name' => 'Gulf Retail Scenario LLC', 'emirate' => 'Abu Dhabi', 'status' => 'active'],
            ['name' => 'Northern Logistics Scenario FZE', 'emirate' => 'Sharjah', 'status' => 'active'],
            ['name' => 'Archived Client Scenario LLC', 'emirate' => 'Dubai', 'status' => 'inactive'],
        ] as $index => $definition) {
            $company = Company::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'legal_name' => $definition['name'],
                    'country' => 'AE',
                    'emirate' => $definition['emirate'],
                    'default_currency' => 'AED',
                    'timezone' => 'Asia/Dubai',
                    'status' => $definition['status'],
                    'emiratisation_applicable' => $index !== 2,
                    'emiratisation_category' => $index === 0 ? 'selected_20_to_49' : 'not_applicable',
                    'economic_sector_code' => 'SC'.($index + 1),
                    'mohre_establishment_number' => 'PLATFORM-MOHRE-'.($index + 1),
                ],
            );

            AuditLog::query()->updateOrCreate(
                ['company_id' => $company->id, 'action' => 'company.scenario_seeded', 'auditable_id' => $company->id],
                [
                    'actor_user_id' => $admin->id,
                    'auditable_type' => Company::class,
                    'after_json' => ['status' => $definition['status']],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'DashboardScenarioSeeder',
                ],
            );
        }
    }

    private function seedAuditHistory(Company $company, User $admin, $employees): void
    {
        $actions = [
            'employee.created', 'employee.updated', 'employee.salary_updated',
            'attendance.updated', 'attendance.correction.approved',
            'leave.approved', 'leave.rejected', 'payroll.period.approved',
            'wps.batch.submitted', 'wps.proof.verified', 'document.uploaded',
            'company.settings.updated', 'user.role.changed',
        ];

        for ($index = 0; $index < 80; $index++) {
            $employee = $employees[$index % $employees->count()];
            $action = $actions[$index % count($actions)];
            AuditLog::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'action' => $action,
                    'auditable_id' => $employee->id,
                    'created_at' => Carbon::now()->subHours($index + 1),
                ],
                [
                    'actor_user_id' => $admin->id,
                    'auditable_type' => Employee::class,
                    'before_json' => ['source' => 'dashboard_scenario', 'sequence' => $index],
                    'after_json' => ['source' => 'dashboard_scenario', 'sequence' => $index + 1],
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'DashboardScenarioSeeder',
                    'updated_at' => Carbon::now()->subHours($index + 1),
                ],
            );
        }
    }

    private function employeeStatus(int $number): string
    {
        return match (true) {
            $number % 29 === 0 => 'terminated',
            $number % 23 === 0 => 'suspended',
            $number % 19 === 0 => 'on_leave',
            $number % 17 === 0 => 'onboarding',
            $number % 13 === 0 => 'draft',
            $number % 11 === 0 => 'archived',
            default => 'active',
        };
    }

    private function contractEndDate(int $number, Carbon $hireDate): Carbon
    {
        return match ($number % 6) {
            0 => Carbon::today()->subDays(10),
            1 => Carbon::today()->addDays(15),
            2 => Carbon::today()->addDays(45),
            default => $hireDate->copy()->addYears(2),
        };
    }

    private function uaeIban(int $sequence): string
    {
        $bankAndAccount = '033'.str_pad((string) (1000000000000000 + $sequence), 16, '0', STR_PAD_LEFT);
        $numeric = $bankAndAccount.'101400';
        $remainder = 0;

        foreach (str_split($numeric) as $digit) {
            $remainder = (($remainder * 10) + (int) $digit) % 97;
        }

        return 'AE'.str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT).$bankAndAccount;
    }
}
