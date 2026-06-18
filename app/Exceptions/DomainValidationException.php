<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DomainValidationException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Domain validation failed.',
            'error' => $this->getMessage(),
        ], 422);
    }
}
