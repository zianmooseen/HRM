<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('employees')
            ->whereNotNull('hire_date')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($now): void {
                foreach ($employees as $employee) {
                    $exists = DB::table('employee_service_periods')
                        ->where('employee_id', $employee->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('employee_service_periods')->insert([
                        'company_id' => $employee->company_id,
                        'employee_id' => $employee->id,
                        'start_date' => $employee->contract_start_date ?: $employee->hire_date,
                        'end_date' => $employee->contract_end_date,
                        'employment_type' => $employee->employment_type,
                        'contract_type' => $employee->contract_type,
                        'status' => in_array($employee->status, ['terminated', 'archived'], true) ? 'terminated' : 'active',
                        'change_reason' => 'Backfilled from employee record',
                        'created_by' => $employee->created_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('employee_service_periods')
            ->where('change_reason', 'Backfilled from employee record')
            ->delete();
    }
};
