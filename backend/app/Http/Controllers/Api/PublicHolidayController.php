<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Compliance\ImportPublicHolidaysRequest;
use App\Http\Requests\Compliance\StorePublicHolidayRequest;
use App\Http\Resources\PublicHolidayResource;
use App\Models\PublicHoliday;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicHolidayController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly CompanyAccess $access,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'settings.view');

        return $this->success('Public holidays retrieved.', [
            'public_holidays' => PublicHolidayResource::collection(
                $company->publicHolidays()
                    ->orderBy('holiday_date')
                    ->orderBy('name')
                    ->get(),
            ),
        ]);
    }

    public function store(StorePublicHolidayRequest $request): JsonResponse
    {
        $company = $this->company($request, 'settings.update');
        $data = $this->payload($request);
        $this->ensureUniqueHoliday($company->id, $data['holiday_date'], $data['name']);

        // Feature flow step 1: HR records each holiday once so leave and payroll policies can reference it later.
        $holiday = $company->publicHolidays()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'public_holiday.created', $holiday, null, $holiday->toArray());

        return $this->success('Public holiday created.', [
            'public_holiday' => new PublicHolidayResource($holiday),
        ], 201);
    }

    public function import(ImportPublicHolidaysRequest $request): JsonResponse
    {
        $company = $this->company($request, 'settings.update');
        $created = [];
        $skipped = [];
        $seen = [];

        DB::transaction(function () use ($request, $company, &$created, &$skipped, &$seen): void {
            // Feature flow step 2: import accepts a yearly calendar paste and skips rows already present for this company.
            foreach ($request->validated('holidays') as $index => $row) {
                $data = $this->payloadFromArray($row);
                $key = $data['holiday_date'].'|'.$data['name'];

                if (isset($seen[$key]) || $this->holidayExists($company->id, $data['holiday_date'], $data['name'])) {
                    $skipped[] = [
                        'row' => $index + 1,
                        'name' => $data['name'],
                        'holiday_date' => $data['holiday_date'],
                        'reason' => 'duplicate',
                    ];

                    continue;
                }

                $holiday = $company->publicHolidays()->create([
                    ...$data,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                $seen[$key] = true;
                $created[] = $holiday;
                $this->audit->log($request, 'public_holiday.created', $holiday, null, $holiday->toArray());
            }
        });

        return $this->success('Public holidays imported.', [
            'import_summary' => [
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'skipped' => $skipped,
            ],
            'public_holidays' => PublicHolidayResource::collection($created),
        ], 201);
    }

    public function update(StorePublicHolidayRequest $request, PublicHoliday $publicHoliday): JsonResponse
    {
        $company = $this->company($request, 'settings.update');
        $this->ensureOwned($publicHoliday, $company->id);

        $data = $this->payload($request);
        $this->ensureUniqueHoliday($company->id, $data['holiday_date'], $data['name'], $publicHoliday->id);

        $before = $publicHoliday->toArray();
        $publicHoliday->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit->log($request, 'public_holiday.updated', $publicHoliday, $before, $publicHoliday->fresh()->toArray());

        return $this->success('Public holiday updated.', [
            'public_holiday' => new PublicHolidayResource($publicHoliday->fresh()),
        ]);
    }

    public function destroy(Request $request, PublicHoliday $publicHoliday): JsonResponse
    {
        $company = $this->company($request, 'settings.update');
        $this->ensureOwned($publicHoliday, $company->id);

        $before = $publicHoliday->toArray();
        $publicHoliday->delete();

        $this->audit->log($request, 'public_holiday.deleted', $publicHoliday, $before, null);

        return $this->success('Public holiday deleted.');
    }

    private function payload(StorePublicHolidayRequest $request): array
    {
        return $this->payloadFromArray($request->validated());
    }

    private function payloadFromArray(array $data): array
    {
        $data['country_code'] = strtoupper($data['country_code'] ?? 'AE');

        return $data;
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }

    private function ensureOwned(PublicHoliday $holiday, int $companyId): void
    {
        abort_unless($holiday->company_id === $companyId, 403, 'You are not authorized to perform this action.');
    }

    private function ensureUniqueHoliday(int $companyId, string $date, string $name, ?int $ignoreId = null): void
    {
        if ($this->holidayExists($companyId, $date, $name, $ignoreId)) {
            throw ValidationException::withMessages([
                'holiday_date' => ['This public holiday is already recorded for the selected date.'],
            ]);
        }
    }

    private function holidayExists(int $companyId, string $date, string $name, ?int $ignoreId = null): bool
    {
        return PublicHoliday::query()
            ->where('company_id', $companyId)
            ->whereDate('holiday_date', $date)
            ->where('name', $name)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
