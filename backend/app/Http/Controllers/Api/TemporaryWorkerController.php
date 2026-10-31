<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Requests\TemporaryWorker\SaveTemporaryWorkerRequest;
use App\Http\Requests\TemporaryWorker\StoreTemporaryWorkerEntryRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\TemporaryWorker;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\CompanyAccess;
use App\Services\TemporaryWorkers\TemporaryWorkerLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemporaryWorkerController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __construct(private readonly CompanyAccess $access, private readonly TemporaryWorkerLedger $ledger) {}

    private function company(Request $request, string $action = 'view'): Company
    {
        $user = $request->user()->loadMissing('roles.permissions', 'scopedCompanies');
        $this->access->ensurePermission($user, 'temporary_workers.view');
        $this->access->ensurePermission($user, 'temporary_workers.'.$action);

        return $this->access->ensureCompany($user);
    }

    private function worker(Request $request, int $id, string $action = 'view'): TemporaryWorker
    {
        $company = $this->company($request, $action);

        return TemporaryWorker::query()->where('company_id', $company->id)->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $query = TemporaryWorker::query()->where('company_id', $company->id);
        $counts = ['total' => (clone $query)->count(), 'active' => (clone $query)->where('status', 'active')->count()];
        $workers = $query->with('branch')->when($request->query('search'), fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when(in_array($request->query('status'), ['active', 'inactive']), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('name')->paginate(25);

        return $this->success('Temporary workers retrieved.', [
            'workers' => $workers->getCollection()->map(fn ($w) => $w->toArray() + ['totals' => $this->ledger->totals($w)]),
            'counts' => $counts,
            'branches' => Branch::query()->where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'currency' => $company->default_currency,
            'today' => now($company->timezone ?: 'UTC')->toDateString(),
            'meta' => ['current_page' => $workers->currentPage(), 'last_page' => $workers->lastPage(), 'total' => $workers->total()],
        ]);
    }

    public function store(SaveTemporaryWorkerRequest $request): JsonResponse
    {
        $company = $this->company($request, 'manage');
        $data = $request->validated();
        $this->validateBranch($company, $data['branch_id']);
        $worker = DB::transaction(function () use ($request, $company, $data) {
            $worker = TemporaryWorker::query()->create($data + ['company_id' => $company->id, 'currency' => $company->default_currency ?: 'AED']);
            app(AuditLogger::class)->log($request, 'temporary_worker.created', $worker, null, $worker->toArray());

            return $worker;
        });

        return $this->success('Temporary worker created.', ['worker' => $worker], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $worker = $this->worker($request, $id);
        $entries = $worker->entries()->orderByDesc('date')->orderByDesc('id')->paginate(30);
        $company = $this->company($request);

        return $this->success('Temporary worker retrieved.', [
            'worker' => $worker->load('branch'), 'totals' => $this->ledger->totals($worker),
            'entries' => $entries->items(),
            'today' => now($company->timezone ?: 'UTC')->toDateString(),
            'meta' => ['current_page' => $entries->currentPage(), 'last_page' => $entries->lastPage()],
        ]);
    }

    public function update(SaveTemporaryWorkerRequest $request, int $id): JsonResponse
    {
        $worker = $this->worker($request, $id, 'manage');
        $data = $request->validated();
        $this->validateBranch($this->company($request), $data['branch_id']);
        DB::transaction(function () use ($worker, $request, $data): void {
            $worker = TemporaryWorker::query()->lockForUpdate()->findOrFail($worker->id);
            if ((int) $data['branch_id'] !== (int) $worker->branch_id && $worker->entries()->exists()) {
                throw ValidationException::withMessages(['branch_id' => 'Keep the original branch for this work history. Create a separate worker profile for a different branch.']);
            }
            $before = $worker->toArray();
            $worker->update($data);
            app(AuditLogger::class)->log($request, 'temporary_worker.updated', $worker, $before, $worker->toArray());
        });

        return $this->success('Temporary worker updated.');
    }

    public function record(StoreTemporaryWorkerEntryRequest $request, int $id): JsonResponse
    {
        $worker = $this->worker($request, $id, $request->validated('kind') === 'payment' ? 'pay' : 'manage');
        $company = $this->company($request);
        if ($request->validated('date') > now($company->timezone ?: 'UTC')->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Record work or payments on or before today.']);
        }
        $entry = $this->ledger->record($request, $worker, $request->validated());

        return $this->success('Entry recorded.', ['entry' => $entry], 201);
    }

    public function void(Request $request, int $id, int $entry): JsonResponse
    {
        $worker = $this->worker($request, $id);
        $record = $worker->entries()->findOrFail($entry);
        $this->company($request, $record->kind === 'payment' ? 'pay' : 'manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $this->ledger->void($request, $worker, $entry, $data['reason']);

        return $this->success('Entry voided.');
    }

    private function validateBranch(Company $company, int $id): void
    {
        if (! Branch::query()->where('company_id', $company->id)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a branch in the current company and branch scope.']);
        }
    }
}
