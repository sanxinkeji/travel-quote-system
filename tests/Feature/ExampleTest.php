<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_home_page_routes_guests_through_the_quote_library_to_login(): void
    {
        $this->get('/')->assertRedirect('/quotes');

        $this->get('/quotes')->assertRedirect('/login');
    }
}
