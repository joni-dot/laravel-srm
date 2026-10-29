<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_a_hashed_password()
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Anna Buyer')
            ->expectsQuestion('Email', 'Anna@Example.com')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->expectsQuestion('Confirm password', 'correct-horse-battery')
            ->assertSuccessful();

        $user = User::sole();

        $this->assertSame('anna@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_it_rejects_a_taken_email()
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Anna Buyer')
            ->expectsQuestion('Email', 'anna@example.com')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->expectsQuestion('Confirm password', 'correct-horse-battery')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_mismatched_passwords()
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Anna Buyer')
            ->expectsQuestion('Email', 'anna@example.com')
            ->expectsQuestion('Password', 'correct-horse-battery')
            ->expectsQuestion('Confirm password', 'something-else')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
