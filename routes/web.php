<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\OrderDoneController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');
Route::view('/checkout', 'storefront.checkout')->name('checkout');
Route::view('/privacy', 'storefront.privacy')->name('privacy');

Route::get('/order/{orderNo}', OrderDoneController::class)
    ->middleware('signed')
    ->name('order.done');

Route::get('/auth/google', [GoogleController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.google.callback');
Route::post('/account/logout', [GoogleController::class, 'logout'])
    ->name('customer.logout');