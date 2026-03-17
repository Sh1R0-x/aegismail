<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertDraftRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['single', 'bulk'])],
            'templateId' => ['nullable', 'integer', 'exists:mail_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'htmlBody' => ['nullable', 'string'],
            'textBody' => ['nullable', 'string'],
            'signatureHtml' => ['nullable', 'string'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.email' => ['nullable', 'string', 'max:255'],
            'recipients.*.contactId' => ['nullable', 'integer', 'exists:contacts,id'],
            'recipients.*.contactEmailId' => ['nullable', 'integer', 'exists:contact_emails,id'],
            'recipients.*.organizationId' => ['nullable', 'integer', 'exists:organizations,id'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'le type de brouillon',
            'templateId' => 'le modèle',
            'subject' => 'le sujet',
            'htmlBody' => 'le contenu HTML',
            'textBody' => 'la version texte',
            'signatureHtml' => 'la signature HTML',
            'recipients' => 'les destinataires',
            'recipients.*.email' => 'l’adresse e-mail du destinataire',
            'recipients.*.contactId' => 'le contact du destinataire',
            'recipients.*.contactEmailId' => 'l’adresse liée au contact',
            'recipients.*.organizationId' => 'l’organisation du destinataire',
            'recipients.*.name' => 'le nom du destinataire',
        ];
    }
}
