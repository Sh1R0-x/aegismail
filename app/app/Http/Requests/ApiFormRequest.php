<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

abstract class ApiFormRequest extends FormRequest
{
    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
            'string' => 'Le champ :attribute doit être un texte.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'boolean' => 'Le champ :attribute doit être vrai ou faux.',
            'date' => 'Le champ :attribute doit contenir une date valide.',
            'date_format' => 'Le champ :attribute doit respecter le format :format.',
            'max.string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
            'max.numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
            'min.numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
            'between.numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
            'array' => 'Le champ :attribute doit être une liste.',
            'exists' => 'La valeur sélectionnée pour :attribute est introuvable.',
            'in' => 'La valeur choisie pour :attribute est invalide.',
            'gte' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->messages();
        $message = collect($validator->errors()->all())
            ->filter()
            ->unique()
            ->implode(' ');

        throw new HttpResponseException(response()->json([
            'message' => $message !== '' ? $message : 'Le formulaire contient des erreurs.',
            'errors' => $errors,
        ], 422));
    }
}
