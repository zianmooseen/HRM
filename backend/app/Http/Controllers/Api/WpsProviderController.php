<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Payroll\StoreWpsProviderRequest;
use App\Http\Resources\WpsProviderResource;
use App\Models\WpsProvider;
use App\Services\Auth\CompanyAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WpsProviderController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return $this->success('WPS providers retrieved.', [
            'wps_providers' => WpsProviderResource::collection(WpsProvider::query()->orderBy('name')->get()),
        ]);
    }

    public function store(StoreWpsProviderRequest $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $provider = WpsProvider::query()->create($request->validated());

        return $this->success('WPS provider created.', ['wps_provider' => new WpsProviderResource($provider)], 201);
    }

    public function update(StoreWpsProviderRequest $request, WpsProvider $provider): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $provider->update($request->validated());

        return $this->success('WPS provider updated.', ['wps_provider' => new WpsProviderResource($provider->fresh())]);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user()->loadMissing('roles.permissions');
        abort_unless($user->hasRole('super_admin'), 403, 'You are not authorized to perform this action.');
    }
}
