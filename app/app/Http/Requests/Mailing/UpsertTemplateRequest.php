<?php

namespace App\Http\Requests\Mailing;

use Illuminate\Foundation\Http\FormRequest;

class UpsertTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'htmlBody' => ['nullable', 'string'],
            'textBody' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
