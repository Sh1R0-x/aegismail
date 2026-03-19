<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AutosaveCampaignRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campaignId' => ['nullable', 'integer', 'exists:mail_campaigns,id'],
            'draftId' => ['nullable', 'integer', 'exists:mail_drafts,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['single', 'bulk'])],
            'templateId' => ['nullable', 'integer', 'exists:mail_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'htmlBody' => ['nullable', 'string', 'max:1000000'],
            'textBody' => ['nullable', 'string', 'max:500000'],
            'signatureHtml' => ['nullable', 'string', 'max:50000'],
            'expectedUpdatedAt' => ['nullable', 'date'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'recipients.*.contactId' => ['nullable', 'integer', 'exists:contacts,id'],
            'recipients.*.contactEmailId' => ['nullable', 'integer', 'exists:contact_emails,id'],
            'recipients.*.organizationId' => ['nullable', 'integer', 'exists:organizations,id'],
            'recipients.*.organizationName' => ['nullable', 'string', 'max:255'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'campaignId' => 'la campagne',
            'draftId' => 'le brouillon technique',
            'name' => 'le nom de campagne',
            'type' => 'le type de campagne',
            'templateId' => 'le modèle',
            'subject' => 'le sujet',
            'htmlBody' => 'le contenu HTML',
            'textBody' => 'la version texte',
            'signatureHtml' => 'la signature HTML',
            'expectedUpdatedAt' => 'l’horodatage attendu',
            'recipients' => 'les destinataires',
            'recipients.*.email' => 'l’adresse e-mail du destinataire',
            'recipients.*.contactId' => 'le contact du destinataire',
            'recipients.*.contactEmailId' => 'l’adresse liée au contact',
            'recipients.*.organizationId' => 'l’organisation du destinataire',
            'recipients.*.organizationName' => 'le nom de l’organisation du destinataire',
            'recipients.*.name' => 'le nom du destinataire',
        ];
    }
}
