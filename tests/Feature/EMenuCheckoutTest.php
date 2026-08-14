<?php

namespace Tests\Feature;

use App\Livewire\EMenu;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StockTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Feature\Concerns\CreatesPosFixtures;
use Tests\TestCase;

class EMenuCheckoutTest extends TestCase
{
    use CreatesPosFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // checkout() rate-limits per IP (20 / 60s); keep tests independent.
        RateLimiter::clear('emenu-checkout:127.0.0.1');
    }

    private function fixtureTable(array $permissions = []): array
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);
        $user = $this->makeUser($company, $branch, $permissions);
        $this->makeTax($company, 10);
        $product = $this->makeProduct($company, 5.00, 'latte');
        $this->stockIn($branch, $product, 10);
        $floor = $this->makeFloorPlan($branch);
        $table = $this->makeTable($floor);

        return compact('company', 'branch', 'user', 'product', 'table');
    }

    /**
     * P1-6 regression (eMenu side): a second order on the same table must
     * still go through (guests order more), and must not flip the status.
     */
    public function test_ordering_on_an_already_occupied_table_still_works(): void
    {
        $f = $this->fixtureTable();
        $f['table']->update(['status' => 'occupied']);

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 1, 5.00)])
            ->call('checkout');

        $this->assertSame(1, Invoice::count());
        $this->assertSame('occupied', $f['table']->refresh()->status);
    }

    /**
     * P2: a deactivated floor plan takes its tables offline — the table QR
     * resolves to 404 instead of an order form.
     */
    public function test_emenu_table_is_not_reachable_on_an_inactive_floor_plan(): void
    {
        $f = $this->fixtureTable();
        $f['table']->floorPlan->update(['is_active' => false]);

        $this->get(route('emenu.table', ['uuid' => $f['table']->uuid]))
            ->assertNotFound();
    }

    /**
     * P2: absurd cart quantities are capped server-side at 99.
     */
    public function test_emenu_cart_quantity_is_capped_at_99(): void
    {
        $f = $this->fixtureTable();
        $this->stockIn($f['branch'], $f['product'], 100); // on hand 110, cap 99

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 500, 5.00)])
            ->call('checkout');

        $invoice = Invoice::sole();
        $this->assertSame(99, (int) $invoice->orderItems()->sole()->quantity);
    }

    public function test_emenu_checkout_creates_invoice_occupies_table_and_decrements_stock(): void
    {
        $f = $this->fixtureTable();

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 2, 5.00)])
            ->call('checkout');

        $invoice = Invoice::sole();
        $this->assertSame('pending', $invoice->status);
        $this->assertSame($f['table']->id, $invoice->table_id);
        // Attribution goes to a system user in the branch (no guest auth).
        $this->assertSame($f['user']->id, $invoice->user_id);
        // 2 × 5.00 + 10% tax = 11.00
        $this->assertSame('11.00', (string) $invoice->total);
        $this->assertSame('occupied', $f['table']->refresh()->status);
        $this->assertSame(8, StockTransaction::onHand($f['branch']->id, $f['product']->id));
    }

    /**
     * P1-4 regression: "pay at counter" (no method selected) must still create
     * a pending cash payment so the cashier's POS "Pending payments" modal can
     * surface and confirm it. Previously no Payment row was created at all and
     * the order was invisible to the cashier.
     */
    public function test_emenu_checkout_without_method_creates_pending_cash_payment(): void
    {
        $f = $this->fixtureTable();

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 1, 5.00)])
            ->call('checkout');

        $payment = Payment::sole();
        $this->assertSame('cash', $payment->method);
        $this->assertNull($payment->payment_method_id);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('5.50', (string) $payment->amount);
    }

    public function test_emenu_checkout_with_selected_method_links_payment_type(): void
    {
        $f = $this->fixtureTable();
        $method = $this->makePaymentMethod($f['company'], 'Bakong QR', 'khqr');

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 1, 5.00)])
            ->set('selectedPaymentMethod', $method->id)
            ->call('checkout');

        $payment = Payment::sole();
        $this->assertSame($method->id, $payment->payment_method_id);
        $this->assertSame('Bakong QR', $payment->method);
        $this->assertSame('pending', $payment->status);
    }

    /**
     * P0-2 regression (eMenu side): same as POS — no negative stock.
     */
    public function test_emenu_checkout_is_blocked_when_stock_is_insufficient(): void
    {
        $f = $this->fixtureTable();
        // Reduce on hand to 1, then try to order 2.
        StockTransaction::where('product_id', $f['product']->id)->delete();
        $this->stockIn($f['branch'], $f['product'], 1);

        Livewire::test(EMenu::class, ['uuid' => $f['table']->uuid])
            ->set('cart', [$this->emenuCartLine($f['product']->id, 2, 5.00)])
            ->call('checkout');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, StockTransaction::onHand($f['branch']->id, $f['product']->id));
        $this->assertSame('available', $f['table']->refresh()->status);
    }
}
