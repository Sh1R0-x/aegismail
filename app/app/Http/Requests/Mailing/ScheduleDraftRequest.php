<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;

class ScheduleDraftRequest extends ApiFormRequest
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

    public function attributes(): array
    {
        return [
            'scheduledAt' => 'la date de planification',
            'name' => 'le nom de campagne',
        ];
    }
}
