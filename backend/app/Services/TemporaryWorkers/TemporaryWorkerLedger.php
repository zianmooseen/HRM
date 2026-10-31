<?php

namespace App\Services\TemporaryWorkers;

use App\Models\TemporaryWorker;
use App\Models\TemporaryWorkerEntry;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemporaryWorkerLedger
{
    public static function hundredths(string|int|float $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $value): string
    {
        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public function totals(TemporaryWorker $worker): array
    {
        $entries = $worker->entries()->whereNull('voided_at')->get();
        $work = $entries->where('kind', 'work');
        $earned = $work->sum(fn ($row) => self::hundredths($row->amount));
        $paid = $entries->where('kind', 'payment')->sum(fn ($row) => self::hundredths($row->amount));

        return [
            'earned' => self::decimal($earned), 'paid' => self::decimal($paid), 'balance' => self::decimal($earned - $paid),
            'days' => self::decimal($work->where('wage_basis', 'daily')->sum(fn ($row) => self::hundredths($row->quantity))),
            'hours' => self::decimal($work->where('wage_basis', 'hourly')->sum(fn ($row) => self::hundredths($row->quantity))),
            'dates_worked' => $work->count(),
            'first_worked' => $work->min('date')?->format('Y-m-d'),
            'last_worked' => $work->max('date')?->format('Y-m-d'),
        ];
    }

    public function record(Request $request, TemporaryWorker $worker, array $data): TemporaryWorkerEntry
    {
        // 1. Serialize all balance-changing operations against the worker row.
        return DB::transaction(function () use ($request, $worker, $data) {
            $worker = TemporaryWorker::query()->lockForUpdate()->findOrFail($worker->id);
            $existing = $worker->entries()->where('entry_key', $data['entry_key'])->first();
            if ($existing) {
                throw ValidationException::withMessages(['entry_key' => 'This entry was already recorded. Refresh the history before recording another.']);
            }
            $attributes = array_merge($data, ['company_id' => $worker->company_id, 'created_by' => $request->user()->id]);
            if ($data['kind'] === 'work') {
                if ($worker->status !== 'active') {
                    throw ValidationException::withMessages(['status' => 'Reactivate this worker before recording work.']);
                }
                if ($worker->entries()->where('kind', 'work')->where('date', $data['date'])->whereNull('voided_at')->exists()) {
                    throw ValidationException::withMessages(['date' => 'Work is already recorded for this date. Void the incorrect entry before replacing it.']);
                }
                if ($worker->wage_basis === 'daily' && self::hundredths($data['quantity']) > 100) {
                    throw ValidationException::withMessages(['quantity' => 'A daily entry cannot exceed one day.']);
                }
                // 2. Freeze the rate and basis; calculate cents with integer half-up rounding.
                $attributes['wage_basis'] = $worker->wage_basis;
                $attributes['rate'] = $worker->rate;
                $attributes['amount'] = self::decimal(intdiv(self::hundredths($worker->rate) * self::hundredths($data['quantity']) + 50, 100));
            } else {
                if (self::hundredths($data['amount']) > self::hundredths($this->totals($worker)['balance'])) {
                    throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the unpaid wages already earned.']);
                }
            }
            $entry = $worker->entries()->create($attributes);
            // 3. Keep a branch-aware audit snapshot alongside the immutable ledger entry.
            app(AuditLogger::class)->log($request, 'temporary_worker_'.$entry->kind.'.created', $entry, null, $entry->toArray() + ['branch_id' => $worker->branch_id]);

            return $entry;
        });
    }

    public function void(Request $request, TemporaryWorker $worker, int $entryId, string $reason): void
    {
        DB::transaction(function () use ($request, $worker, $entryId, $reason): void {
            $worker = TemporaryWorker::query()->lockForUpdate()->findOrFail($worker->id);
            $entry = $worker->entries()->findOrFail($entryId);
            abort_if($entry->voided_at !== null, 422, 'This entry is already voided.');
            if ($entry->kind === 'work' && self::hundredths($entry->amount) > self::hundredths($this->totals($worker)['balance'])) {
                throw ValidationException::withMessages(['reason' => 'This work has been paid. Correct the associated payment records before voiding it.']);
            }
            $before = $entry->toArray() + ['branch_id' => $worker->branch_id];
            $entry->update(['voided_at' => now(), 'void_reason' => $reason]);
            app(AuditLogger::class)->log($request, 'temporary_worker_'.$entry->kind.'.voided', $entry, $before, $entry->toArray() + ['branch_id' => $worker->branch_id]);
        });
    }
}
