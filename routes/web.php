<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\OrderDoneController;
use App\Http\Controllers\PaymentProofController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');
Route::view('/checkout', 'storefront.checkout')->name('checkout');
Route::view('/whatsapp-order', 'storefront.whatsapp-order')->name('whatsapp.order');
Route::view('/privacy', 'storefront.privacy')->name('privacy');

Route::get('/order/{orderNo}', OrderDoneController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('order.done');

// Customers upload a payment screenshot through a signed, expiring link.
Route::get('/pay/{payment}', [PaymentProofController::class, 'upload'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('payment.proof');

// Staff view screenshots (role checked inside the controller).
Route::get('/staff/payment-proof/{payment}', [PaymentProofController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('payments.proof');

Route::get('/auth/google', [GoogleController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.google.callback');
Route::post('/account/logout', [GoogleController::class, 'logout'])
    ->name('customer.logout');