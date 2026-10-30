<?php

namespace Tests\Feature\Ratings;

use App\Compliance\SupplierRisk;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Contract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierRiskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds one supplier for each rule and returns them with the level they should get.
     *
     * @return array<string, array{Supplier, RiskLevel}>
     */
    private function scenarios(): array
    {
        $required = CertificateType::factory()->required()->create(['name' => 'Code of conduct']);
        $optional = CertificateType::factory()->create(['name' => 'ISO 9001']);

        $withRequired = function (array $attributes = []) use ($required): Supplier {
            $supplier = Supplier::factory()->active()->create($attributes);
            Certificate::factory()->for($supplier)->for($required, 'type')->create();

            return $supplier;
        };

        $expiredButReplaced = $withRequired();
        Certificate::factory()->for($expiredButReplaced)->for($optional, 'type')->expired()->create();
        Certificate::factory()->for($expiredButReplaced)->for($optional, 'type')->create();

        $expiringSoon = $withRequired();
        Certificate::factory()->for($expiringSoon)->for($optional, 'type')->expiresInDays(10)->create();

        $expiring30Days = $withRequired();
        Certificate::factory()->for($expiring30Days)->for($optional, 'type')->expiresInDays(30)->create();

        $expired = $withRequired();
        Certificate::factory()->for($expired)->for($optional, 'type')->expired()->create();

        $overriddenUp = $withRequired();
        $overriddenUp->risk_override = RiskLevel::High;
        $overriddenUp->save();

        $overriddenDown = Supplier::factory()->create(['status' => SupplierStatus::Blocked]);
        $overriddenDown->risk_override = RiskLevel::Low;
        $overriddenDown->save();

        return [
            'clean, unrated' => [$withRequired(), RiskLevel::Low],
            'prospect without certificates' => [Supplier::factory()->create(['status' => SupplierStatus::Prospect]), RiskLevel::Low],
            'blocked' => [Supplier::factory()->create(['status' => SupplierStatus::Blocked]), RiskLevel::High],
            'missing a required certificate' => [Supplier::factory()->active()->create(), RiskLevel::High],
            'rating just below high' => [$this->withRating($withRequired(), '2.49'), RiskLevel::High],
            'rating at the high limit' => [$this->withRating($withRequired(), '2.50'), RiskLevel::Medium],
            'rating just below medium' => [$this->withRating($withRequired(), '3.49'), RiskLevel::Medium],
            'rating at the medium limit' => [$this->withRating($withRequired(), '3.50'), RiskLevel::Low],
            'expired, not replaced' => [$expired, RiskLevel::Medium],
            'expired, but replaced' => [$expiredButReplaced, RiskLevel::Low],
            'expiring within 30 days' => [$expiringSoon, RiskLevel::Medium],
            'expiring in exactly 30 days' => [$expiring30Days, RiskLevel::Low],
            'overridden upwards' => [$overriddenUp, RiskLevel::High],
            'overridden downwards' => [$overriddenDown, RiskLevel::Low],
        ];
    }

    public function test_the_calculation_and_the_list_filter_agree_on_every_rule()
    {
        $scenarios = $this->scenarios();
        $suppliers = collect($scenarios)->map(fn (array $scenario) => $scenario[0]->fresh())->values();
        $assessments = app(SupplierRisk::class)->assess($suppliers);

        foreach ($scenarios as $name => [$supplier, $expected]) {
            $this->assertSame($expected, $assessments[$supplier->id]['level'], "Calculation: {$name}");

            foreach (RiskLevel::cases() as $level) {
                $this->assertSame(
                    $level === $expected,
                    Supplier::query()->whereKey($supplier->id)->withRiskLevel($level)->exists(),
                    "Filter: {$name} at {$level->value}",
                );
            }
        }
    }

    public function test_signals_explain_the_level()
    {
        CertificateType::factory()->required()->create(['name' => 'Code of conduct']);
        $supplier = $this->withRating(Supplier::factory()->active()->create(), '2.00');

        $assessment = app(SupplierRisk::class)->assess(collect([$supplier->fresh()]))[$supplier->id];

        $this->assertSame(RiskLevel::High, $assessment['calculated_level']);
        $this->assertSame(
            ['The latest rating is 2.00 out of 5', 'Missing a valid required certificate: Code of conduct'],
            array_column($assessment['signals'], 'message'),
        );
    }

    public function test_an_override_keeps_the_calculated_level_visible()
    {
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Blocked]);
        $this->actingAs(User::factory()->buyer()->create());

        $this->put(route('suppliers.risk.update', $supplier), ['level' => 'low', 'reason' => 'Dispute settled.'])
            ->assertSessionHasNoErrors();

        $this->get(route('suppliers.show', $supplier))
            ->assertInertia(fn ($page) => $page
                ->where('supplier.risk.level', 'low')
                ->where('supplier.risk.calculated_level', 'high')
                ->where('supplier.risk.is_overridden', true)
                ->where('supplier.risk.reason', 'Dispute settled.')
            );

        $this->delete(route('suppliers.risk.destroy', $supplier))->assertSessionHasNoErrors();

        $supplier->refresh();
        $this->assertNull($supplier->risk_override);
        $this->assertNull($supplier->risk_override_reason);
    }

    public function test_an_override_needs_a_valid_level_and_a_reason()
    {
        $this->actingAs(User::factory()->buyer()->create())
            ->put(route('suppliers.risk.update', Supplier::factory()->create()), ['level' => 'extreme', 'reason' => ''])
            ->assertSessionHasErrors(['level', 'reason']);
    }

    public function test_the_list_can_be_filtered_by_risk_and_ignores_unknown_values()
    {
        Supplier::factory()->create(['name' => 'Risky', 'status' => SupplierStatus::Blocked]);
        Supplier::factory()->create(['name' => 'Fine', 'status' => SupplierStatus::Prospect]);
        $this->actingAs(User::factory()->create());

        $this->get(route('suppliers.index', ['risk' => 'high']))
            ->assertInertia(fn ($page) => $page
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Risky')
                ->where('suppliers.data.0.risk_level', 'high')
            );

        $this->get(route('suppliers.index', ['risk' => 'nonsense']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('suppliers.data', 2));
    }

    public function test_contracts_never_affect_the_risk_level()
    {
        $supplier = Supplier::factory()->create(['status' => SupplierStatus::Prospect]);
        Contract::factory()->for($supplier)->endsInDays(-1)->create();

        $assessment = app(SupplierRisk::class)->assess(collect([$supplier->fresh()]))[$supplier->id];

        $this->assertSame(RiskLevel::Low, $assessment['level']);
        $this->assertSame([], $assessment['signals']);
    }

    public function test_risk_fields_cannot_be_set_through_the_supplier_form()
    {
        $this->actingAs(User::factory()->buyer()->create())->post(route('suppliers.store'), [
            'name' => 'New Oy',
            'status' => 'active',
            'country_code' => 'SE',
            'risk_override' => 'low',
            'latest_rating_score' => '5.00',
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::sole();
        $this->assertNull($supplier->risk_override);
        $this->assertNull($supplier->latest_rating_score);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_suppliers()
    {
        CertificateType::factory()->required()->create();
        Supplier::factory()->active()->count(25)->create();
        $this->actingAs(User::factory()->create());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->get(route('suppliers.index'))->assertOk();

        $this->assertLessThanOrEqual(10, $queries);
    }

    private function withRating(Supplier $supplier, string $score): Supplier
    {
        $supplier->latest_rating_score = $score;
        $supplier->save();

        return $supplier;
    }
}
