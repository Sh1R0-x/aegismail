<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

class SettingsStore
{
    public function get(string $key, array $defaults = []): array
    {
        $stored = Setting::query()->where('key', $key)->first()?->value_json;

        return array_replace_recursive($defaults, is_array($stored) ? $stored : []);
    }

    public function put(string $key, array $value, ?int $updatedBy = null): Setting
    {
        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value_json' => $value,
                'updated_by' => $updatedBy,
                'updated_at' => Carbon::now(),
            ],
        );

        return $setting->refresh();
    }
}
