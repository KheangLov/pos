<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discount;
use App\Models\FloorPlan;
use App\Models\Modifier;
use App\Models\ModifierFactor;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Table;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrewHavenSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Brew Haven Coffee',
            'email' => 'hello@brewhaven.coffee',
            'phone' => '+855 23 555 010',
            'address' => 'No. 12, Street 240, Daun Penh, Phnom Penh',
            'is_active' => true,
        ]);

        $riverside = Branch::create([
            'company_id' => $company->id,
            'name' => 'Brew Haven — Riverside',
            'email' => 'riverside@brewhaven.coffee',
            'phone' => '+855 23 555 011',
            'address' => 'No. 45, Sisowath Quay, Phnom Penh',
            'is_active' => true,
        ]);

        $bkk = Branch::create([
            'company_id' => $company->id,
            'name' => 'Brew Haven — BKK1',
            'email' => 'bkk1@brewhaven.coffee',
            'phone' => '+855 23 555 012',
            'address' => 'No. 28, Street 302, BKK1, Phnom Penh',
            'is_active' => true,
        ]);

        $this->seedUsers($company, $riverside, $bkk);
        [$categories, $products] = $this->seedCatalog($company);
        $drinkCategoryIds = [
            $categories['Hot Coffee']->id,
            $categories['Iced & Cold Brew']->id,
            $categories['Tea & Matcha']->id,
        ];
        $this->seedModifiers($company, $products, $drinkCategoryIds);
        $this->seedFloorPlans($riverside, $bkk);

        Tax::create(['company_id' => $company->id, 'name' => 'VAT 10%', 'rate' => 10.00, 'is_active' => true]);

        Discount::create(['company_id' => $company->id, 'name' => 'Happy Hour', 'type' => 'percentage', 'value' => 15.00, 'is_active' => true]);
        Discount::create(['company_id' => $company->id, 'name' => 'Member Card', 'type' => 'percentage', 'value' => 10.00, 'is_active' => true]);
        Discount::create(['company_id' => $company->id, 'name' => 'Staff Meal', 'type' => 'percentage', 'value' => 30.00, 'is_active' => true]);
        Discount::create(['company_id' => $company->id, 'name' => '$1 Off Promo', 'type' => 'fixed', 'value' => 1.00, 'is_active' => true]);

        $this->seedOpeningStock([$riverside, $bkk], $products);
    }

    private function seedUsers(Company $company, Branch $riverside, Branch $bkk): void
    {
        $make = function (string $name, string $email, string $role, Branch $branch) use ($company) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
            ]);
            $user->assignRole($role);
        };

        $make('Sophia Chan', 'sophia@brewhaven.coffee', 'Admin', $riverside);
        $make('Dara Sok', 'dara@brewhaven.coffee', 'Manager', $riverside);
        $make('Vanna Kim', 'vanna@brewhaven.coffee', 'Cashier', $riverside);
        $make('Leakena Pich', 'leakena@brewhaven.coffee', 'Cashier', $riverside);
        $make('Rithy Chea', 'rithy@brewhaven.coffee', 'Kitchen', $riverside);
        $make('Sokha Meas', 'sokha@brewhaven.coffee', 'Manager', $bkk);
        $make('Nita Heng', 'nita@brewhaven.coffee', 'Cashier', $bkk);
        $make('Piseth Long', 'piseth@brewhaven.coffee', 'Cashier', $bkk);
        $make('Chenda Ros', 'chenda@brewhaven.coffee', 'Kitchen', $bkk);
    }

    /**
     * @return array{0: array<string, Category>, 1: \Illuminate\Support\Collection<int, Product>}
     */
    private function seedCatalog(Company $company): array
    {
        $categoryData = [
            'Hot Coffee' => 'Espresso-based classics, brewed fresh all day',
            'Iced & Cold Brew' => 'Chilled coffee, slow-steeped cold brew and iced favourites',
            'Tea & Matcha' => 'Loose-leaf teas, matcha and chai',
            'Pastries' => 'Baked in-house every morning',
            'Sandwiches & Brunch' => 'Toasties, sandwiches and all-day brunch plates',
        ];

        $categories = [];
        $sort = 1;
        foreach ($categoryData as $name => $description) {
            $categories[$name] = Category::create([
                'company_id' => $company->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'is_active' => true,
                'sort_order' => $sort++,
            ]);
        }

        // [name, category, base price, cost, has size variants, description]
        $items = [
            ['Espresso', 'Hot Coffee', 2.50, 0.60, false, 'Double shot of our signature house blend'],
            ['Americano', 'Hot Coffee', 3.00, 0.65, true, 'Espresso lengthened with hot water'],
            ['Cappuccino', 'Hot Coffee', 3.75, 0.90, true, 'Equal parts espresso, steamed milk and foam'],
            ['Flat White', 'Hot Coffee', 4.00, 0.95, true, 'Velvety microfoam over a double ristretto'],
            ['Caffè Latte', 'Hot Coffee', 4.25, 1.00, true, 'Smooth espresso with steamed milk'],
            ['Caramel Macchiato', 'Hot Coffee', 4.75, 1.20, true, 'Vanilla, steamed milk, espresso and caramel drizzle'],
            ['Mocha', 'Hot Coffee', 4.75, 1.25, true, 'Espresso with dark chocolate and steamed milk'],
            ['Hot Chocolate', 'Hot Coffee', 3.95, 1.10, true, 'Belgian chocolate melted into steamed milk'],
            ['Iced Latte', 'Iced & Cold Brew', 4.50, 1.05, true, 'Espresso and cold milk over ice'],
            ['Iced Americano', 'Iced & Cold Brew', 3.50, 0.70, true, 'Espresso over ice and cold water'],
            ['Cold Brew', 'Iced & Cold Brew', 4.25, 0.95, true, '18-hour slow-steeped cold brew'],
            ['Iced Caramel Macchiato', 'Iced & Cold Brew', 5.00, 1.30, true, 'Iced vanilla milk with espresso and caramel'],
            ['Coconut Iced Coffee', 'Iced & Cold Brew', 4.75, 1.15, true, 'Cold brew with coconut cream — local favourite'],
            ['Matcha Latte', 'Tea & Matcha', 4.95, 1.40, true, 'Ceremonial-grade matcha whisked with milk'],
            ['Chai Latte', 'Tea & Matcha', 4.50, 1.10, true, 'Spiced black tea with steamed milk'],
            ['Earl Grey', 'Tea & Matcha', 3.00, 0.50, true, 'Bergamot-scented black tea'],
            ['Jasmine Green Tea', 'Tea & Matcha', 3.00, 0.50, true, 'Fragrant jasmine-infused green tea'],
            ['Butter Croissant', 'Pastries', 3.25, 0.90, false, 'Flaky, all-butter, baked every morning'],
            ['Almond Croissant', 'Pastries', 3.95, 1.20, false, 'Twice-baked with almond cream and flaked almonds'],
            ['Pain au Chocolat', 'Pastries', 3.75, 1.10, false, 'Buttery pastry with dark chocolate batons'],
            ['Blueberry Muffin', 'Pastries', 3.50, 1.00, false, 'Bursting with blueberries, crumble top'],
            ['Cinnamon Roll', 'Pastries', 4.25, 1.25, false, 'Soft-baked with cream cheese frosting'],
            ['Banana Bread', 'Pastries', 3.50, 0.95, false, 'Toasted slice with salted butter'],
            ['Avocado Toast', 'Sandwiches & Brunch', 6.95, 2.40, false, 'Smashed avocado, chili flakes, sourdough'],
            ['Ham & Cheese Toastie', 'Sandwiches & Brunch', 6.50, 2.20, false, 'Honey ham and gruyère on sourdough'],
            ['Turkey Club Sandwich', 'Sandwiches & Brunch', 6.95, 2.60, false, 'Roast turkey, bacon, lettuce, tomato'],
            ['Chicken Pesto Panini', 'Sandwiches & Brunch', 6.75, 2.50, false, 'Grilled chicken, basil pesto, mozzarella'],
        ];

        $skuSeq = 1;
        $products = collect();
        foreach ($items as [$name, $cat, $price, $cost, $hasSizes, $description]) {
            $sku = sprintf('BH-%03d', $skuSeq++);
            $product = Product::create([
                'company_id' => $company->id,
                'category_id' => $categories[$cat]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'base_price' => $price,
                'cost_price' => $cost,
                'sku' => $sku,
                'barcode' => $this->ean13(),
                'is_serialized' => false,
                'warranty_period' => 0,
                'is_active' => true,
            ]);

            if ($hasSizes) {
                foreach ([['Small', -0.50, 'S'], ['Medium', 0.00, 'M'], ['Large', 0.75, 'L']] as [$size, $delta, $code]) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $size,
                        'additional_price' => $delta,
                        'sku' => "{$sku}-{$code}",
                        'barcode' => $this->ean13(),
                        'is_active' => true,
                    ]);
                }
            }

            $products->push($product);
        }

        return [$categories, $products];
    }

    private function seedModifiers(Company $company, $products, array $drinkCategoryIds): void
    {
        $milk = ModifierGroup::create([
            'company_id' => $company->id,
            'name' => 'Milk Options',
            'selection_type' => 'single',
            'min_selections' => 0,
            'max_selections' => 1,
            'is_active' => true,
        ]);
        foreach ([['Whole Milk', 0.00], ['Skim Milk', 0.00], ['Oat Milk', 0.60], ['Almond Milk', 0.60], ['Soy Milk', 0.50]] as [$name, $price]) {
            Modifier::create(['modifier_group_id' => $milk->id, 'name' => $name, 'price' => $price, 'is_active' => true]);
        }

        $extras = ModifierGroup::create([
            'company_id' => $company->id,
            'name' => 'Espresso Extras',
            'selection_type' => 'multiple',
            'min_selections' => 0,
            'max_selections' => 3,
            'is_active' => true,
        ]);
        foreach ([['Extra Shot', 0.75], ['Decaf', 0.00], ['Vanilla Syrup', 0.50], ['Caramel Syrup', 0.50], ['Hazelnut Syrup', 0.50], ['Whipped Cream', 0.50]] as [$name, $price]) {
            Modifier::create(['modifier_group_id' => $extras->id, 'name' => $name, 'price' => $price, 'is_active' => true]);
        }

        $sweetness = ModifierGroup::create([
            'company_id' => $company->id,
            'name' => 'Sweetness Level',
            'selection_type' => 'single',
            'min_selections' => 0,
            'max_selections' => 1,
            'is_active' => true,
        ]);
        foreach ([['No Sugar', 0.00], ['25% Sweet', 0.00], ['50% Sweet', 0.00], ['100% Sweet', 0.00]] as [$name, $price]) {
            Modifier::create(['modifier_group_id' => $sweetness->id, 'name' => $name, 'price' => $price, 'is_active' => true]);
        }

        ModifierFactor::create(['company_id' => $company->id, 'name' => 'Single', 'multiplier' => 1.0, 'is_active' => true]);
        ModifierFactor::create(['company_id' => $company->id, 'name' => 'Double', 'multiplier' => 2.0, 'is_active' => true]);
        ModifierFactor::create(['company_id' => $company->id, 'name' => 'Triple', 'multiplier' => 3.0, 'is_active' => true]);

        // Attach drink modifier groups to every drink (categories 1-3 are drinks)
        $now = now();
        $pivotRows = [];
        foreach ($products as $product) {
            if (in_array($product->category_id, $drinkCategoryIds, true)) {
                foreach ([$milk->id, $extras->id, $sweetness->id] as $groupId) {
                    $pivotRows[] = [
                        'modifier_group_id' => $groupId,
                        'product_id' => $product->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }
        DB::table('modifier_group_product')->insert($pivotRows);
    }

    private function seedFloorPlans(Branch $riverside, Branch $bkk): void
    {
        $mainHall = FloorPlan::create(['branch_id' => $riverside->id, 'name' => 'Main Hall', 'is_active' => true]);
        $terrace = FloorPlan::create(['branch_id' => $riverside->id, 'name' => 'River Terrace', 'is_active' => true]);
        $ground = FloorPlan::create(['branch_id' => $bkk->id, 'name' => 'Ground Floor', 'is_active' => true]);
        $mezz = FloorPlan::create(['branch_id' => $bkk->id, 'name' => 'Mezzanine', 'is_active' => true]);

        $layouts = [
            [$mainHall, 8, 'T'],
            [$terrace, 4, 'R'],
            [$ground, 6, 'G'],
            [$mezz, 4, 'M'],
        ];

        foreach ($layouts as [$plan, $count, $prefix]) {
            for ($i = 1; $i <= $count; $i++) {
                Table::create([
                    'floor_plan_id' => $plan->id,
                    'name' => "{$prefix}{$i}",
                    'capacity' => [2, 2, 4, 4, 4, 6][array_rand([2, 2, 4, 4, 4, 6])],
                    'shape' => $i % 3 === 0 ? 'round' : 'rectangle',
                    'position_x' => (($i - 1) % 4) * 120 + 40,
                    'position_y' => intdiv($i - 1, 4) * 120 + 40,
                    'width' => 100,
                    'height' => 100,
                    'status' => 'available',
                ]);
            }
        }
    }

    private function seedOpeningStock(array $branches, $products): void
    {
        $now = now();
        $rows = [];
        foreach ($branches as $branch) {
            foreach ($products as $product) {
                $rows[] = [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity' => rand(300, 600),
                    'type' => 'purchase',
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => 'Opening stock',
                    'created_at' => $now->copy()->subDays(31),
                    'updated_at' => $now->copy()->subDays(31),
                ];
            }
        }
        DB::table('stock_transactions')->insert($rows);
    }

    private function ean13(): string
    {
        return '885'.str_pad((string) rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }
}
