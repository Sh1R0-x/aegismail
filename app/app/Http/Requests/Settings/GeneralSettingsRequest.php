<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;

class GeneralSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daily_limit_default' => ['required', 'integer', 'min:1', 'max:100000'],
            'hourly_limit_default' => ['required', 'integer', 'min:1', 'max:10000'],
            'min_delay_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'jitter_min_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'jitter_max_seconds' => ['required', 'integer', 'gte:jitter_min_seconds', 'max:3600'],
            'slow_mode_enabled' => ['required', 'boolean'],
            'stop_on_consecutive_failures' => ['required', 'integer', 'min:1', 'max:100'],
            'stop_on_hard_bounce_threshold' => ['required', 'integer', 'min:1', 'max:100'],
            'open_points' => ['required', 'integer', 'between:-100,100'],
            'click_points' => ['required', 'integer', 'between:-100,100'],
            'reply_points' => ['required', 'integer', 'between:-100,100'],
            'auto_reply_points' => ['required', 'integer', 'between:-100,100'],
            'soft_bounce_points' => ['required', 'integer', 'between:-100,100'],
            'hard_bounce_points' => ['required', 'integer', 'between:-100,100'],
            'unsubscribe_points' => ['required', 'integer', 'between:-100,100'],
            'inactivity_decay_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'jitter_max_seconds.gte' => 'Le jitter maximum doit être supérieur ou égal au jitter minimum.',
        ];
    }

    public function attributes(): array
    {
        return [
            'daily_limit_default' => 'le plafond journalier',
            'hourly_limit_default' => 'le plafond horaire',
            'min_delay_seconds' => 'le délai minimum entre deux envois',
            'jitter_min_seconds' => 'le jitter minimum',
            'jitter_max_seconds' => 'le jitter maximum',
            'slow_mode_enabled' => 'le mode lent',
            'stop_on_consecutive_failures' => 'le seuil d’arrêt sur échecs',
            'stop_on_hard_bounce_threshold' => 'le seuil d’arrêt sur hard bounce',
            'open_points' => 'les points d’ouverture',
            'click_points' => 'les points de clic',
            'reply_points' => 'les points de réponse',
            'auto_reply_points' => 'les points d’auto-réponse',
            'soft_bounce_points' => 'les points de soft bounce',
            'hard_bounce_points' => 'les points de hard bounce',
            'unsubscribe_points' => 'les points de désinscription',
            'inactivity_decay_days' => 'le délai de décroissance d’inactivité',
        ];
    }
}
