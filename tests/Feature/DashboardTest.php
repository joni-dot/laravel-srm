<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\Currency;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\SupplierRating;
use App\Models\SupplierSpend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_viewers_never_receive_contract_or_spend_data()
    {
        $contract = Contract::factory()->endsInDays(5)->create(['title' => 'Secret agreement']);
        SupplierSpend::factory()->for($contract->supplier)->create(['year' => today()->year]);

        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attention.contracts_action_soon', null)
            ->where('topSpend', null)
            ->where('upcoming', []));
        $response->assertDontSee('Secret agreement');
    }

    public function test_buyers_see_upcoming_contracts_and_spend()
    {
        $contract = Contract::factory()->endsInDays(5)->create();
        SupplierSpend::factory()->for($contract->supplier)->create(['year' => today()->year, 'amount' => '500.00', 'currency_code' => Currency::Eur]);

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attention.contracts_action_soon', 1)
                ->where('upcoming.0.kind', 'contract_ends')
                ->where('upcoming.0.contract_id', $contract->id)
                ->where('topSpend.0.currency_code', 'EUR')
                ->where('topSpend.0.suppliers.0.id', $contract->supplier_id));
    }

    public function test_each_attention_count_matches_the_supplier_list_it_links_to()
    {
        $required = CertificateType::factory()->required()->create();

        $missingCertificate = Supplier::factory()->active()->create();
        SupplierRating::factory()->create(['supplier_id' => $missingCertificate->id, 'rated_on' => today()]);

        $expiring = Supplier::factory()->active()->create();
        Certificate::factory()->for($expiring)->for($required, 'type')->expiresInDays(10)->create();

        $contractSupplier = Supplier::factory()->create(['status' => SupplierStatus::Prospect]);
        Contract::factory()->for($contractSupplier)->endsInDays(10)->create();
        Contract::factory()->for($contractSupplier)->endsInDays(20)->create();

        $archived = Supplier::factory()->active()->archived()->create();
        Contract::factory()->for($archived)->endsInDays(10)->create();

        $links = [
            'contracts_action_soon' => ['contracts_action_soon' => 1],
            'missing_certificates' => ['missing_certificates' => 1],
            'certificates_expiring' => ['status' => 'active', 'certificates_expiring' => 1],
            'high_risk' => ['status' => 'active', 'risk' => 'high'],
            'rating_overdue' => ['rating_overdue' => 1],
        ];

        $buyer = User::factory()->buyer()->create();
        $attention = $this->actingAs($buyer)->get(route('dashboard'))->viewData('page')['props']['attention'];

        $this->assertSame(1, $attention['contracts_action_soon']);
        $this->assertSame(1, $attention['certificates_expiring']);
        $this->assertSame(1, $attention['missing_certificates']);
        $this->assertSame(1, $attention['high_risk']);
        $this->assertSame(1, $attention['rating_overdue']);

        foreach ($links as $key => $query) {
            $this->actingAs($buyer)
                ->get(route('suppliers.index', $query))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('suppliers.total', $attention[$key]));
        }
    }

    public function test_risk_and_status_counts_leave_out_archived_suppliers()
    {
        Supplier::factory()->create(['status' => SupplierStatus::Prospect]);
        Supplier::factory()->create(['status' => SupplierStatus::Blocked]);
        Supplier::factory()->archived()->create(['status' => SupplierStatus::Blocked]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('statuses', [
                    ['status' => 'prospect', 'count' => 1],
                    ['status' => 'active', 'count' => 0],
                    ['status' => 'inactive', 'count' => 0],
                    ['status' => 'blocked', 'count' => 1],
                ])
                ->where('riskLevels', [
                    ['level' => RiskLevel::High->value, 'count' => 1],
                    ['level' => RiskLevel::Medium->value, 'count' => 0],
                    ['level' => RiskLevel::Low->value, 'count' => 1],
                ]));
    }

    public function test_upcoming_lists_the_soonest_dates_first_and_skips_what_needs_no_action()
    {
        $type = CertificateType::factory()->create(['name' => 'ISO 9001']);
        $supplier = Supplier::factory()->active()->create();

        $certificate = Certificate::factory()->for($supplier)->for($type, 'type')->expiresInDays(3)->create();
        $contract = Contract::factory()->for($supplier)->endsInDays(7)->create();

        Contract::factory()->for($supplier)->endsInDays(29)->create(['status' => ContractStatus::Draft]);
        Contract::factory()->for($supplier)->endsInDays(30)->create();

        $replaced = Supplier::factory()->active()->create();
        Certificate::factory()->for($replaced)->for($type, 'type')->expiresInDays(2)->create();
        Certificate::factory()->for($replaced)->for($type, 'type')->expiresInDays(400)->create();

        $inactive = Supplier::factory()->create(['status' => SupplierStatus::Inactive]);
        Certificate::factory()->for($inactive)->for($type, 'type')->expiresInDays(1)->create();

        $alreadyExpired = Supplier::factory()->active()->create();
        Certificate::factory()->for($alreadyExpired)->for($type, 'type')->expired()->create();

        $this->actingAs(User::factory()->buyer()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('upcoming', 2)
                ->where('upcoming.0.key', "certificate-{$certificate->id}")
                ->where('upcoming.0.title', 'ISO 9001')
                ->where('upcoming.1.key', "contract-{$contract->id}"));
    }

    public function test_a_rating_is_overdue_after_twelve_months_or_when_there_is_none()
    {
        $neverRated = Supplier::factory()->active()->create();

        $ratedLongAgo = Supplier::factory()->active()->create();
        SupplierRating::factory()->create(['supplier_id' => $ratedLongAgo->id, 'rated_on' => today()->subMonths(12)->subDay()]);

        $ratedRecently = Supplier::factory()->active()->create();
        SupplierRating::factory()->create(['supplier_id' => $ratedRecently->id, 'rated_on' => today()->subMonths(12)]);

        Supplier::factory()->create(['status' => SupplierStatus::Inactive]);

        $this->assertEqualsCanonicalizing(
            [$neverRated->id, $ratedLongAgo->id],
            Supplier::query()->ratingOverdue()->pluck('id')->all(),
        );
    }

    public function test_top_spend_ranks_the_chosen_year_separately_for_each_currency()
    {
        $spend = fn (string $amount, Currency $currency, ?int $year = null, array $supplier = []) => SupplierSpend::factory()
            ->for(Supplier::factory()->create($supplier))
            ->create(['amount' => $amount, 'currency_code' => $currency, 'year' => $year ?? today()->year]);

        foreach (range(1, 6) as $thousands) {
            $spend("{$thousands}000.00", Currency::Eur);
        }

        $spend('9.00', Currency::Usd);
        $spend('99999.00', Currency::Eur, today()->year - 1);
        $spend('99999.00', Currency::Eur, null, ['archived_at' => now()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('topSpend', 2)
                ->where('topSpend.0.currency_code', 'EUR')
                ->has('topSpend.0.suppliers', 5)
                ->where('topSpend.0.suppliers.0.amount', '6000.00')
                ->where('topSpend.0.suppliers.4.amount', '2000.00')
                ->where('topSpend.1.currency_code', 'USD'));
    }
}
