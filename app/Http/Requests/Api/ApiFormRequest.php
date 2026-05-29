<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'data' => [
                'errors' => $validator->errors(),
            ],
            'message' => $validator->errors()->first() ?: 'Los datos enviados no son válidos.',
            'status' => 422,
        ], 422));
    }
}
