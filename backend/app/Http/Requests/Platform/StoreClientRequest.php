<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['nullable', 'string', 'max:50', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', 'unique:platform_clients,client_code'],
            'company_name' => ['required', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'size:2'],
            'emirate' => ['nullable', 'string', 'max:100'],
            'timezone' => ['sometimes', 'timezone'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_username' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9._-]+\z/'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:12', 'max:72'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
        ];
    }
}
