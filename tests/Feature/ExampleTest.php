<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_guests_to_login(): void
    {
        $response = $this->followingRedirects()->get('/');

        $response->assertOk();
        $response->assertViewIs('auth.login');
    }
}
