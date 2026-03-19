<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;

class DeliverabilitySettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_opens_enabled' => ['required', 'boolean'],
            'tracking_clicks_enabled' => ['required', 'boolean'],
            'max_links_warning_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_remote_images_warning_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
            'html_size_warning_kb' => ['required', 'integer', 'min:1', 'max:10240'],
            'attachment_size_warning_mb' => ['required', 'integer', 'min:1', 'max:10240'],
            'public_base_url' => ['nullable', 'url:https', 'max:2048'],
            'tracking_base_url' => ['nullable', 'url:https', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tracking_opens_enabled' => 'l’activation du tracking des ouvertures',
            'tracking_clicks_enabled' => 'l’activation du tracking des clics',
            'max_links_warning_threshold' => 'le seuil d’alerte sur les liens',
            'max_remote_images_warning_threshold' => 'le seuil d’alerte sur les images distantes',
            'html_size_warning_kb' => 'le seuil de taille HTML',
            'attachment_size_warning_mb' => 'le seuil de taille des pièces jointes',
            'public_base_url' => 'l’URL publique email',
            'tracking_base_url' => 'l’URL publique de tracking',
        ];
    }
}
