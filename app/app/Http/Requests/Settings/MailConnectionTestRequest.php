<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MailConnectionTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_email' => ['sometimes', 'email:rfc', 'max:255'],
            'mailbox_username' => ['sometimes', 'string', 'max:255'],
            'mailbox_password' => ['nullable', 'string', 'max:1000'],
            'imap_host' => ['sometimes', 'string', 'max:255'],
            'imap_port' => ['sometimes', 'integer', 'between:1,65535'],
            'imap_secure' => ['sometimes', 'boolean'],
            'smtp_host' => ['sometimes', 'string', 'max:255'],
            'smtp_port' => ['sometimes', 'integer', 'between:1,65535'],
            'smtp_secure' => ['sometimes', 'boolean'],
        ];
    }
}
