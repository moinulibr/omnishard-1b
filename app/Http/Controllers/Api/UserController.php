<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSearchRequest;
use App\Http\Resources\UserResource;
use App\Services\UserSearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponse;

    protected $searchService;

    public function __construct(UserSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(UserSearchRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        // 
        $user = $this->searchService->findUserByIdentifier($request->q);

        $latency = number_format(microtime(true) - $startTime, 5);

        return $user
            ? $this->successResponse(new UserResource($user), 'User found', ['latency' => "{$latency}s"])
            : $this->errorResponse('User not found', 404);
    }
}
