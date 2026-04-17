<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Routes
//Route::post('/register', [AuthController::class, 'register']);
//Route::post('/login', [AuthController::class, 'login']);
Route::post('/login', [UserController::class, 'login']);


Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    // User list (GET)
    Route::get('/users', [UserController::class, 'index']); // Paginated List
    // User list (GET)
    Route::get('/users/search', [UserController::class, 'search']); // Search Resource
    // Search User (GET)
    Route::get('/search', [UserController::class, 'search']);
    // Register/Store User (POST)
    Route::post('store', [UserController::class, 'store']);
    //user update
    Route::put('/{id}', [UserController::class, 'update']);
    //delete user
    Route::delete('/{id}', [UserController::class, 'destroy']);

    Route::post('/logout', [UserController::class, 'logout']);
});

