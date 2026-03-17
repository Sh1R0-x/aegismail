<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;

class CreateCampaignFromDraftRequest extends ApiFormRequest
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

    public function attributes(): array
    {
        return [
            'name' => 'le nom de campagne',
            'scheduledAt' => 'la date de planification',
        ];
    }
}
