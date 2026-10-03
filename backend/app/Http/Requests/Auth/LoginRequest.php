<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['nullable', 'string', 'max:50'],
            'login' => ['required_without:email', 'string'],
            'email' => ['required_without:login', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
