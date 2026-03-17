<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class StoreContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organizationId' => ['nullable', 'integer', 'exists:organizations,id'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'fullName' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:contact_emails,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'organizationId' => 'l’organisation',
            'firstName' => 'le prénom',
            'lastName' => 'le nom',
            'fullName' => 'le nom complet',
            'title' => 'le poste',
            'email' => 'l’adresse e-mail',
            'phone' => 'le téléphone',
            'notes' => 'les notes',
            'status' => 'le statut',
        ];
    }
}
