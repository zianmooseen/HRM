<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['code' => 'annual_leave', 'name' => 'Annual Leave', 'category' => 'annual', 'paid_type' => 'paid', 'requires_document' => false],
            ['code' => 'sick_leave', 'name' => 'Sick Leave', 'category' => 'sick', 'paid_type' => 'tiered', 'requires_document' => true],
            ['code' => 'maternity_leave', 'name' => 'Maternity Leave', 'category' => 'maternity', 'paid_type' => 'tiered', 'requires_document' => true],
            ['code' => 'parental_leave', 'name' => 'Parental Leave', 'category' => 'parental', 'paid_type' => 'paid', 'requires_document' => true],
            ['code' => 'bereavement_leave', 'name' => 'Bereavement Leave', 'category' => 'bereavement', 'paid_type' => 'paid', 'requires_document' => true],
            ['code' => 'hajj_leave', 'name' => 'Hajj Leave', 'category' => 'hajj', 'paid_type' => 'unpaid', 'requires_document' => true],
            ['code' => 'unpaid_leave', 'name' => 'Unpaid Leave', 'category' => 'unpaid', 'paid_type' => 'unpaid', 'requires_document' => false],
            ['code' => 'public_holiday', 'name' => 'Public Holiday', 'category' => 'public_holiday', 'paid_type' => 'paid', 'requires_document' => false],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::query()->firstOrCreate(
                ['company_id' => null, 'code' => $leaveType['code']],
                [
                    ...$leaveType,
                    'requires_approval' => true,
                    'is_statutory' => true,
                    'status' => 'active',
                ],
            );
        }
    }
}
