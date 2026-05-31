<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'trade_license_number',
        'tax_registration_number',
        'country',
        'emirate',
        'default_currency',
        'timezone',
        'status',
        'emiratisation_applicable',
        'emiratisation_category',
        'economic_sector_code',
        'mohre_establishment_number',
        'wps_bank_name',
        'wps_agent_code',
        'wps_file_sender_id',
    ];

    protected $casts = [
        'emiratisation_applicable' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
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
        return $this->hasMany(SalaryComponent::class);
    }

    public function employeeSalaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function employeeTerminations(): HasMany
    {
        return $this->hasMany(EmployeeTermination::class);
    }

    public function employeeServicePeriods(): HasMany
    {
        return $this->hasMany(EmployeeServicePeriod::class);
    }

    public function complianceSetting(): HasOne
    {
        return $this->hasOne(CompanyComplianceSetting::class);
    }

    public function publicHolidays(): HasMany
    {
        return $this->hasMany(PublicHoliday::class);
    }

    public function emiratisationSnapshots(): HasMany
    {
        return $this->hasMany(EmiratisationSnapshot::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class)->latestOfMany();
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function wpsPayrollBatches(): HasMany
    {
        return $this->hasMany(WpsPayrollBatch::class);
    }
}
