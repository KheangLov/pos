<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Table;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesHistorySeeder extends Seeder
{
    private array $orderItemRows = [];

    private array $paymentRows = [];

    private array $stockRows = [];

    private array $cashNoteRows = [];

    /** @var array<int, array<int, int>> serial ids still in stock, keyed by branch, then product */
    private array $serialPools = [];

    public function run(): void
    {
        $branches = Branch::all();
        $companies = DB::table('companies')->pluck('name', 'id');

        foreach ($branches as $branch) {
            $isCoffee = str_contains($companies[$branch->company_id], 'Brew Haven');
            $this->seedBranchHistory($branch, $isCoffee);
        }

        $this->flush();
    }

    private function seedBranchHistory(Branch $branch, bool $isCoffee): void
    {
        $products = Product::where('company_id', $branch->company_id)->where('is_active', true)->get();
        $variantsByProduct = DB::table('product_variants')
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->groupBy('product_id');

        $cashiers = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'Cashier')
            ->where('users.branch_id', $branch->id)
            ->pluck('users.id')
            ->all();

        $tableIds = DB::table('tables')
            ->join('floor_plans', 'floor_plans.id', '=', 'tables.floor_plan_id')
            ->where('floor_plans.branch_id', $branch->id)
            ->pluck('tables.id')
            ->all();

        // Pool of in-stock serials per product for serialized sales
        $this->serialPools[$branch->id] = [];
        foreach (DB::table('serial_numbers')->where('branch_id', $branch->id)->where('status', 'in_stock')->get(['id', 'product_id'])->groupBy('product_id') as $productId => $serials) {
            $this->serialPools[$branch->id][$productId] = $serials->pluck('id')->all();
        }

        $taxRate = (float) (DB::table('taxes')->where('company_id', $branch->company_id)->where('is_active', true)->value('rate') ?? 0);
        $percentDiscounts = DB::table('discounts')
            ->where('company_id', $branch->company_id)
            ->where('type', 'percentage')
            ->pluck('value')
            ->all();

        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            $day = Carbon::today()->subDays($daysAgo);
            $isToday = $daysAgo === 0;
            $isWeekend = $day->isWeekend();

            $openedAt = $day->copy()->setTime($isCoffee ? 6 : 9, $isCoffee ? 30 : 0);
            $closedAt = $day->copy()->setTime($isCoffee ? 20 : 21, 0);
            $cashierId = $cashiers[$daysAgo % count($cashiers)];
            $openingAmount = $isCoffee ? 200.00 : 500.00;

            $shift = Shift::create([
                'branch_id' => $branch->id,
                'user_id' => $cashierId,
                'opening_amount' => $openingAmount,
                'closing_amount' => null,
                'opened_at' => $openedAt,
                'closed_at' => $isToday ? null : $closedAt,
                'status' => $isToday ? 'open' : 'closed',
                'created_at' => $openedAt,
                'updated_at' => $isToday ? $openedAt : $closedAt,
            ]);

            $cashTotal = 0.0;

            if ($isCoffee) {
                $invoiceCount = $isWeekend ? rand(30, 45) : rand(18, 30);
            } else {
                $invoiceCount = $isWeekend ? rand(8, 14) : rand(5, 10);
            }
            if ($isToday) {
                $invoiceCount = (int) ceil($invoiceCount * (Carbon::now()->hour / 20));
            }

            for ($i = 0; $i < $invoiceCount; $i++) {
                $createdAt = $this->randomBusinessTime($day, $isCoffee);
                if ($isToday && $createdAt->isFuture()) {
                    $createdAt = Carbon::now()->subMinutes(rand(5, 120));
                }

                $cashTotal += $this->createInvoice(
                    $branch, $shift, $cashierId, $products, $variantsByProduct,
                    $tableIds, $taxRate, $percentDiscounts, $createdAt, $isCoffee,
                );
            }

            // Occasional petty-cash withdrawal during the shift
            if (rand(1, 100) <= 30) {
                $noteTime = $openedAt->copy()->addHours(rand(2, 8));
                $amount = $isCoffee ? rand(5, 30) : rand(10, 60);
                $this->cashNoteRows[] = [
                    'shift_id' => $shift->id,
                    'type' => 'cash_out',
                    'amount' => $amount,
                    'reason' => collect(['Milk run to supplier', 'Cleaning supplies', 'Motodop delivery fee', 'Printer paper', 'Staff drinking water'])->random(),
                    'created_at' => $noteTime,
                    'updated_at' => $noteTime,
                ];
                $cashTotal -= $amount;
            }

            if (! $isToday) {
                $shift->update(['closing_amount' => round($openingAmount + $cashTotal, 2)]);
            }
        }

        // Leave a few live orders on today's board for the KDS/eMenu demo
        if ($isCoffee) {
            $this->seedLiveOrders($branch, $products, $variantsByProduct, $tableIds, $taxRate, $cashiers);
        }
    }

    /**
     * Creates one paid invoice and buffers its child rows. Returns cash received.
     */
    private function createInvoice(
        Branch $branch,
        Shift $shift,
        int $cashierId,
        $products,
        $variantsByProduct,
        array $tableIds,
        float $taxRate,
        array $percentDiscounts,
        Carbon $createdAt,
        bool $isCoffee,
    ): float {
        $itemCount = $isCoffee ? rand(1, 4) : rand(1, 2);
        $items = [];
        $subtotal = 0.0;

        for ($k = 0; $k < $itemCount; $k++) {
            $product = $products->random();
            $variants = $variantsByProduct->get($product->id);
            $variant = $variants ? $variants->random() : null;

            $qty = $isCoffee ? rand(1, 3) : 1;

            // Serialized items sell one unit and consume a serial from the pool
            if ($product->is_serialized) {
                $pool = &$this->serialPools[$branch->id][$product->id];
                if (empty($pool)) {
                    continue; // sold out — skip this line item
                }
                $serialId = array_shift($pool);
                $qty = 1;
                DB::table('serial_numbers')->where('id', $serialId)->update([
                    'status' => 'sold',
                    'warranty_end_date' => $product->warranty_period > 0
                        ? $createdAt->copy()->addMonths((int) $product->warranty_period)->toDateString()
                        : null,
                    'updated_at' => $createdAt,
                ]);
            }

            $unitPrice = round((float) $product->base_price + (float) ($variant->additional_price ?? 0), 2);
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id ?? null,
                'quantity' => $qty,
                'price' => $unitPrice,
                'subtotal' => $lineTotal,
                'status' => 'completed',
            ];
        }

        if ($items === []) {
            return 0.0;
        }

        $discountTotal = 0.0;
        if ($percentDiscounts !== [] && rand(1, 100) <= 12) {
            $discountTotal = round($subtotal * ($percentDiscounts[array_rand($percentDiscounts)] / 100), 2);
        }

        $taxTotal = round(($subtotal - $discountTotal) * ($taxRate / 100), 2);
        $total = round($subtotal - $discountTotal + $taxTotal, 2);

        $invoice = Invoice::create([
            'branch_id' => $branch->id,
            'user_id' => $cashierId,
            'shift_id' => $shift->id,
            'table_id' => ($isCoffee && $tableIds !== [] && rand(1, 100) <= 65) ? $tableIds[array_rand($tableIds)] : null,
            'status' => 'paid',
            'subtotal' => round($subtotal, 2),
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items as $item) {
            $this->orderItemRows[] = $item + [
                'invoice_id' => $invoice->id,
                'notes' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $this->stockRows[] = [
                'branch_id' => $branch->id,
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'],
                'quantity' => -$item['quantity'],
                'type' => 'sale',
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'notes' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        // Payment split: mostly single tender; electronics skews to card
        $roll = rand(1, 100);
        if ($isCoffee) {
            $method = $roll <= 40 ? 'cash' : ($roll <= 80 ? 'card' : 'qr');
        } else {
            $method = $roll <= 25 ? 'cash' : ($roll <= 80 ? 'card' : 'qr');
        }

        $this->paymentRows[] = [
            'invoice_id' => $invoice->id,
            'method' => $method,
            'amount' => $total,
            'reference' => $method === 'cash' ? null : strtoupper('TXN-'.dechex(rand(0x100000, 0xFFFFFF)).$invoice->id),
            'status' => 'successful',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        return $method === 'cash' ? $total : 0.0;
    }

    private function seedLiveOrders(Branch $branch, $products, $variantsByProduct, array $tableIds, float $taxRate, array $cashiers): void
    {
        if ($tableIds === [] || $cashiers === []) {
            return;
        }

        $shiftId = DB::table('shifts')->where('branch_id', $branch->id)->where('status', 'open')->value('id');
        $statuses = ['pending', 'preparing', 'ready'];
        $drinkable = $products->where('is_serialized', false);

        foreach (array_slice($tableIds, 0, 3) as $idx => $tableId) {
            $createdAt = Carbon::now()->subMinutes([3, 8, 14][$idx]);
            $subtotal = 0.0;
            $items = [];

            for ($k = 0; $k < rand(2, 3); $k++) {
                $product = $drinkable->random();
                $variants = $variantsByProduct->get($product->id);
                $variant = $variants ? $variants->random() : null;
                $qty = rand(1, 2);
                $unitPrice = round((float) $product->base_price + (float) ($variant->additional_price ?? 0), 2);
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;
                $items[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id ?? null,
                    'quantity' => $qty,
                    'price' => $unitPrice,
                    'subtotal' => $lineTotal,
                    'status' => $statuses[$idx],
                ];
            }

            $taxTotal = round($subtotal * ($taxRate / 100), 2);
            $invoice = Invoice::create([
                'branch_id' => $branch->id,
                'user_id' => $cashiers[0],
                'shift_id' => $shiftId,
                'table_id' => $tableId,
                'status' => 'pending',
                'subtotal' => round($subtotal, 2),
                'discount_total' => 0,
                'tax_total' => $taxTotal,
                'total' => round($subtotal + $taxTotal, 2),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($items as $item) {
                $this->orderItemRows[] = $item + [
                    'invoice_id' => $invoice->id,
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            Table::where('id', $tableId)->update(['status' => 'occupied']);
        }
    }

    private function randomBusinessTime(Carbon $day, bool $isCoffee): Carbon
    {
        if ($isCoffee) {
            // Morning rush, lunch bump, afternoon pick-me-up
            $weights = [7 => 8, 8 => 10, 9 => 9, 10 => 5, 11 => 5, 12 => 8, 13 => 7, 14 => 4, 15 => 4, 16 => 6, 17 => 6, 18 => 4, 19 => 3];
        } else {
            $weights = [10 => 3, 11 => 5, 12 => 7, 13 => 6, 14 => 4, 15 => 4, 16 => 5, 17 => 7, 18 => 8, 19 => 6, 20 => 4];
        }

        $total = array_sum($weights);
        $roll = rand(1, $total);
        foreach ($weights as $hour => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $day->copy()->setTime($hour, rand(0, 59), rand(0, 59));
            }
        }

        return $day->copy()->setTime(12, 0);
    }

    private function flush(): void
    {
        foreach (array_chunk($this->orderItemRows, 500) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }
        foreach (array_chunk($this->paymentRows, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }
        foreach (array_chunk($this->stockRows, 500) as $chunk) {
            DB::table('stock_transactions')->insert($chunk);
        }
        foreach (array_chunk($this->cashNoteRows, 500) as $chunk) {
            DB::table('cash_notes')->insert($chunk);
        }
    }
}
