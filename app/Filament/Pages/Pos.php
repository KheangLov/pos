<?php

namespace App\Filament\Pages;

use App\Events\OrderCreated;
use App\Events\PaymentReceived;
use App\Events\StockLow;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\KhqrService;
use App\Services\ProductSearch;
use App\Support\BuildsModifierGroupsPayload;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Pos extends Page
{
    use BuildsModifierGroupsPayload;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'POS Terminal';

    protected static ?string $title = 'Point of Sale';

    protected static ?string $slug = 'pos';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pos';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create_invoice') ?? false;
    }

    public string $search = '';

    public ?int $activeCategory = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeShift')
                ->label('Close Shift')
                ->color('danger')
                ->icon('heroicon-o-lock-closed')
                ->visible(fn () => $this->currentShift() !== null)
                ->requiresConfirmation()
                ->modalDescription('Count the cash drawer and enter the total before closing. This ends the shift and stops new orders until the next one is opened.')
                ->schema([
                    TextInput::make('closing_amount')
                        ->label('Counted cash')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->minValue(0),
                ])
                ->action(fn (array $data) => $this->closeShift((float) $data['closing_amount'])),
        ];
    }

    public function setCategory(?int $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    /**
     * The branch's currently open shift, if any. The terminal is gated
     * behind having one open (see pos.blade.php).
     */
    public function currentShift(): ?Shift
    {
        return Shift::where('branch_id', auth()->user()->branch_id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function openShift(float $openingAmount): void
    {
        if ($this->currentShift()) {
            return;
        }

        $user = auth()->user();

        Shift::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'opening_amount' => round(max(0, $openingAmount), 2),
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function closeShift(float $closingAmount): mixed
    {
        $shift = $this->currentShift();
        if (! $shift) {
            return null;
        }

        $shift->update([
            'closing_amount' => round(max(0, $closingAmount), 2),
            'closed_at' => now(),
            'status' => 'closed',
        ]);

        Notification::make()
            ->title('Shift #'.$shift->id.' closed')
            ->success()
            ->send();

        return redirect(ShiftResource::getUrl('view', ['record' => $shift]));
    }

    /**
     * Barcode-scanner flow: scanners type the code and send Enter. If the
     * term resolves to exactly one product, add it to the cart immediately.
     */
    public function addFirstMatch(): void
    {
        $term = trim($this->search);
        if ($term === '') {
            return;
        }

        $results = app(ProductSearch::class)->search(
            auth()->user()->company_id,
            $term,
            $this->activeCategory,
            2,
        );

        if ($results->count() !== 1) {
            return;
        }

        $product = $results->first();

        // Prefer the exact variant if a variant barcode/SKU was scanned
        $variant = $product->variants->first(fn ($v) => $v->barcode === $term || $v->sku === $term)
            ?? $product->variants->firstWhere('additional_price', 0.00)
            ?? $product->variants->first();

        $this->dispatch('pos-add-product', product: [
            'id' => $product->id,
            'variant_id' => $variant?->id,
            'name' => $product->name.($variant ? ' ('.$variant->name.')' : ''),
            'price' => round((float) $product->base_price + (float) ($variant->additional_price ?? 0), 2),
            'modifierGroups' => $this->modifierGroupsPayload($product),
        ]);

        $this->search = '';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'products' => app(ProductSearch::class)->search(
                $user->company_id,
                $this->search,
                $this->activeCategory,
            ),
            'categories' => Category::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'taxRate' => $this->taxRate(),
            'shift' => $this->currentShift(),
            'discounts' => Discount::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'value']),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where(function ($query) use ($user) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $user->branch_id);
                })
                ->get(),
            'pendingPayments' => Payment::with('invoice.table')
                ->where('status', 'pending')
                ->whereHas('invoice', function ($query) use ($user) {
                    $query->where('branch_id', $user->branch_id)
                        ->whereDate('created_at', today());
                })
                ->get(),
        ];
    }

    public function confirmPayment(int $paymentId): void
    {
        $payment = Payment::whereHas('invoice', fn ($query) => $query->where('branch_id', auth()->user()->branch_id))
            ->find($paymentId);

        if ($payment) {
            $payment->update(['status' => 'successful']);

            $payment->invoice->syncPaidStatus();

            event(new PaymentReceived($payment));

            Notification::make()
                ->title('Payment #'.$payment->id.' confirmed!')
                ->success()
                ->send();
        }
    }

    public function generateKhqr(array $cart, int $methodId, ?int $discountId = null): ?string
    {
        if (empty($cart)) {
            return null;
        }

        $user = auth()->user();

        $method = PaymentMethod::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('type', 'khqr')
            ->find($methodId);

        if (! $method || ! $method->account_details) {
            return null;
        }

        $pricing = $this->priceCart($cart, $user, $discountId);

        if ($pricing['total'] <= 0) {
            return null;
        }

        $service = app(KhqrService::class);
        $khqr = $service->generateKhqr(
            bakongId: $method->account_details,
            amount: $pricing['total'],
            currency: $method->currency ?? 'USD',
            merchantName: $user->company->name,
            city: 'Phnom Penh',
            merchantId: $method->merchant_id,
            acquiringBank: $method->acquiring_bank,
        );

        return $khqr ? $service->generateQrImage($khqr['qr']) : null;
    }

    /**
     * Server-side pricing shared by checkout() and generateKhqr() - the
     * client cart is only ever trusted for which product/variant/modifier
     * ids and quantities were picked, never for prices or totals.
     *
     * @return array{lines: array, subtotal: float, discount_total: float, tax_total: float, total: float}
     */
    private function priceCart(array $cart, User $user, ?int $discountId): array
    {
        $products = Product::with(['variants', 'modifierGroups.modifiers.modifierFactor'])
            ->where('company_id', $user->company_id)
            ->whereIn('id', collect($cart)->pluck('id'))
            ->get()
            ->keyBy('id');

        $discount = $discountId
            ? Discount::where('company_id', $user->company_id)->where('is_active', true)->find($discountId)
            : null;

        $subtotal = 0.0;
        $lines = [];

        foreach ($cart as $line) {
            $product = $products->get($line['id']);
            if (! $product) {
                continue;
            }

            $variant = isset($line['variant_id'])
                ? $product->variants->firstWhere('id', $line['variant_id'])
                : null;

            $modifiers = $this->resolveValidModifiers($product, $line['modifiers'] ?? []);
            $modifiersTotal = $modifiers->sum(fn ($m) => $m->effectivePrice());

            $qty = max(1, (int) ($line['qty'] ?? 1));
            $unitPrice = round((float) $product->base_price + (float) ($variant->additional_price ?? 0) + $modifiersTotal, 2);
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'modifiers' => $modifiers,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        $discountTotal = $discount ? $discount->amountFor($subtotal) : 0.0;
        $taxTotal = round(($subtotal - $discountTotal) * ($this->taxRate() / 100), 2);

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }

    public function checkout(array $cart, ?int $methodId = null, ?int $discountId = null): void
    {
        if (empty($cart)) {
            return;
        }

        $shift = $this->currentShift();
        if (! $shift) {
            Notification::make()
                ->title('Open a shift before taking orders')
                ->warning()
                ->send();

            return;
        }

        $user = auth()->user();

        // Never trust a client-supplied method name/type - resolve it from
        // the company's own configured methods, same as the QR/pricing path.
        $paymentMethod = $methodId
            ? PaymentMethod::where('company_id', $user->company_id)->where('is_active', true)->find($methodId)
            : null;

        $pricing = $this->priceCart($cart, $user, $discountId);

        if ($pricing['lines'] === []) {
            return;
        }

        $invoice = DB::transaction(function () use ($user, $shift, $pricing, $paymentMethod) {
            $invoice = Invoice::create([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'table_id' => null,
                'status' => 'pending', // paid up front; kitchen lifecycle: pending → preparing → ready → completed
                'subtotal' => $pricing['subtotal'],
                'discount_total' => $pricing['discount_total'],
                'tax_total' => $pricing['tax_total'],
                'total' => $pricing['total'],
            ]);

            foreach ($pricing['lines'] as $line) {
                $orderItem = OrderItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'quantity' => $line['qty'],
                    'price' => $line['unit_price'],
                    'subtotal' => $line['line_total'],
                    'status' => 'pending',
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    OrderItemModifier::create([
                        'order_item_id' => $orderItem->id,
                        'modifier_id' => $modifier->id,
                        'name' => $modifier->name,
                        'price' => $modifier->effectivePrice(),
                    ]);
                }

                StockTransaction::create([
                    'branch_id' => $user->branch_id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'quantity' => -$line['qty'],
                    'type' => 'sale',
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                ]);
            }

            // Re-derive the same QR here (deterministic from account+amount)
            // purely to capture its md5 for later Bakong reconciliation -
            // the customer already scanned the one shown by generateKhqr().
            $khqrMd5 = null;
            if ($paymentMethod?->type === 'khqr' && $paymentMethod->account_details) {
                $khqr = app(KhqrService::class)->generateKhqr(
                    bakongId: $paymentMethod->account_details,
                    amount: $invoice->total,
                    currency: $paymentMethod->currency ?? 'USD',
                    merchantName: $user->company->name,
                    city: 'Phnom Penh',
                    merchantId: $paymentMethod->merchant_id,
                    acquiringBank: $paymentMethod->acquiring_bank,
                );
                $khqrMd5 = $khqr['md5'] ?? null;
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethod?->id,
                'method' => $paymentMethod?->name ?? 'cash',
                'amount' => $invoice->total,
                'status' => 'successful',
                'khqr_md5' => $khqrMd5,
            ]);

            $invoice->syncPaidStatus();

            return [$invoice, $payment];
        });

        [$invoice, $payment] = $invoice;

        event(new OrderCreated([
            'id' => $invoice->id,
            'table' => 'Counter',
            'time' => $invoice->created_at->format('h:i A'),
            'items' => $invoice->orderItems()->with('product', 'productVariant', 'modifiers')->get()
                ->map(fn ($item) => [
                    'name' => $item->product->name.($item->productVariant ? ' ('.$item->productVariant->name.')' : ''),
                    'modifiers' => $item->modifiers->pluck('name')->all(),
                    'qty' => $item->quantity,
                ])->all(),
            'status' => 'pending',
        ], $user->branch_id));

        event(new PaymentReceived($payment));

        // Alert managers when a sale pushes any product below the reorder level
        foreach (collect($pricing['lines'])->pluck('product')->unique('id') as $product) {
            $onHand = StockTransaction::onHand($user->branch_id, $product->id);
            if ($onHand <= StockTransaction::LOW_STOCK_THRESHOLD) {
                event(new StockLow($product, $user->branch_id, $onHand));
            }
        }

        Notification::make()
            ->title('Order #'.$invoice->id.' placed — $'.number_format((float) $invoice->total, 2))
            ->success()
            ->send();

        $this->dispatch('clear-cart');
        $this->dispatch('print-receipt', url: route('invoices.receipt', $invoice));
    }

    private function taxRate(): float
    {
        return (float) (DB::table('taxes')
            ->where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->value('rate') ?? 0);
    }
}
