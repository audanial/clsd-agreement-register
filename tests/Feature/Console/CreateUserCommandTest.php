<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_a_user_with_the_given_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Test Legal',
            '--email' => 'legal-test@unikl.edu.my',
            '--role' => 'legal',
        ])
            ->expectsQuestion('Password', 'secret123')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'legal-test@unikl.edu.my',
            'role' => 'legal',
            'name' => 'Test Legal',
        ]);

        $user = User::where('email', 'legal-test@unikl.edu.my')->first();
        $this->assertTrue($user->is_active);
    }

    public function test_command_rejects_an_invalid_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Bad Role',
            '--email' => 'bad-role@unikl.edu.my',
            '--role' => 'superuser',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'bad-role@unikl.edu.my',
        ]);
    }

    public function test_command_rejects_a_duplicate_email(): void
    {
        User::factory()->admin()->create([
            'email' => 'duplicate@unikl.edu.my',
        ]);

        $this->artisan('user:create', [
            '--name' => 'Duplicate',
            '--email' => 'duplicate@unikl.edu.my',
            '--role' => 'legal',
        ])->assertFailed();
    }
}
