<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\WpsPayrollBatch;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WpsDeadlineMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_escalates_and_resolves_wps_deadline_alerts(): void
    {
        CarbonImmutable::setTestNow('2026-06-20');
        $company = Company::query()->create(['name' => 'Deadline Company', 'status' => 'active']);
        $period = PayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'pay_date' => '2026-06-01',
            'status' => 'approved',
        ]);

        $this->artisan('wps:monitor-deadlines')->assertSuccessful();
        $this->assertDatabaseHas('wps_compliance_alerts', [
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'severity' => 'overdue',
            'resolved_at' => null,
        ]);

        WpsPayrollBatch::query()->create([
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'batch_number' => 'WPS-DEADLINE-1',
            'status' => 'accepted',
            'salary_month' => '2026-05',
            'accepted_at' => now(),
        ]);

        $this->artisan('wps:monitor-deadlines')->assertSuccessful();
        $this->assertDatabaseMissing('wps_compliance_alerts', [
            'company_id' => $company->id,
            'payroll_period_id' => $period->id,
            'resolved_at' => null,
        ]);

        CarbonImmutable::setTestNow();
    }
}
