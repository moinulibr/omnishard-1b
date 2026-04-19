<?php

use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Routes
Route::post('/registration', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);


Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    // User list (GET)
    Route::get('/', [UserController::class, 'index']); // Paginated List
    // User list (GET)
    Route::get('/users/search', [UserController::class, 'search']); // Search Resource
    // Search User (GET)
    Route::get('/search', [UserController::class, 'search']);
    // get single User (GET)
    Route::get('/show/{id}', [UserController::class, 'show']);
    // Register/Store User (POST)
    Route::post('store', [UserController::class, 'store']);
    //user update
    Route::put('/{id}', [UserController::class, 'update']);
    //delete user
    Route::delete('/{id}', [UserController::class, 'destroy']);
    // Logout - Specific Post Route
    Route::post('/logout', [UserController::class, 'logout']);
});


/*
|--------------------------------------------------------------------------
| Maintenance Routes
|--------------------------------------------------------------------------
*/
Route::prefix('maintenance')->group(function () {
    Route::get('/sync', [MaintenanceController::class, 'syncCount']);
    Route::get('/adjust', [MaintenanceController::class, 'adjust']);
    Route::get('/add-bulk', [MaintenanceController::class, 'addBulk']);
    Route::get('/reset', [MaintenanceController::class, 'reset']);
    Route::get('/status', [MaintenanceController::class, 'getStatus']);
});
