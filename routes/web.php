<?php

use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/redis-test', [RegisterController::class, 'redisTest']);

Route::get('search-airlines',[RegisterController::class,'searchAirlines'])->name('search.airlines');
Route::post('flight-book',[RegisterController::class, 'bookFlight'])->name('flight.book');
Route::get('flight-booked-succes',[RegisterController::class, 'bookedFlightSuccess'])->name('flight.booked.succes');