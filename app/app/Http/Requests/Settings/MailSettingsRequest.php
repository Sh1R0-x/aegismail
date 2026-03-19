<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;

class MailSettingsRequest extends ApiFormRequest
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
            'global_signature_html' => ['nullable', 'string', 'max:50000'],
            'global_signature_text' => ['nullable', 'string', 'max:50000'],
            'clear_signature' => ['sometimes', 'boolean'],
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

    public function attributes(): array
    {
        return [
            'sender_email' => 'l’adresse d’envoi',
            'sender_name' => 'le nom d’expéditeur',
            'global_signature_html' => 'la signature HTML globale',
            'global_signature_text' => 'la signature texte globale',
            'mailbox_username' => 'l’identifiant de la boîte mail',
            'mailbox_password' => 'le mot de passe de la boîte mail',
            'imap_host' => 'l’hôte IMAP',
            'imap_port' => 'le port IMAP',
            'imap_secure' => 'la sécurité IMAP',
            'smtp_host' => 'l’hôte SMTP',
            'smtp_port' => 'le port SMTP',
            'smtp_secure' => 'la sécurité SMTP',
            'sync_enabled' => 'l’activation de la synchronisation IMAP',
            'send_enabled' => 'l’activation de l’envoi',
            'send_window_start' => 'le début de la fenêtre d’envoi',
            'send_window_end' => 'la fin de la fenêtre d’envoi',
            'clear_signature' => 'la suppression explicite de la signature',
        ];
    }
}
