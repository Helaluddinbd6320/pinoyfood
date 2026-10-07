<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderDoneController;


Route::view('/', 'storefront.home')->name('home');
Route::view('/checkout', 'storefront.checkout')->name('checkout');
Route::get('/order/{orderNo}', OrderDoneController::class)
    ->middleware('signed')
    ->name('order.done');