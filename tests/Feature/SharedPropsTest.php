<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_safe_user_fields_are_sent_to_the_browser()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
            );
    }

    public function test_guests_have_no_user()
    {
        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('auth.user', null));
    }
}
