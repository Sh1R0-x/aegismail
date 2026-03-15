<?php

namespace App\Http\Requests\Mailing;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduledAt' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
