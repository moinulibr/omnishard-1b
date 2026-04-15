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

    public function store(UserStoreRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->registerUser($request->validated());
            return $this->successResponse(new UserResource($user), 'User registered successfully', [], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', 500, $e->getMessage());
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        try {
            $user = $this->userService->login($request->email, $request->password);
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => new UserResource($user),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed - ', 401, $e->getMessage());
        }
    }

    public function search(Request $request): JsonResponse
    {
        $user = $this->userService->findUserByIdentifier($request->q);
        return $user
            ? $this->successResponse(new UserResource($user), 'User found')
            : $this->errorResponse('User not found', 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $updated = $this->userService->updateUser($id, $request->all());
        return $updated
            ? $this->successResponse(null, 'User updated successfully')
            : $this->errorResponse('Update failed', 400);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser($id);
        return $deleted
            ? $this->successResponse(null, 'User deleted successfully')
            : $this->errorResponse('Delete failed', 400);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, 'Logged out successfully');
    }
}
