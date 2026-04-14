<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSearchRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Class UserController
 * Manages user-related operations including registration and sharded search.
 */
class UserController extends Controller
{
    use ApiResponse;

    /** @var UserService */
    protected $userService;

    /**
     * UserController constructor.
     * * @param UserService $userService
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Search for a user across shards using an identifier (email/phone).
     *
     * @param UserSearchRequest $request
     * @return JsonResponse
     */
    public function search(UserSearchRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        $user = $this->userService->findUserByIdentifier($request->q);

        $latency = number_format(microtime(true) - $startTime, 5);

        return $user
            ? $this->successResponse(new UserResource($user), 'User found', ['latency' => "{$latency}s"])
            : $this->errorResponse('User not found', 404);
    }

    /**
     * Register a new user and distribute the data across available shards.
     *
     * @param UserStoreRequest $request
     * @return JsonResponse
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        try {
            // Using the correctly named property $userService
            $user = $this->userService->registerUser($request->validated());

            return $this->successResponse(
                new UserResource($user),
                'User registered and sharded successfully',
                [],
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', 500, $e->getMessage());
        }
    }
}
