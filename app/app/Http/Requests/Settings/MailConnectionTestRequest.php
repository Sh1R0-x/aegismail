<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;

class MailConnectionTestRequest extends ApiFormRequest
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

    public function attributes(): array
    {
        return [
            'sender_email' => 'l’adresse d’envoi',
            'mailbox_username' => 'l’identifiant de la boîte mail',
            'mailbox_password' => 'le mot de passe de la boîte mail',
            'imap_host' => 'l’hôte IMAP',
            'imap_port' => 'le port IMAP',
            'imap_secure' => 'la sécurité IMAP',
            'smtp_host' => 'l’hôte SMTP',
            'smtp_port' => 'le port SMTP',
            'smtp_secure' => 'la sécurité SMTP',
        ];
    }
}
