<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;

class BulkDeleteDraftsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:mail_drafts,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'la sélection de brouillons',
            'ids.*' => 'le brouillon sélectionné',
        ];
    }
}
