<?php

namespace App\Livewire;

use App\Events\OrderCreated;
use App\Events\StockLow;
use App\Events\TableStatusChanged;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\Table;
use App\Models\User;
use App\Services\ProductSearch;
use App\Support\BuildsModifierGroupsPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class EMenu extends Component
{
    use BuildsModifierGroupsPayload;

    public Table $table;

    public ?int $selectedCategory = null;

    public string $search = '';

    public array $cart = [];

    public float $total = 0;

    public ?int $selectedPaymentMethod = null;

    // Modifier picker state - kept server-side (Livewire) rather than Alpine
    // to match the rest of this component's architecture.
    public ?int $pickingProductId = null;

    public array $pickingModifierGroups = [];

    public array $pickerSelections = [];

    public function mount($uuid)
    {
        $this->table = Table::where('uuid', $uuid)
            ->with('floorPlan.branch')
            ->firstOrFail();
    }

    public function getCompanyIdProperty(): int
    {
        return $this->table->floorPlan->branch->company_id;
    }

    public function selectCategory($categoryId)
    {
        $this->selectedCategory = $categoryId ? (int) $categoryId : null;
    }

    /**
     * Entry point for adding a product: opens the modifier picker if the
     * product has any, otherwise adds it straight to the cart.
     */
    public function selectProduct($productId)
    {
        $product = Product::where('company_id', $this->companyId)
            ->where('is_active', true)
            ->with('modifierGroups.modifiers.modifierFactor')
            ->findOrFail($productId);

        $groups = $this->modifierGroupsPayload($product);

        if ($groups === []) {
            $this->addItemToCart($product, []);

            return;
        }

        $this->pickingProductId = $product->id;
        $this->pickingModifierGroups = $groups;
        $this->pickerSelections = collect($groups)->mapWithKeys(fn ($group) => [$group['id'] => []])->all();
    }

    public function closePicker(): void
    {
        $this->pickingProductId = null;
        $this->pickingModifierGroups = [];
        $this->pickerSelections = [];
    }

    public function toggleModifierSelection(int $groupId, int $modifierId): void
    {
        $group = collect($this->pickingModifierGroups)->firstWhere('id', $groupId);
        if (! $group) {
            return;
        }

        $current = $this->pickerSelections[$groupId] ?? [];

        if ($group['selection_type'] === 'single') {
            $this->pickerSelections[$groupId] = in_array($modifierId, $current) ? [] : [$modifierId];

            return;
        }

        if (in_array($modifierId, $current)) {
            $this->pickerSelections[$groupId] = array_values(array_diff($current, [$modifierId]));
        } elseif (! $group['max_selections'] || count($current) < $group['max_selections']) {
            $this->pickerSelections[$groupId] = [...$current, $modifierId];
        }
    }

    public function getPickerValidProperty(): bool
    {
        foreach ($this->pickingModifierGroups as $group) {
            $count = count($this->pickerSelections[$group['id']] ?? []);

            if ($count < ($group['min_selections'] ?? 0)) {
                return false;
            }

            if ($group['max_selections'] && $count > $group['max_selections']) {
                return false;
            }
        }

        return true;
    }

    public function getPickerTotalProperty(): float
    {
        if (! $this->pickingProductId) {
            return 0;
        }

        $total = (float) (Product::find($this->pickingProductId)?->base_price ?? 0);

        foreach ($this->selectedPickerModifiers() as $modifier) {
            $total += $modifier['price'];
        }

        return $total;
    }

    /**
     * @return array<int, array{id: int, name: string, price: float}>
     */
    private function selectedPickerModifiers(): array
    {
        $selected = [];

        foreach ($this->pickingModifierGroups as $group) {
            foreach ($group['modifiers'] as $modifier) {
                if (in_array($modifier['id'], $this->pickerSelections[$group['id']] ?? [])) {
                    $selected[] = $modifier;
                }
            }
        }

        return $selected;
    }

    public function confirmPicker(): void
    {
        if (! $this->pickerValid || ! $this->pickingProductId) {
            return;
        }

        $product = Product::where('company_id', $this->companyId)->findOrFail($this->pickingProductId);
        $modifiers = $this->selectedPickerModifiers();

        $this->addItemToCart($product, $modifiers);
        $this->closePicker();
    }

    /**
     * @param  array<int, array{id: int, name: string, price: float}>  $modifiers
     */
    private function addItemToCart(Product $product, array $modifiers): void
    {
        $modifiersTotal = collect($modifiers)->sum('price');
        $price = round((float) $product->base_price + $modifiersTotal, 2);
        $modifierKey = collect($modifiers)->pluck('id')->sort()->implode(',');
        $key = $product->id.'-'.$modifierKey;

        foreach ($this->cart as &$item) {
            if ($item['key'] === $key) {
                $item['quantity']++;
                $this->calculateTotal();

                return;
            }
        }
        unset($item);

        $this->cart[] = [
            'key' => $key,
            'id' => $product->id,
            'name' => $product->name,
            'price' => $price,
            'modifiers' => $modifiers,
            'quantity' => 1,
        ];

        $this->calculateTotal();
    }

    public function incrementCartItem(string $key): void
    {
        foreach ($this->cart as &$item) {
            if ($item['key'] === $key) {
                $item['quantity']++;
                break;
            }
        }
        unset($item);

        $this->calculateTotal();
    }

    public function decrementCartItem(string $key): void
    {
        foreach ($this->cart as $i => &$item) {
            if ($item['key'] === $key) {
                if ($item['quantity'] > 1) {
                    $item['quantity']--;
                } else {
                    unset($this->cart[$i]);
                }
                break;
            }
        }
        unset($item);

        $this->cart = array_values($this->cart);
        $this->calculateTotal();
    }

    public function calculateTotal(): void
    {
        $this->total = 0;
        foreach ($this->cart as $item) {
            $this->total += $item['price'] * $item['quantity'];
        }
    }

    public function checkout()
    {
        if (empty($this->cart)) {
            return;
        }

        if (RateLimiter::tooManyAttempts('emenu-checkout:'.request()->ip(), 20)) {
            return;
        }
        RateLimiter::hit('emenu-checkout:'.request()->ip(), 60);

        $branchId = $this->table->floorPlan->branch_id;

        // Re-price everything server-side from the database
        $products = Product::with('modifierGroups.modifiers.modifierFactor')
            ->where('company_id', $this->companyId)
            ->whereIn('id', collect($this->cart)->pluck('id'))
            ->get()
            ->keyBy('id');

        $taxRate = (float) (DB::table('taxes')
            ->where('company_id', $this->companyId)
            ->where('is_active', true)
            ->value('rate') ?? 0);

        $systemUserId = User::where('branch_id', $branchId)->value('id')
            ?? User::where('company_id', $this->companyId)->value('id');

        $invoice = DB::transaction(function () use ($branchId, $products, $taxRate, $systemUserId) {
            $subtotal = 0.0;
            $lines = [];

            foreach ($this->cart as $item) {
                $product = $products->get($item['id']);
                if (! $product) {
                    continue;
                }

                $modifiers = $this->resolveValidModifiers($product, $item['modifiers'] ?? []);
                $modifiersTotal = $modifiers->sum(fn ($m) => $m->effectivePrice());

                $qty = max(1, (int) $item['quantity']);
                $unitPrice = round((float) $product->base_price + $modifiersTotal, 2);
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $lines[] = ['product' => $product, 'modifiers' => $modifiers, 'qty' => $qty, 'unit_price' => $unitPrice, 'line_total' => $lineTotal];
            }

            if ($lines === []) {
                return null;
            }

            $taxTotal = round($subtotal * ($taxRate / 100), 2);

            $invoice = Invoice::create([
                'branch_id' => $branchId,
                'user_id' => $systemUserId,
                'table_id' => $this->table->id,
                'status' => 'pending',
                'subtotal' => round($subtotal, 2),
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => round($subtotal + $taxTotal, 2),
            ]);

            foreach ($lines as $line) {
                $orderItem = OrderItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $line['product']->id,
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
                    'branch_id' => $branchId,
                    'product_id' => $line['product']->id,
                    'quantity' => -$line['qty'],
                    'type' => 'sale',
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                ]);
            }

            $this->table->update(['status' => 'occupied']);

            if ($this->selectedPaymentMethod) {
                $method = PaymentMethod::where('company_id', $this->companyId)
                    ->where('is_active', true)
                    ->where(function ($query) use ($branchId) {
                        $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->find($this->selectedPaymentMethod);
                if ($method) {
                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'payment_method_id' => $method->id,
                        'method' => $method->name,
                        'amount' => $invoice->total,
                        'status' => 'pending',
                    ]);
                }
            }

            return $invoice;
        });

        if (! $invoice) {
            return;
        }

        event(new OrderCreated([
            'id' => $invoice->id,
            'table' => $this->table->name,
            'time' => now()->format('h:i A'),
            'items' => $invoice->orderItems()->with('modifiers')->get()
                ->map(fn ($item) => [
                    'name' => $products->get($item->product_id)?->name ?? 'Item',
                    'modifiers' => $item->modifiers->pluck('name')->all(),
                    'qty' => $item->quantity,
                ])->all(),
            'status' => 'pending',
        ], $branchId));

        event(new TableStatusChanged($this->table->fresh('floorPlan')));

        foreach ($products as $product) {
            $onHand = StockTransaction::onHand($branchId, $product->id);
            if ($onHand <= StockTransaction::LOW_STOCK_THRESHOLD) {
                event(new StockLow($product, $branchId, $onHand));
            }
        }

        $this->cart = [];
        $this->calculateTotal();

        return redirect()->route('order.tracking', ['invoice' => $invoice->id]);
    }

    public function render()
    {
        return view('livewire.e-menu', [
            'categories' => Category::where('company_id', $this->companyId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'products' => app(ProductSearch::class)->search(
                $this->companyId,
                $this->search,
                $this->selectedCategory,
            ),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $this->companyId)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('branch_id')->orWhere('branch_id', $this->table->floorPlan->branch_id);
                })
                ->get(),
        ]);
    }
}
