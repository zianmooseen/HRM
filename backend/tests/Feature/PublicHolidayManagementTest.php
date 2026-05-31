<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicHolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_manage_public_holidays(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        Sanctum::actingAs($user);

        $holidayId = $this->postJson('/api/public-holidays', [
            'name' => 'Eid Al Fitr',
            'holiday_date' => '2026-03-20',
            'country_code' => 'ae',
            'emirate' => null,
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.public_holiday.company_id', $company->id)
            ->assertJsonPath('data.public_holiday.country_code', 'AE')
            ->json('data.public_holiday.id');

        $this->getJson('/api/public-holidays')
            ->assertOk()
            ->assertJsonPath('data.public_holidays.0.name', 'Eid Al Fitr');

        $this->putJson("/api/public-holidays/{$holidayId}", [
            'name' => 'Eid Al Fitr Holiday',
            'holiday_date' => '2026-03-20',
            'country_code' => 'AE',
            'emirate' => 'Dubai',
            'paid' => true,
            'source' => 'government',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.public_holiday.name', 'Eid Al Fitr Holiday')
            ->assertJsonPath('data.public_holiday.emirate', 'Dubai')
            ->assertJsonPath('data.public_holiday.status', 'inactive');

        $this->deleteJson("/api/public-holidays/{$holidayId}")
            ->assertOk();

        $this->assertDatabaseMissing('public_holidays', ['id' => $holidayId]);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'public_holiday.created']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'public_holiday.updated']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'public_holiday.deleted']);
    }

    public function test_duplicate_public_holiday_name_and_date_is_rejected(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'National Day',
            'holiday_date' => '2026-12-02',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/public-holidays', [
            'name' => 'National Day',
            'holiday_date' => '2026-12-02',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('holiday_date');
    }

    public function test_company_admin_can_import_public_holidays_and_duplicates_are_skipped(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        PublicHoliday::query()->create([
            'company_id' => $company->id,
            'name' => 'Existing Holiday',
            'holiday_date' => '2026-01-01',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'government',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/public-holidays/import', [
            'holidays' => [
                [
                    'name' => 'Existing Holiday',
                    'holiday_date' => '2026-01-01',
                    'country_code' => 'AE',
                    'paid' => true,
                    'source' => 'government',
                    'status' => 'active',
                ],
                [
                    'name' => 'Arafat Day',
                    'holiday_date' => '2026-05-26',
                    'country_code' => 'ae',
                    'emirate' => null,
                    'paid' => true,
                    'source' => 'imported',
                    'status' => 'active',
                ],
                [
                    'name' => 'Arafat Day',
                    'holiday_date' => '2026-05-26',
                    'country_code' => 'AE',
                    'paid' => true,
                    'source' => 'imported',
                    'status' => 'active',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.import_summary.created_count', 1)
            ->assertJsonPath('data.import_summary.skipped_count', 2)
            ->assertJsonPath('data.public_holidays.0.country_code', 'AE');

        $this->assertDatabaseHas('public_holidays', [
            'company_id' => $company->id,
            'name' => 'Arafat Day',
            'holiday_date' => '2026-05-26 00:00:00',
            'source' => 'imported',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'public_holiday.created',
        ]);
    }

    public function test_company_admin_cannot_update_foreign_company_holiday(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        [, $user] = $this->companyAdmin();
        $otherCompany = Company::query()->create(['name' => 'Other Company']);
        $holiday = PublicHoliday::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Holiday',
            'holiday_date' => '2026-01-01',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'company',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/public-holidays/{$holiday->id}", [
            'name' => 'Foreign Holiday',
            'holiday_date' => '2026-01-01',
            'country_code' => 'AE',
            'paid' => true,
            'source' => 'company',
            'status' => 'inactive',
        ])->assertForbidden();
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create(['name' => 'Demo Company', 'default_currency' => 'AED']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }
}
