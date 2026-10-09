<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\AccountDrawer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function security_headers_are_sent_and_checkout_may_use_geolocation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');
    }

    #[Test]
    public function an_unknown_page_shows_the_branded_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found');
    }

    #[Test]
    public function the_privacy_page_shows_the_contact_email_and_the_deletion_promise(): void
    {
        config(['services.contact.email' => 'hello@example.test']);

        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('hello@example.test')
            ->assertSee('delete your personal data');
    }

    #[Test]
    public function switching_google_login_off_closes_the_routes(): void
    {
        config(['services.google.enabled' => false]);

        $this->get(route('auth.google'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
    }

    #[Test]
    public function switching_google_login_off_hides_the_sign_in_button(): void
    {
        config(['services.google.enabled' => false]);

        Livewire::test(AccountDrawer::class)->assertDontSee('Sign in');
    }
}