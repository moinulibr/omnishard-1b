<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use App\Rules\BdPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponse;
use App\Utils\IdGenerator;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    use ApiResponse;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function login(Request $request)
    {
        //return IdGenerator::generate('user');
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        try {
            $data = $this->userService->login($request->email, $request->password);
            //$token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => new UserResource($data['user']),
                'access_token' => $data['token'],
                'token_type' => 'Bearer'
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed - ', 401, $e->getMessage());
        }
    }
}
