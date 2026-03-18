<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class StoreContactImportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'previewToken' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'previewToken' => 'le jeton de prévalidation',
        ];
    }
}
