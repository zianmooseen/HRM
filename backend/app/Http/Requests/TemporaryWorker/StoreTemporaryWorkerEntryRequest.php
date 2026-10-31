<?php

namespace App\Http\Requests\TemporaryWorker;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemporaryWorkerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_key' => ['required', 'uuid'],
            'kind' => ['required', 'in:work,payment'],
            'date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['exclude_unless:kind,work', 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:24'],
            'amount' => ['exclude_unless:kind,payment', 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'method' => ['exclude_unless:kind,payment', 'required', 'in:cash,bank_transfer,cheque,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
