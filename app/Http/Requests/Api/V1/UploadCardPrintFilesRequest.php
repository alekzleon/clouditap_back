<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class UploadCardPrintFilesRequest extends ApiFormRequest
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
            'front_file' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:20480'],
            'back_file' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'front_file.required' => 'El archivo frontal para impresión es obligatorio.',
            'front_file.file' => 'El archivo frontal no es válido.',
            'front_file.mimes' => 'El archivo frontal debe ser PNG, JPG, JPEG o PDF.',
            'front_file.max' => 'El archivo frontal no puede pesar más de 20 MB.',
            'back_file.required' => 'El archivo trasero para impresión es obligatorio.',
            'back_file.file' => 'El archivo trasero no es válido.',
            'back_file.mimes' => 'El archivo trasero debe ser PNG, JPG, JPEG o PDF.',
            'back_file.max' => 'El archivo trasero no puede pesar más de 20 MB.',
        ];
    }
}
