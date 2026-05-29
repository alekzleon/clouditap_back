<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'status' => $code,
        ], $code);
    }

    protected function error(string $message, mixed $data = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'status' => $code,
        ], $code);
    }

    protected function validationError(MessageBag $errors): JsonResponse
    {
        return $this->error($errors->first() ?: 'Los datos enviados no son válidos.', [
            'errors' => $errors,
        ], 422);
    }
}
