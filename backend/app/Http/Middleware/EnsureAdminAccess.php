<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Enums\Permission;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureAdminAccess
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiErrorResponse::make(
                $request,
                'UNAUTHORIZED',
                'Authentication is required.',
                401,
            );
        }

        if (! $user->can(Permission::AdminAccess->value)) {
            try {
                $this->recordAuditEvent->execute(new AuditEventData(
                    event: AuditEvent::AdminAccessDenied,
                    actorUserId: $user->id,
                    subjectType: 'route',
                    subjectId: $request->path(),
                    requestId: $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    metadata: [
                        'method' => $request->method(),
                    ],
                ));
            } catch (Throwable) {
                // Denial still returns 403 even if audit write fails.
            }

            return ApiErrorResponse::make(
                $request,
                'ADMIN_ACCESS_REQUIRED',
                'Administrative access is required.',
                403,
            );
        }

        return $next($request);
    }
}
