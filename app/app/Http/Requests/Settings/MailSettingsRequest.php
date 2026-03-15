<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_email' => ['required', 'email:rfc', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'global_signature_html' => ['nullable', 'string'],
            'global_signature_text' => ['nullable', 'string'],
            'mailbox_username' => ['required', 'string', 'max:255'],
            'mailbox_password' => ['nullable', 'string', 'max:1000'],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_secure' => ['required', 'boolean'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_secure' => ['required', 'boolean'],
            'sync_enabled' => ['required', 'boolean'],
            'send_enabled' => ['required', 'boolean'],
            'send_window_start' => ['required', 'date_format:H:i'],
            'send_window_end' => ['required', 'date_format:H:i'],
        ];
    }
}
