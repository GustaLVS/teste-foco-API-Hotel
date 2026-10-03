<?php

use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReservationController;

Route::apiResource('rooms', RoomController::class);
Route::post('reservations', [ReservationController::class, 'store'])
    ->name('reservations.store');