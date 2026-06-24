<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\UpdateCompanyWpsSettingRequest;
use App\Http\Resources\CompanyWpsSettingResource;
use App\Http\Resources\WpsProviderResource;
use App\Models\CompanyWpsSetting;
use App\Models\MohreEstablishment;
use App\Models\WpsProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WpsSettingController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request, 'wps_settings.view');

        return $this->success('WPS settings retrieved.', [
            'wps_settings' => CompanyWpsSettingResource::collection(
                $company->wpsSettings()->with(['establishment', 'provider'])->orderBy('id')->get()
            ),
            'wps_providers' => WpsProviderResource::collection(
                WpsProvider::query()->where('status', 'active')->orderBy('name')->get()
            ),
        ]);
    }

    public function store(UpdateCompanyWpsSettingRequest $request): JsonResponse
    {
        $company = $this->company($request, 'wps_settings.update');
        $data = $request->validated();
        $establishment = MohreEstablishment::query()
            ->where('company_id', $company->id)
            ->findOrFail($data['mohre_establishment_id']);
        $provider = WpsProvider::query()->where('status', 'active')->findOrFail($data['wps_provider_id']);
        $setting = CompanyWpsSetting::query()->firstOrNew([
            'company_id' => $company->id,
            'mohre_establishment_id' => $establishment->id,
        ]);
        $before = $setting->exists ? $setting->toArray() : null;
        $setting->fill([
            ...$data,
            'created_by' => $setting->created_by ?: $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        if ($company->mohre_establishment_number === null) {
            $company->update([
                'mohre_establishment_number' => $establishment->mohre_establishment_number,
                'wps_bank_name' => $provider->name,
                'wps_provider' => $provider->export_profile,
                'wps_agent_code' => $setting->agent_code,
                'wps_file_sender_id' => $setting->sender_id,
            ]);
        }

        $this->audit->log($request, 'company_wps_setting.updated', $setting, $before, $setting->fresh()->toArray());

        return $this->success('WPS setting saved.', [
            'wps_setting' => new CompanyWpsSettingResource($setting->fresh(['establishment', 'provider'])),
        ]);
    }

    private function company(Request $request, string $permission)
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, $permission);

        return $this->access->ensureCompany($user);
    }
}
