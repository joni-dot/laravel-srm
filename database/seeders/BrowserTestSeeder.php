<?php

namespace Database\Seeders;

use App\Comments\CommentFeed;
use App\Enums\Currency;
use App\Models\CertificateType;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Starting data for the browser tests in tests/Browser. They read these values
 * from tests/Browser/support/test-data.ts, so change both together.
 */
class BrowserTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Browser test data must never be added to production.');
        }

        User::factory()->admin()->create(['name' => 'Browser Admin', 'email' => 'admin@browser-test.example']);
        $buyer = User::factory()->buyer()->create(['name' => 'Browser Buyer', 'email' => 'buyer@browser-test.example']);
        User::factory()->create(['name' => 'Browser Viewer', 'email' => 'viewer@browser-test.example']);

        CertificateType::factory()->required()->create(['name' => 'ISO 9001']);

        $supplier = Supplier::factory()->active()->create(['name' => 'Browser Test Supplier']);

        $contract = Contract::factory()->create([
            'supplier_id' => $supplier->id,
            'contract_type_id' => ContractType::query()->firstOrFail()->id,
            'title' => 'Confidential browser test contract',
            'value' => 123456,
        ]);

        Comment::factory()->for($supplier)->for($buyer, 'author')->create(['body' => 'Browser test supplier comment']);
        Comment::factory()->aboutContract($contract)->for($buyer, 'author')->create(['body' => 'Secret negotiation note']);

        $busySupplier = Supplier::factory()->active()->create(['name' => 'Busy Comment Supplier']);
        Comment::factory()->for($busySupplier)->for($buyer, 'author')->count(CommentFeed::PAGE_SIZE + 5)
            ->sequence(fn ($sequence) => ['body' => "Comment number {$sequence->index}", 'created_at' => now()->subMinutes($sequence->index)])
            ->create();

        $supplier->spends()->create([
            'year' => today()->year,
            'amount' => 987654,
            'currency_code' => Currency::Eur,
        ]);
    }
}
