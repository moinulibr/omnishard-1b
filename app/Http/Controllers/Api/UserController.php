<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Class UserController
 * Handles the incoming search requests.
 */
class UserController extends Controller
{
    /** @var UserSearchService */
    protected $searchService;

    public function __construct(UserSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Search user with performance metrics.
     * * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $identifier = $request->query('q');
        if (!$identifier) {
            return response()->json(['error' => 'Query parameter q is required'], 400);
        }

        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $startTime = microtime(true);
        $user = $this->searchService->getRoutedUser($identifier, $type);
        $executionTime = microtime(true) - $startTime;

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
                'latency' => number_format($executionTime, 5) . 's'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user,
            'meta' => [
                'execution_time' => number_format($executionTime, 5) . 's',
                'shard' => $user->shard_key
            ]
        ]);
    }
}
