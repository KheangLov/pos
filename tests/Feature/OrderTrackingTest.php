<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CreatesPosFixtures;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use CreatesPosFixtures;
    use RefreshDatabase;

    private function fixtureTable(): array
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $user = $this->makeUser($company, $branch);
        $product = $this->makeProduct($company, 5.00, 'latte');
        $this->stockIn($branch, $product, 10);
        $table = $this->makeTable($this->makeFloorPlan($branch));

        return compact('company', 'branch', 'user', 'product', 'table');
    }

    private function openInvoice(array $f): Invoice
    {
        return Invoice::create([
            'branch_id' => $f['branch']->id,
            'user_id' => $f['user']->id,
            'table_id' => $f['table']->id,
            'status' => 'pending',
            'subtotal' => 5.00,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5.00,
        ]);
    }

    /**
     * P1-2 regression: the tracking page is reachable only with the owning
     * table's UUID — the invoice id alone must not resolve.
     */
    public function test_tracking_requires_the_table_uuid(): void
    {
        $f = $this->fixtureTable();
        $invoice = $this->openInvoice($f);

        // Correct table UUID → the page loads.
        $this->get(route('order.tracking', ['tableUuid' => $f['table']->uuid, 'invoice' => $invoice->id]))
            ->assertOk();

        // Any other table's UUID (or a random one) → 404, not a leak.
        $other = $this->makeTable($this->makeFloorPlan($f['branch']));

        $this->get(route('order.tracking', ['tableUuid' => $other->uuid, 'invoice' => $invoice->id]))
            ->assertNotFound();

        // Random uuid, valid id — still 404.
        $this->get(route('order.tracking', ['tableUuid' => (string) Str::uuid(), 'invoice' => $invoice->id]))
            ->assertNotFound();
    }
}
