<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelloPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Pinoy Food KSA');
    }

    public function test_timezone_is_riyadh(): void
    {
        $this->assertSame('Asia/Riyadh', config('app.timezone'));
    }

    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}