<?php

namespace Tests\Feature\Certificates;

use App\Enums\SettingKey;
use App\Models\Certificate;
use App\Models\CertificateReminder;
use App\Models\CertificateType;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\CertificatesExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendCertificateRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_due_reminders_go_to_the_responsible_buyer_in_one_email()
    {
        $buyer = User::factory()->buyer()->create(['email' => 'buyer@example.com']);
        $supplier = Supplier::factory()->create(['responsible_user_id' => $buyer->id]);
        $first = $this->certificateWithReminderDueToday($supplier);
        $second = $this->certificateWithReminderDueToday($supplier);

        $this->artisan('app:send-certificate-reminders')->assertSuccessful();

        $this->assertSentOnceTo('buyer@example.com', 2);
        $this->assertSame(0, CertificateReminder::whereNull('sent_at')->count());
        $this->assertNotNull($first->reminders()->value('sent_at'));
        $this->assertNotNull($second->reminders()->value('sent_at'));
    }

    public function test_without_a_responsible_buyer_reminders_go_to_the_reminder_email()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');
        $this->certificateWithReminderDueToday(Supplier::factory()->create());

        $this->artisan('app:send-certificate-reminders');

        $this->assertSentOnceTo('procurement@example.com', 1);
    }

    public function test_a_deactivated_responsible_buyer_does_not_get_reminders()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');
        $buyer = User::factory()->buyer()->deactivated()->create();
        $this->certificateWithReminderDueToday(Supplier::factory()->create(['responsible_user_id' => $buyer->id]));

        $this->artisan('app:send-certificate-reminders');

        $this->assertSentOnceTo('procurement@example.com', 1);
    }

    public function test_reminders_without_any_recipient_are_kept_for_later_and_logged()
    {
        Log::spy();
        $this->certificateWithReminderDueToday(Supplier::factory()->create());

        $this->artisan('app:send-certificate-reminders');

        Notification::assertNothingSent();
        $this->assertSame(1, CertificateReminder::whereNull('sent_at')->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_reminders_are_sent_only_once()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');
        $this->certificateWithReminderDueToday(Supplier::factory()->create());

        $this->artisan('app:send-certificate-reminders');
        $this->artisan('app:send-certificate-reminders');

        $this->assertSentOnceTo('procurement@example.com', 1);
    }

    public function test_future_expired_and_archived_cases_are_skipped()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');

        $future = Certificate::factory()->expiresInDays(100)->create();
        $future->reminders()->create(['days_before' => 14, 'remind_on' => today()->addDays(86)]);

        $expired = Certificate::factory()->expired()->create();
        $expired->reminders()->create(['days_before' => 14, 'remind_on' => today()->subDays(15)]);

        $this->certificateWithReminderDueToday(Supplier::factory()->archived()->create());

        $this->artisan('app:send-certificate-reminders');

        Notification::assertNothingSent();
    }

    public function test_the_email_shows_names_exactly_as_typed()
    {
        $supplier = Supplier::factory()->create(['name' => '[Click here](https://evil.example) <b>Oy</b>']);
        $certificate = $this->certificateWithReminderDueToday($supplier);

        $html = (string) (new CertificatesExpiring(collect([$certificate->load('supplier', 'type')])))
            ->toMail(new AnonymousNotifiable)
            ->render();

        $this->assertStringContainsString('[Click here](https://evil.example) &lt;b&gt;Oy&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('href="https://evil.example"', $html);
    }

    private function certificateWithReminderDueToday(Supplier $supplier): Certificate
    {
        $certificate = Certificate::factory()
            ->for($supplier)
            ->for(CertificateType::factory(), 'type')
            ->expiresInDays(14)
            ->create();

        $certificate->reminders()->create(['days_before' => 14, 'remind_on' => today()]);

        return $certificate;
    }

    private function assertSentOnceTo(string $email, int $certificateCount): void
    {
        Notification::assertSentOnDemandTimes(CertificatesExpiring::class, 1);
        Notification::assertSentOnDemand(
            CertificatesExpiring::class,
            fn (CertificatesExpiring $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $email
                && count($notification->items()) === $certificateCount,
        );
    }
}
