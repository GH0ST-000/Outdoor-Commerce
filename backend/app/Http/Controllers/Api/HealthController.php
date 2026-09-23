<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Catalog\Search\Services\SearchHealthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __construct(
        private readonly SearchHealthService $search,
    ) {}

    public function __invoke(): JsonResponse
    {
        $search = $this->search->snapshot();

        return response()->json([
            'status' => 'ok',
            'service' => 'backend',
            'search' => [
                'status' => $search['status'],
                'enabled' => $search['enabled'],
                'reachable' => $search['reachable'],
            ],
        ]);
    }
}
