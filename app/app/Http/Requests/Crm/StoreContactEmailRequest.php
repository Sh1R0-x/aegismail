<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreContactEmailRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('contact_emails', 'email')],
            'isPrimary' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'l’adresse e-mail',
            'isPrimary' => 'le statut principal',
        ];
    }
}
