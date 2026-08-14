<?php

namespace Tests\Feature;

use App\Filament\Pages\Kds;
use App\Models\Invoice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\CreatesPosFixtures;
use Tests\TestCase;

class KdsTableLifecycleTest extends TestCase
{
    use CreatesPosFixtures;
    use RefreshDatabase;

    private $user;

    private $branch;

    private $table;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $company = $this->makeCompany();
        $this->branch = $this->makeBranch($company);
        $this->user = $this->makeUser($company, $this->branch, ['update_order_item']);
        $floor = $this->makeFloorPlan($this->branch);
        $this->table = $this->makeTable($floor);
        $this->table->update(['status' => 'occupied']);
    }

    private function openInvoice(): Invoice
    {
        return Invoice::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'table_id' => $this->table->id,
            'status' => 'pending',
            'subtotal' => 5.00,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5.00,
        ]);
    }

    public function test_completing_the_last_open_order_frees_the_table(): void
    {
        $invoice = $this->openInvoice();

        Livewire::actingAs($this->user)
            ->test(Kds::class)
            ->call('setStatus', $invoice->id, 'completed');

        $this->assertSame('available', $this->table->refresh()->status);
    }

    /**
     * P1-6 regression: a table with two open invoices must stay occupied after
     * the first is served; previously the first completion freed the table
     * while a second order was still live on it.
     */
    public function test_completing_one_of_two_open_orders_keeps_the_table_occupied(): void
    {
        $first = $this->openInvoice();
        $second = $this->openInvoice();

        Livewire::actingAs($this->user)
            ->test(Kds::class)
            ->call('setStatus', $first->id, 'completed');

        $this->assertSame('occupied', $this->table->refresh()->status);

        Livewire::actingAs($this->user)
            ->test(Kds::class)
            ->call('setStatus', $second->id, 'completed');

        $this->assertSame('available', $this->table->refresh()->status);
    }
}
