<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(CustomerAccountService $accounts, Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('home')
                ->with('auth_error', 'Google sign-in was cancelled or failed. Please try again.');
        }

        $raw = is_array($google->user ?? null) ? $google->user : [];

        $customer = $accounts->signInWithGoogle(
            googleId: (string) $google->getId(),
            email: $google->getEmail(),
            emailVerified: (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false),
            name: $google->getName(),
            avatar: $google->getAvatar(),
        );

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate(); // keeps the cart, new session id

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->forget('phone_prompt_done');
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}