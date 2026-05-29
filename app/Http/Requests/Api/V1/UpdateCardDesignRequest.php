<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCardDesignRequest extends ApiFormRequest
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
            'design_data' => ['required', 'array'],
            'design_data.version' => ['required', 'integer', 'min:1'],
            'design_data.mode' => ['required', Rule::in(['editor', 'uploaded'])],
            'design_data.size' => ['required', 'array'],
            'design_data.size.width_cm' => ['required', 'numeric', 'in:9'],
            'design_data.size.height_cm' => ['required', 'numeric', 'in:5'],
            'design_data.size.preview_width' => ['required', 'integer', 'min:300'],
            'design_data.size.preview_height' => ['required', 'integer', 'min:150'],
            'design_data.size.export_width' => ['required', 'integer', 'min:900'],
            'design_data.size.export_height' => ['required', 'integer', 'min:500'],
            'design_data.front' => ['required', 'array'],
            'design_data.back' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'design_data.required' => 'Los datos del diseño son obligatorios.',
            'design_data.array' => 'Los datos del diseño deben enviarse como objeto JSON.',
            'design_data.version.required' => 'La versión del diseño es obligatoria.',
            'design_data.mode.required' => 'El modo del diseño es obligatorio.',
            'design_data.mode.in' => 'El modo del diseño debe ser editor o uploaded.',
            'design_data.size.required' => 'Las medidas del diseño son obligatorias.',
            'design_data.size.width_cm.in' => 'El ancho de la tarjeta debe ser de 9 cm.',
            'design_data.size.height_cm.in' => 'El alto de la tarjeta debe ser de 5 cm.',
            'design_data.front.required' => 'El diseño frontal es obligatorio.',
            'design_data.back.required' => 'El diseño trasero es obligatorio.',
        ];
    }
}
