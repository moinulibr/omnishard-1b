<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use ApiResponse;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Global List of Users.
     */
    public function index(Request $request)
    {
        //return $lastId = (int) request()->input('per_page', 15);
        //return $lastId = (int) request()->input('last_id', 0);
        // One line call - Dynamic and Scalable
        $users = $this->userService->getGlobalUsersData(
            request()->input('per_page', 15)
        );

        return response()->json($users);
        
        $users = $this->userService->getGlobalUsers($request->get('per_page', 15));
        return UserResource::collection($users);
    }

    /**
     * Search User - Protected Route.
     */
    public function protecedSearch(Request $request)
    {
        $request->validate(['identifier' => 'required']);
        $user = $this->userService->searchUser($request->identifier);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return new UserResource($user);
    }


    public function search(Request $request): JsonResponse
    {
        $user = $this->userService->findUserByIdentifier($request->q);
        return $user
            ? $this->successResponse(new UserResource($user), 'User found')
            : $this->errorResponse('User not found', 404);
    }

    public function show($id): JsonResponse
    {
        $user = $this->userService->findUserById($id);
        return $user
            ? $this->successResponse(new UserResource($user), 'User found')
            : $this->errorResponse('User not found', 404);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->registerUser($request->validated());
            return $this->successResponse(new UserResource($user), 'User registered successfully', [], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', 500, $e->getMessage());
        }
    }

    

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->userService->updateUser($id, $request->all());
        return $user
            ? $this->successResponse(new UserResource($user), 'User updated successfully')
            : $this->errorResponse('Update failed', 400);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser($id);
        return $deleted
            ? $this->successResponse(null, 'User deleted successfully')
            : $this->errorResponse('Delete failed', 400);
    }

    /**
     * Logout User.
     */
    public function logout(Request $request)
    {
        $this->userService->logoutUser($request);
        return response()->json(['message' => 'Logged out successfully']);
    }
}
