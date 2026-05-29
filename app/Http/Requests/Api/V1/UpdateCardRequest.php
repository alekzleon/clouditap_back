<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CardStatus;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends ApiFormRequest
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
        $cardId = $this->route('card')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'hex_color'],
            'status' => ['sometimes', Rule::enum(CardStatus::class)],
            'nfc_id' => ['nullable', 'string', 'max:255', Rule::unique('cards', 'nfc_id')->ignore($cardId)],
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
            'color.hex_color' => 'El color de la tarjeta debe ser un color hexadecimal válido.',
            'status' => 'El estatus de la tarjeta no es válido.',
            'nfc_id.string' => 'El identificador NFC debe ser texto.',
            'nfc_id.max' => 'El identificador NFC no puede tener más de :max caracteres.',
            'nfc_id.unique' => 'Este identificador NFC ya está asignado a otra tarjeta.',
        ];
    }
}
