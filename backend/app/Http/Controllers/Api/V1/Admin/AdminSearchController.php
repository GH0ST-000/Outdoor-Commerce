<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Catalog\Search\Services\SearchHealthService;
use App\Domains\Catalog\Search\Services\SearchIndexManager;
use App\Domains\Catalog\Search\Services\SearchSynchronizationService;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class AdminSearchController extends Controller
{
    public function __construct(
        private readonly SearchHealthService $health,
        private readonly SearchIndexManager $indexes,
        private readonly SearchSynchronizationService $sync,
        private readonly RecordAuditEventAction $audit,
    ) {}

    public function status(): JsonResponse
    {
        $snapshot = $this->health->snapshot();

        return response()->json([
            'data' => $snapshot,
            'meta' => ['request_id' => request()->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    public function configure(Request $request): JsonResponse
    {
        $this->indexes->configure();
        $this->record($request, AuditEvent::SearchConfigured);

        return response()->json([
            'data' => ['indexes' => $this->indexes->activeIndexes()],
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    public function rebuild(Request $request): JsonResponse
    {
        $locale = $request->string('locale')->toString();
        $locale = $locale !== '' ? $locale : null;
        $result = $this->sync->rebuild($locale);
        $this->record($request, AuditEvent::SearchRebuilt, ['locale' => $locale]);

        return response()->json([
            'data' => $result,
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    public function syncProduct(Request $request, int $product): JsonResponse
    {
        $this->sync->syncProduct($product);
        $this->record($request, AuditEvent::SearchProductSynced, ['product_id' => $product]);

        return response()->json([
            'data' => ['product_id' => $product],
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    public function removeProduct(Request $request, int $product): JsonResponse
    {
        $this->sync->removeProduct($product);
        $this->record($request, AuditEvent::SearchProductRemoved, ['product_id' => $product]);

        return response()->json([
            'data' => ['product_id' => $product],
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $repair = $request->isMethod('post') && $request->boolean('repair');
        try {
            $report = $this->sync->verify($repair);
        } catch (Throwable) {
            $report = ['missing' => 0, 'unexpected' => 0, 'locales' => []];
        }
        $this->record($request, AuditEvent::SearchVerified, ['repair' => $repair]);

        return response()->json([
            'data' => $report,
            'meta' => ['request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function record(Request $request, AuditEvent $event, ?array $metadata = null): void
    {
        $actor = $request->user();
        $this->audit->execute(new AuditEventData(
            event: $event,
            actorUserId: $actor !== null ? (int) $actor->getAuthIdentifier() : null,
            subjectType: 'search',
            requestId: is_string($request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE))
                ? $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE)
                : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            metadata: $metadata,
        ));
    }
}
