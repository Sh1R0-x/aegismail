<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class UpdateOrganizationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'le nom de l’organisation',
            'domain' => 'le domaine',
            'website' => 'le site web',
            'notes' => 'les notes',
        ];
    }
}
