<?php

namespace App\Http\Requests\TemporaryWorker;

use Illuminate\Foundation\Http\FormRequest;

class SaveTemporaryWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_description' => ['nullable', 'string', 'max:255'],
            'wage_basis' => ['required', 'in:daily,hourly'],
            'rate' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
