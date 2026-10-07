<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never need the compiled CSS/JS, and public/build does not exist on a fresh checkout.
        $this->withoutVite();
    }
}