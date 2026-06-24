<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'job_title_id',
        'manager_employee_id',
        'user_id',
        'employee_code',
        'first_name',
        'middle_name',
        'last_name',
        'display_name',
        'personal_email',
        'work_email',
        'phone',
        'gender',
        'nationality',
        'is_uae_citizen',
        'skill_level',
        'is_skilled_worker',
        'work_permit_type',
        'work_permit_number',
        'labor_card_number',
        'date_of_birth',
        'hire_date',
        'probation_end_date',
        'contract_start_date',
        'contract_end_date',
        'employment_type',
        'contract_type',
        'status',
        'basic_salary',
        'monthly_salary',
        'bank_name',
        'bank_iban',
        'bank_routing_code',
        'wps_person_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_uae_citizen' => 'boolean',
        'is_skilled_worker' => 'boolean',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'basic_salary' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'bank_iban' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function attendanceCorrectionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function onboardingCases(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingCase::class);
    }

    public function terminations(): HasMany
    {
        return $this->hasMany(EmployeeTermination::class);
    }

    public function servicePeriods(): HasMany
    {
        return $this->hasMany(EmployeeServicePeriod::class);
    }

    public function activeServicePeriod(): HasOne
    {
        return $this->hasOne(EmployeeServicePeriod::class)->where('status', 'active')->latestOfMany();
    }

    public function wpsPayrollBatchItems(): HasMany
    {
        return $this->hasMany(WpsPayrollBatchItem::class);
    }

    public function governmentProfile(): HasOne
    {
        return $this->hasOne(EmployeeGovernmentProfile::class);
    }
}
