<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class PreviewContactImportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'le fichier d’import',
        ];
    }
}
