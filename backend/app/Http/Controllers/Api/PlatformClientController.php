<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\Platform\StoreClientRequest;
use App\Http\Resources\PlatformClientResource;
use App\Models\PlatformClient;
use App\Services\Tenancy\ProvisionClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlatformClientController extends Controller
{
    use RespondsWithApiEnvelope;

    public function index()
    {
        return $this->success('Clients retrieved.', ['clients' => PlatformClientResource::collection(
            PlatformClient::query()->with(['company', 'runs' => fn ($query) => $query->latest('id')->limit(1)])->latest('id')->get()
        )]);
    }

    public function store(StoreClientRequest $request, ProvisionClient $provisioner)
    {
        $client = $provisioner->register($request->validated(), $request->user());

        return $this->success($client->provisioning_status === 'ready' ? 'Client ready.' : 'Client saved; setup needs attention.', [
            'client' => new PlatformClientResource($client->load(['company', 'runs' => fn ($query) => $query->latest('id')->limit(1)])),
        ], 201);
    }

    public function retry(Request $request, PlatformClient $client, ProvisionClient $provisioner)
    {
        $client = $provisioner->provision($client, $request->user());

        return $this->success('Client setup checked.', [
            'client' => new PlatformClientResource($client->load(['company', 'runs' => fn ($query) => $query->latest('id')->limit(1)])),
        ]);
    }
}
