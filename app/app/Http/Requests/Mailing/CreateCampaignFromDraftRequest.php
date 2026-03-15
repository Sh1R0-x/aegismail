<?php

namespace App\Http\Requests\Mailing;

use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignFromDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'scheduledAt' => ['nullable', 'date'],
        ];
    }
}
