<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [UserController::class, 'login']);

Route::prefix('users')->group(function () {
    // Search User (GET)
    Route::get('/search', [UserController::class, 'search']);
    // Register/Store User (POST)
    Route::post('/', [UserController::class, 'store']);
    //user update
    Route::put('/{id}', [UserController::class, 'update']);
    //delete user
    Route::delete('/{id}', [UserController::class, 'destroy']);
});
