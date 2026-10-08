<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $id = 'g-1', string $email = 'maria@example.com', bool $verified = true): void
    {
        $user = (new SocialiteUser())->map([
            'id' => $id,
            'name' => 'Maria Santos',
            'email' => $email,
            'avatar' => 'https://lh3.example.test/a.jpg',
        ]);
        $user->user = ['email_verified' => $verified];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function the_sign_in_route_redirects_to_google(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->get(route('auth.google'))->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    #[Test]
    public function the_callback_creates_a_customer_and_signs_them_in(): void
    {
        $this->fakeGoogle();

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', ['google_id' => 'g-1', 'email' => 'maria@example.com', 'name' => 'Maria Santos']);
        $this->assertNotEmpty(Customer::query()->firstOrFail()->referral_code);
    }

    #[Test]
    public function signing_in_twice_uses_the_same_customer(): void
    {
        $this->fakeGoogle();
        $this->get(route('auth.google.callback'));
        $this->get(route('auth.google.callback'));

        $this->assertSame(1, Customer::query()->count());
    }

    #[Test]
    public function a_verified_email_matches_an_existing_customer(): void
    {
        $existing = Customer::factory()->create(['email' => 'maria@example.com', 'google_id' => null]);
        $this->fakeGoogle();

        $this->get(route('auth.google.callback'));

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('g-1', $existing->fresh()->google_id);
    }

    #[Test]
    public function an_unverified_email_is_never_matched(): void
    {
        $existing = Customer::factory()->create(['email' => 'maria@example.com', 'google_id' => null]);
        $this->fakeGoogle(verified: false);

        $this->get(route('auth.google.callback'));

        $this->assertSame(2, Customer::query()->count());
        $this->assertNull($existing->fresh()->google_id);
    }

    #[Test]
    public function a_failed_callback_goes_home_with_a_message(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('auth_error');

        $this->assertGuest('customer');
    }

    #[Test]
    public function a_signed_in_customer_cannot_use_the_admin_panel(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->get('/admin')->assertRedirect();
    }

    #[Test]
    public function signing_out_ends_the_customer_session(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest('customer');
    }
}