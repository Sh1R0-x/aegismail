<?php

namespace App\Http\Requests\Mailing;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

class UpsertTemplateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'htmlBody' => ['nullable', 'string'],
            'textBody' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'le nom du modèle',
            'subject' => 'le sujet du modèle',
            'htmlBody' => 'le contenu HTML du modèle',
            'textBody' => 'la version texte du modèle',
            'active' => 'l’activation du modèle',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $htmlBody = trim((string) $this->input('htmlBody', ''));
            $textBody = trim((string) $this->input('textBody', ''));

            if ($htmlBody === '' && $textBody === '') {
                $validator->errors()->add('textBody', 'Ajoutez au moins une version texte ou HTML au modèle.');
            }
        });
    }
}
