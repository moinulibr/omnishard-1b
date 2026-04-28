<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Test\RedisTestIngControllet;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/redis-test', [RegisterController::class, 'redisTest']);

Route::get('search-airlines',[RedisTestIngControllet::class,'searchAirlines'])->name('search.airlines');
Route::post('flight-book',[RedisTestIngControllet::class, 'bookFlight'])->name('flight.book');
Route::get('flight-booked-succes',[RedisTestIngControllet::class, 'bookedFlightSuccess'])->name('flight.booked.succes');