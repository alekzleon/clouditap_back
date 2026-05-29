<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateCardNameRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la tarjeta es obligatorio.',
            'name.string' => 'El nombre de la tarjeta debe ser texto.',
            'name.max' => 'El nombre de la tarjeta no puede tener más de :max caracteres.',
        ];
    }
}
