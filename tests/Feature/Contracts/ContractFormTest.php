<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractHealth;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractFormTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_the_starting_list_of_contract_types_exists()
    {
        $this->assertSame(5, ContractType::count());
        $this->assertTrue(ContractType::where('name', 'Framework agreement')->exists());
    }

    public function test_a_contract_can_be_created_with_value_and_reminders()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), $this->validInput())
            ->assertSessionHasNoErrors();

        $contract = Contract::sole();

        $this->assertSame($this->supplier->id, $contract->supplier_id);
        $this->assertSame('120000.50', $contract->value);
        $this->assertSame(today()->addDays(200)->toDateString(), $contract->action_by?->toDateString());
        $this->assertEqualsCanonicalizing(
            [today()->addDays(140)->toDateString(), today()->addDays(186)->toDateString()],
            $contract->reminders->map(fn ($reminder) => $reminder->remind_on->toDateString())->all(),
        );
    }

    public function test_for_auto_renewing_contracts_reminders_count_back_from_the_last_day_to_cancel()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [
            ...$this->validInput(),
            'auto_renews' => '1',
            'renewal_term_months' => 12,
            'notice_period_days' => 90,
            'reminder_days' => [30],
        ])->assertSessionHasNoErrors();

        $contract = Contract::sole();

        $this->assertSame(today()->addDays(110)->toDateString(), $contract->action_by?->toDateString());
        $this->assertSame(today()->addDays(80)->toDateString(), $contract->reminders()->sole()->remind_on->toDateString());
    }

    public function test_renewal_details_are_cleared_when_a_contract_does_not_auto_renew()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [
            ...$this->validInput(),
            'renewal_term_months' => 12,
            'notice_period_days' => 90,
        ])->assertSessionHasNoErrors();

        $contract = Contract::sole();

        $this->assertNull($contract->renewal_term_months);
        $this->assertNull($contract->notice_period_days);
        $this->assertSame($contract->ends_on?->toDateString(), $contract->action_by?->toDateString());
    }

    public function test_contracts_without_an_end_date_have_no_reminders()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [...$this->validInput(), 'ends_on' => null])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Contract::sole()->reminders()->count());
    }

    public function test_input_is_validated()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [
            'title' => '',
            'contract_type_id' => 9999,
            'status' => 'expired',
            'owner_user_id' => User::factory()->create()->id,
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-04-01',
            'value' => '-5',
            'currency_code' => 'BTC',
            'reminder_days' => [0],
        ])->assertSessionHasErrors(['title', 'contract_type_id', 'status', 'owner_user_id', 'ends_on', 'value', 'currency_code', 'reminder_days.0']);
    }

    public function test_auto_renewal_needs_an_end_date_and_a_term()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [
            ...$this->validInput(),
            'ends_on' => null,
            'auto_renews' => '1',
            'renewal_term_months' => null,
        ])->assertSessionHasErrors(['ends_on', 'renewal_term_months']);
    }

    public function test_a_value_needs_a_currency_and_a_period()
    {
        $this->post(route('suppliers.contracts.store', $this->supplier), [
            ...$this->validInput(),
            'currency_code' => null,
            'value_period' => null,
        ])->assertSessionHasErrors(['currency_code', 'value_period']);
    }

    public function test_contract_numbers_are_unique()
    {
        Contract::factory()->create(['contract_number' => 'C-1']);

        $this->post(route('suppliers.contracts.store', $this->supplier), [...$this->validInput(), 'contract_number' => 'C-1'])
            ->assertSessionHasErrors('contract_number');
    }

    public function test_a_contract_cannot_be_moved_to_another_supplier_through_the_form()
    {
        $contract = Contract::factory()->for($this->supplier)->create();

        $this->put(route('suppliers.contracts.update', [$this->supplier, $contract]), [
            ...$this->validInput(),
            'supplier_id' => Supplier::factory()->create()->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->supplier->id, $contract->refresh()->supplier_id);
    }

    public function test_the_edit_form_gets_plain_dates()
    {
        $contract = Contract::factory()->for($this->supplier)->create(['starts_on' => '2026-01-15', 'ends_on' => '2027-01-15']);

        $this->get(route('suppliers.contracts.edit', [$this->supplier, $contract]))
            ->assertInertia(fn ($page) => $page
                ->where('contract.starts_on', '2026-01-15')
                ->where('contract.ends_on', '2027-01-15')
            );
    }

    public function test_health_follows_the_status_and_dates()
    {
        $health = fn (array $attributes) => Contract::factory()->create($attributes)->refresh()->health();

        $this->assertSame(ContractHealth::Draft, $health(['status' => ContractStatus::Draft]));
        $this->assertSame(ContractHealth::Terminated, $health(['status' => ContractStatus::Terminated]));
        $this->assertSame(ContractHealth::Active, $health(['ends_on' => null]));
        $this->assertSame(ContractHealth::Active, $health(['ends_on' => today()->addDays(30)]));
        $this->assertSame(ContractHealth::ActionSoon, $health(['ends_on' => today()->addDays(29)]));
        $this->assertSame(ContractHealth::ActionSoon, $health(['ends_on' => today()]));
        $this->assertSame(ContractHealth::Expired, $health(['ends_on' => today()->subDay()]));
        $this->assertSame(
            ContractHealth::Active,
            $health(['ends_on' => today()->addDays(10), 'auto_renews' => true, 'renewal_term_months' => 12, 'notice_period_days' => 90]),
            'The last day to cancel has passed, so it simply renews.',
        );
    }

    public function test_the_action_soon_filter_agrees_with_health()
    {
        $soon = Contract::factory()->endsInDays(29)->create();
        $renewingSoon = Contract::factory()->endsInDays(100)->autoRenewing(noticeDays: 90)->create();
        Contract::factory()->endsInDays(30)->create();
        Contract::factory()->endsInDays(-1)->create();
        Contract::factory()->endsInDays(10)->create(['status' => ContractStatus::Terminated]);

        $this->assertEqualsCanonicalizing([$soon->id, $renewingSoon->id], Contract::query()->needingActionSoon()->pluck('id')->all());
        $this->assertSame(ContractHealth::ActionSoon, $renewingSoon->refresh()->health());
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(): array
    {
        return [
            'title' => 'Steel supply',
            'contract_number' => 'C-100',
            'contract_type_id' => ContractType::query()->value('id'),
            'status' => 'active',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(200)->toDateString(),
            'value' => '120000.50',
            'currency_code' => 'EUR',
            'value_period' => 'yearly',
            'notes' => 'Negotiated in 2026.',
            'reminder_days' => [60, 14],
        ];
    }
}
