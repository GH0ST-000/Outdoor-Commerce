<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Operations\Models\AuditLog;
use App\Domains\Operations\Queries\AuditLogListQuery;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\AuditLogIndexRequest;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminAuditLogController
{
    public function index(
        AuditLogIndexRequest $request,
        AuditLogListQuery $query,
    ): AnonymousResourceCollection {
        $paginator = $query->paginate($request->validated());

        return AuditLogResource::collection($paginator)->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): AuditLogResource
    {
        return (new AuditLogResource($auditLog))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
