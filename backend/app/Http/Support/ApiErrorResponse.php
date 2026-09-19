<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domains\Shared\Support\CorrelationId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function make(
        Request $request,
        string $code,
        string $message,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], static fn (mixed $value): bool => $value !== null),
            'meta' => [
                'request_id' => self::requestId($request),
            ],
        ];

        return response()->json($payload, $status);
    }

    public static function requestId(Request $request): string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) && $id !== '' ? $id : (string) Str::uuid();
    }
}
