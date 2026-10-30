<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatus;
use App\Enums\SettingKey;
use App\Models\Contract;
use App\Models\ContractReminder;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ContractsNeedAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContractJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_reminders_go_to_the_contract_owner_in_one_email()
    {
        $owner = User::factory()->buyer()->create(['email' => 'owner@example.com']);
        $this->contractWithReminderDueToday(['owner_user_id' => $owner->id]);
        $this->contractWithReminderDueToday(['owner_user_id' => $owner->id]);

        $this->artisan('app:send-contract-reminders')->assertSuccessful();

        $this->assertSentOnceTo('owner@example.com', 2);
        $this->assertSame(0, ContractReminder::whereNull('sent_at')->count());
    }

    public function test_without_an_owner_reminders_go_to_the_responsible_buyer_then_the_reminder_email()
    {
        $buyer = User::factory()->buyer()->create(['email' => 'buyer@example.com']);
        $this->contractWithReminderDueToday([], Supplier::factory()->create(['responsible_user_id' => $buyer->id]));

        $this->artisan('app:send-contract-reminders');
        $this->assertSentOnceTo('buyer@example.com', 1);

        Notification::fake();
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');
        $this->contractWithReminderDueToday();

        $this->artisan('app:send-contract-reminders');
        $this->assertSentOnceTo('procurement@example.com', 1);
    }

    public function test_people_who_may_not_see_contracts_never_get_the_email()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');
        $viewer = User::factory()->create(['email' => 'viewer@example.com']);
        $deactivated = User::factory()->buyer()->deactivated()->create(['email' => 'gone@example.com']);
        $this->contractWithReminderDueToday(['owner_user_id' => $viewer->id]);
        $this->contractWithReminderDueToday(['owner_user_id' => $deactivated->id]);

        $this->artisan('app:send-contract-reminders');

        $this->assertSentOnceTo('procurement@example.com', 2);
    }

    public function test_reminders_without_a_recipient_are_kept_and_logged()
    {
        Log::spy();
        $this->contractWithReminderDueToday();

        $this->artisan('app:send-contract-reminders');

        Notification::assertNothingSent();
        $this->assertSame(1, ContractReminder::whereNull('sent_at')->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_only_due_reminders_of_active_contracts_are_sent_and_only_once()
    {
        Setting::put(SettingKey::ReminderEmail, 'procurement@example.com');

        $this->contractWithReminderDueToday(['status' => ContractStatus::Draft]);
        $this->contractWithReminderDueToday(['status' => ContractStatus::Terminated]);
        $this->contractWithReminderDueToday([], Supplier::factory()->archived()->create());

        $future = Contract::factory()->endsInDays(100)->create();
        $future->reminders()->create(['days_before' => 14, 'remind_on' => today()->addDays(86)]);

        $past = Contract::factory()->endsInDays(-1)->create();
        $past->reminders()->create(['days_before' => 14, 'remind_on' => today()->subDays(15)]);

        $this->artisan('app:send-contract-reminders');
        Notification::assertNothingSent();

        $this->contractWithReminderDueToday();
        $this->artisan('app:send-contract-reminders');
        $this->artisan('app:send-contract-reminders');

        $this->assertSentOnceTo('procurement@example.com', 1);
    }

    public function test_the_email_shows_titles_as_typed_and_leaves_out_prices()
    {
        $contract = $this->contractWithReminderDueToday(['title' => '[Click](https://evil.example) <b>deal</b>', 'value' => 987654]);

        $html = (string) (new ContractsNeedAction(collect([$contract->refresh()->load('supplier')])))
            ->toMail(new AnonymousNotifiable)
            ->render();

        $this->assertStringContainsString('[Click](https://evil.example) &lt;b&gt;deal&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringNotContainsString('987654', $html);
    }

    public function test_an_uncancelled_auto_renewing_contract_rolls_forward_and_gets_new_reminders()
    {
        $contract = Contract::factory()->autoRenewing(termMonths: 12, noticeDays: 30)->create(['ends_on' => today()->subDay()]);
        $contract->reminders()->create(['days_before' => 14, 'remind_on' => today()->subDays(45), 'sent_at' => now()]);

        $this->artisan('app:renew-contracts')->assertSuccessful();

        $contract->refresh();
        $reminder = $contract->reminders()->sole();

        $this->assertSame(today()->subDay()->addMonthsNoOverflow(12)->toDateString(), $contract->ends_on?->toDateString());
        $this->assertNull($reminder->sent_at);
        $this->assertSame($contract->action_by?->subDays(14)->toDateString(), $reminder->remind_on->toDateString());
    }

    public function test_a_contract_that_ended_several_terms_ago_catches_up()
    {
        $contract = Contract::factory()->autoRenewing(termMonths: 1)->create(['ends_on' => today()->subMonthsNoOverflow(3)->subDay()]);

        $this->artisan('app:renew-contracts');

        $this->assertFalse($contract->refresh()->ends_on?->isBefore(today()));
        $this->assertTrue($contract->ends_on?->isBefore(today()->addMonthNoOverflow()->addDay()));
    }

    public function test_other_contracts_are_not_renewed()
    {
        $ended = Contract::factory()->endsInDays(-1)->create();
        $terminated = Contract::factory()->autoRenewing()->endsInDays(-1)->create(['status' => ContractStatus::Terminated]);
        $running = Contract::factory()->autoRenewing()->endsInDays(10)->create();

        $this->artisan('app:renew-contracts');

        $this->assertSame(today()->subDay()->toDateString(), $ended->refresh()->ends_on?->toDateString());
        $this->assertSame(today()->subDay()->toDateString(), $terminated->refresh()->ends_on?->toDateString());
        $this->assertSame(today()->addDays(10)->toDateString(), $running->refresh()->ends_on?->toDateString());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function contractWithReminderDueToday(array $attributes = [], ?Supplier $supplier = null): Contract
    {
        $contract = Contract::factory()
            ->for($supplier ?? Supplier::factory())
            ->endsInDays(14)
            ->create($attributes);

        $contract->reminders()->create(['days_before' => 14, 'remind_on' => today()]);

        return $contract;
    }

    private function assertSentOnceTo(string $email, int $contractCount): void
    {
        Notification::assertSentOnDemandTimes(ContractsNeedAction::class, 1);
        Notification::assertSentOnDemand(
            ContractsNeedAction::class,
            fn (ContractsNeedAction $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $email
                && count($notification->items()) === $contractCount,
        );
    }
}
