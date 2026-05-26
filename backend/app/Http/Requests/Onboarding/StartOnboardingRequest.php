<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StartOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'onboarding_template_id' => ['required', 'integer', 'exists:onboarding_templates,id'],
        ];
    }
}
