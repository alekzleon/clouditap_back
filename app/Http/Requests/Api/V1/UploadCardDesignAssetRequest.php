<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Card;
use Illuminate\Validation\Rule;

class UploadCardDesignAssetRequest extends ApiFormRequest
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
            'type' => ['required', Rule::in([
                Card::MEDIA_LOGOS,
                Card::MEDIA_FRONT_DESIGN,
                Card::MEDIA_BACK_DESIGN,
            ])],
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de archivo es obligatorio.',
            'type.in' => 'El tipo de archivo no es válido.',
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo enviado no es válido.',
            'file.mimes' => 'El archivo debe ser PNG, JPG, JPEG, WEBP o SVG.',
            'file.max' => 'El archivo no puede pesar más de 10 MB.',
        ];
    }
}
