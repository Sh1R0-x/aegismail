<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;
use App\Models\Contact;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Contact|null $contact */
        $contact = $this->route('contact');
        $primaryEmailId = $contact?->contactEmails()->where('is_primary', true)->value('id');

        return [
            'organizationId' => ['nullable', 'integer', 'exists:organizations,id'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'fullName' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('contact_emails', 'email')->ignore($primaryEmailId),
            ],
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
            'email' => 'l’adresse e-mail principale',
            'phone' => 'le téléphone',
            'notes' => 'les notes',
            'status' => 'le statut',
        ];
    }
}
