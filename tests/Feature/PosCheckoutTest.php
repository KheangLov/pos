<?php

namespace Tests\Feature;

use App\Filament\Pages\Pos;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\StockTransaction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\CreatesPosFixtures;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use CreatesPosFixtures;
    use RefreshDatabase;

    private $company;

    private $branch;

    private $user;

    private $product;

    private $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->company = $this->makeCompany();
        $this->branch = $this->makeBranch($this->company);
        $this->user = $this->makeUser($this->company, $this->branch);
        $this->makeTax($this->company, 10);
        $this->product = $this->makeProduct($this->company, 5.00, 'latte');
        $this->shift = $this->openShift($this->user, $this->branch, 50);
    }

    public function test_checkout_persists_invoice_totals_items_modifiers_stock_and_payment(): void
    {
        $modifier = $this->makeModifier($this->product, 'Extra Shot', 1.00);
        $discount = $this->makeDiscount($this->company, 'percentage', 10);
        $this->stockIn($this->branch, $this->product, 2);

        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id, 2, [$modifier->id])], null, $discount->id);

        // 2 × (5.00 + 1.00) = 12.00 subtotal → 10% discount = 1.20 →
        // 10% tax on 10.80 = 1.08 → total 11.88
        $invoice = Invoice::sole();
        $this->assertSame('12.00', (string) $invoice->subtotal);
        $this->assertSame('1.20', (string) $invoice->discount_total);
        $this->assertSame('1.08', (string) $invoice->tax_total);
        $this->assertSame('11.88', (string) $invoice->total);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame($this->shift->id, $invoice->shift_id);

        $item = $invoice->orderItems()->sole();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame('6.00', (string) $item->price);
        $this->assertSame('12.00', (string) $item->subtotal);
        $this->assertSame('Extra Shot', $item->modifiers->sole()->name);

        // Sold all 2 units that were on hand.
        $this->assertSame(0, StockTransaction::onHand($this->branch->id, $this->product->id));

        $payment = $invoice->payments()->sole();
        $this->assertSame('11.88', (string) $payment->amount);
        $this->assertSame('successful', $payment->status);
        $this->assertSame('cash', $payment->method);
        $this->assertNull($payment->payment_method_id);
    }

    /**
     * P0-1 regression: reconciliation must key on the payment method's type,
     * never the display name an operator typed. "Bakong QR" (type khqr) used to
     * fall through `where('method', 'qr')` and read zero on the shift report.
     */
    public function test_shift_reconciliation_counts_sales_by_payment_method_type(): void
    {
        $khqr = $this->makePaymentMethod($this->company, 'Bakong QR', 'khqr');
        $card = $this->makePaymentMethod($this->company, 'Visa Card', 'card');
        $cash = $this->makePaymentMethod($this->company, 'Cash', 'cash');
        $this->stockIn($this->branch, $this->product, 3);

        // One checkout per method; each sale is 5.00 + 10% tax = 5.50.
        Livewire::actingAs($this->user)->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id)], $khqr->id, null);
        Livewire::actingAs($this->user)->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id)], $card->id, null);
        Livewire::actingAs($this->user)->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id)], $cash->id, null);

        $shift = $this->shift->refresh();

        $this->assertEqualsWithDelta(5.50, $shift->revenueForPaymentType('khqr'), 0.001);
        $this->assertEqualsWithDelta(5.50, $shift->revenueForPaymentType('card'), 0.001);
        $this->assertEqualsWithDelta(5.50, $shift->revenueForPaymentType('cash'), 0.001);

        // The display name is kept on the row (receipts), but the type is the
        // authoritative, linked one.
        $this->assertSame('Bakong QR', Payment::where('payment_method_id', $khqr->id)->sole()->method);
    }

    /**
     * Legacy cash rows (written before payment_method_id existed, or sales with
     * no method selected at all) still count as cash.
     */
    public function test_legacy_methodless_cash_rows_still_reconcile_as_cash(): void
    {
        $invoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'shift_id' => $this->shift->id,
            'status' => 'paid',
            'subtotal' => 10.00,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 10.00,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 10.00,
            'status' => 'successful',
        ]);

        $this->assertEqualsWithDelta(10.00, $this->shift->refresh()->revenueForPaymentType('cash'), 0.001);
    }

    /**
     * P0-2 regression: a sale must never drive stock below what's actually on
     * hand. Previously checkout decremented unconditionally, so selling 2 of a
     * product with 1 in stock created the invoice and left stock at -1.
     */
    public function test_checkout_is_blocked_when_stock_is_insufficient(): void
    {
        $this->stockIn($this->branch, $this->product, 1);

        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id, 2)], null, null);

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, StockTransaction::onHand($this->branch->id, $this->product->id));
    }

    public function test_checkout_allows_selling_exactly_the_remaining_stock(): void
    {
        $this->stockIn($this->branch, $this->product, 1);

        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id, 1)], null, null);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, StockTransaction::onHand($this->branch->id, $this->product->id));
    }

    public function test_checkout_requires_an_open_shift(): void
    {
        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id)], null, null);

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
    }

    /**
     * P2: absurd cart quantities are capped server-side at 99.
     */
    public function test_cart_quantity_is_capped_at_99(): void
    {
        $this->stockIn($this->branch, $this->product, 500);

        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('checkout', [$this->cartLine($this->product->id, 500)], null, null);

        $invoice = Invoice::sole();
        $this->assertSame(99, (int) $invoice->orderItems()->sole()->quantity);
    }

    /**
     * P1-1 regression: opening twice (double-click, two terminals) must not
     * fork the cash-drawer history — at most one open shift per branch.
     * setUp already opened a shift for the branch.
     */
    public function test_opening_a_shift_twice_keeps_a_single_open_shift(): void
    {
        Livewire::actingAs($this->user)
            ->test(Pos::class)
            ->call('openShift', 100);

        $this->assertSame(1, Shift::where('branch_id', $this->branch->id)->where('status', 'open')->count());
    }

    /**
     * P1-1 regression (DB backstop): the partial unique index on
     * shifts(branch_id) WHERE status='open' rejects a second open shift even
     * when created outside Pos::openShift().
     */
    public function test_a_second_open_shift_on_the_same_branch_violates_the_unique_index(): void
    {
        $branchB = $this->makeBranch($this->company, 'Branch B');
        $this->openShift($this->user, $branchB, 10);

        $this->expectException(QueryException::class);

        Shift::create([
            'branch_id' => $branchB->id,
            'user_id' => $this->user->id,
            'opening_amount' => 20,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }
}
