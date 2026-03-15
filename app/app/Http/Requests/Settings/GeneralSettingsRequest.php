<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class GeneralSettingsRequest extends FormRequest
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
}
