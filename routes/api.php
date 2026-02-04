<?php

use App\Http\Controllers\CourierController;
use Illuminate\Support\Facades\Route;

Route::apiResource('couriers', CourierController::class)->only([
    'index',
    'show',
    'store',
    'update',
    'destroy',
]);
