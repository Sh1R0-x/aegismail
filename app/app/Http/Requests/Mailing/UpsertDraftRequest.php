<?php

namespace App\Http\Requests\Mailing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDraftRequest extends FormRequest
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
}
