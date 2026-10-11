<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\OrderDoneController;
use App\Http\Controllers\OrderPdfController;
use App\Http\Controllers\PaymentProofController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'storefront.home')->name('home');
Route::view('/checkout', 'storefront.checkout')->name('checkout');
Route::view('/whatsapp-order', 'storefront.whatsapp-order')->name('whatsapp.order');
Route::view('/privacy', 'storefront.privacy')->name('privacy');

Route::get('/order/{orderNo}', OrderDoneController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('order.done');

// Step 16: customer receipt PDF (signed, expiring).
Route::get('/order/{orderNo}/receipt', [OrderPdfController::class, 'receipt'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('order.receipt');

// Customers upload a payment screenshot through a signed, expiring link.
Route::get('/pay/{payment}', [PaymentProofController::class, 'upload'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('payment.proof');

// Staff view screenshots (role checked inside the controller).
Route::get('/staff/payment-proof/{payment}', [PaymentProofController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('payments.proof');

// Step 16: staff PDFs (role checked inside the controller).
Route::get('/staff/orders/{order}/receipt', [OrderPdfController::class, 'staffReceipt'])
    ->middleware('throttle:60,1')
    ->name('staff.orders.receipt');
Route::get('/staff/orders/{order}/slip', [OrderPdfController::class, 'slip'])
    ->middleware('throttle:60,1')
    ->name('staff.orders.slip');

Route::get('/auth/google', [GoogleController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.google.callback');
Route::post('/account/logout', [GoogleController::class, 'logout'])
    ->name('customer.logout');