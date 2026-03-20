<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class MailSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active_provider' => ['required', 'string', Rule::in(array_keys(config('mailing.outbound_providers', [])))],
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
            'sync_enabled' => ['required', 'boolean'],
            'send_enabled' => ['required', 'boolean'],
            'send_window_start' => ['required', 'date_format:H:i'],
            'send_window_end' => ['required', 'date_format:H:i'],
            'providers' => ['required', 'array'],
            'providers.ovh_mx_plan' => ['required', 'array'],
            'providers.ovh_mx_plan.smtp_host' => ['required', 'string', 'max:255'],
            'providers.ovh_mx_plan.smtp_port' => ['required', 'integer', 'between:1,65535'],
            'providers.ovh_mx_plan.smtp_secure' => ['required', 'boolean'],
            'providers.smtp2go' => ['required', 'array'],
            'providers.smtp2go.smtp_host' => ['nullable', 'string', 'max:255'],
            'providers.smtp2go.smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'providers.smtp2go.smtp_secure' => ['nullable', 'boolean'],
            'providers.smtp2go.smtp_username' => ['nullable', 'string', 'max:255'],
            'providers.smtp2go.smtp_password' => ['nullable', 'string', 'max:1000'],
            'providers.smtp2go.send_enabled' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'active_provider' => 'le provider SMTP actif',
            'sender_email' => 'l’adresse d’envoi',
            'sender_name' => 'le nom d’expéditeur',
            'global_signature_html' => 'la signature HTML globale',
            'global_signature_text' => 'la signature texte globale',
            'mailbox_username' => 'l’identifiant de la boîte mail',
            'mailbox_password' => 'le mot de passe de la boîte mail',
            'imap_host' => 'l’hôte IMAP',
            'imap_port' => 'le port IMAP',
            'imap_secure' => 'la sécurité IMAP',
            'sync_enabled' => 'l’activation de la synchronisation IMAP',
            'send_enabled' => 'l’activation de l’envoi',
            'send_window_start' => 'le début de la fenêtre d’envoi',
            'send_window_end' => 'la fin de la fenêtre d’envoi',
            'clear_signature' => 'la suppression explicite de la signature',
            'providers.ovh_mx_plan.smtp_host' => 'l’hôte SMTP OVH',
            'providers.ovh_mx_plan.smtp_port' => 'le port SMTP OVH',
            'providers.ovh_mx_plan.smtp_secure' => 'la sécurité SMTP OVH',
            'providers.smtp2go.smtp_host' => 'l’hôte SMTP SMTP2GO',
            'providers.smtp2go.smtp_port' => 'le port SMTP SMTP2GO',
            'providers.smtp2go.smtp_secure' => 'la sécurité SMTP SMTP2GO',
            'providers.smtp2go.smtp_username' => 'l’identifiant SMTP SMTP2GO',
            'providers.smtp2go.smtp_password' => 'le mot de passe SMTP SMTP2GO',
            'providers.smtp2go.send_enabled' => 'l’activation du provider SMTP2GO',
        ];
    }
}
