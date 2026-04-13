<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait ApiResponse
 * Provides standardized JSON response methods for controllers.
 */
trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param  mixed  $data
     * @param  string|null  $message
     * @param  array<string, mixed>  $meta
     * @param  int  $code
     * @return JsonResponse
     */
    protected function successResponse(mixed $data, ?string $message = null, array $meta = [], int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $details
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $code, mixed $details = null): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'details' => $details
        ], $code);
    }
}
