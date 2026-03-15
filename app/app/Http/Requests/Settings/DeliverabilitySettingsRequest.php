<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DeliverabilitySettingsRequest extends FormRequest
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
        ];
    }
}
