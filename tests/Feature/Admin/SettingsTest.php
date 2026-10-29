<?php

namespace Tests\Feature\Admin;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_change_settings()
    {
        foreach ([User::factory()->buyer()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.update'), ['reminder_email' => 'x@example.com'])->assertForbidden();
        }
    }

    public function test_admin_can_set_and_clear_the_reminder_email()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), ['reminder_email' => 'Procurement@Example.com'])->assertSessionHasNoErrors();
        $this->assertSame('procurement@example.com', Setting::get(SettingKey::ReminderEmail));

        $this->get(route('admin.settings.edit'))
            ->assertInertia(fn ($page) => $page->where('reminderEmail', 'procurement@example.com'));

        $this->put(route('admin.settings.update'), ['reminder_email' => '']);
        $this->assertNull(Setting::get(SettingKey::ReminderEmail));
    }

    public function test_the_reminder_email_must_be_valid()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.settings.update'), ['reminder_email' => 'not an email'])
            ->assertSessionHasErrors('reminder_email');
    }
}
