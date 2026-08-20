<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    public function test_registration_route_does_not_exist(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }
}
