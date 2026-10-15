<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_the_public_front_page_with_a_login_button(): void
    {
        $this->get('/')->assertOk()->assertSee('Info lomba mahasiswa')->assertSee('href="/login"', false);
    }
}
