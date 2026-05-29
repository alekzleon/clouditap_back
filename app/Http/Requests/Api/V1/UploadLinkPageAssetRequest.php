<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UploadLinkPageAssetRequest extends ApiFormRequest
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
            'type' => ['required', Rule::in(['cover', 'avatar', 'banner'])],
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
            'banner_id' => ['required_if:type,banner', 'nullable'],
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
            'file.mimes' => 'El archivo debe ser PNG, JPG, JPEG o WEBP.',
            'file.max' => 'El archivo no puede pesar más de 10 MB.',
            'banner_id.required_if' => 'El banner_id es obligatorio al subir una imagen de banner.',
        ];
    }
}
