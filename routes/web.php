<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckoutController;

Route::get('/', function () {
    return view('welcome');
});
Route::post('/checkout/bad', [CheckoutController::class, 'checkoutWithRaceCondition']);
