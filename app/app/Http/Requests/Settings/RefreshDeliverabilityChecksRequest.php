<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RefreshDeliverabilityChecksRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mechanisms' => ['nullable', 'array'],
            'mechanisms.*' => [Rule::in(['spf', 'dkim', 'dmarc'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'mechanisms' => 'les mécanismes DNS',
            'mechanisms.*' => 'un mécanisme DNS',
        ];
    }
}
