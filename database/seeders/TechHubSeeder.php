<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TechHubSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'TechHub Electronics',
            'email' => 'support@techhub.store',
            'phone' => '+855 23 900 200',
            'address' => 'No. 88, Monivong Blvd, Phnom Penh',
            'is_active' => true,
        ]);

        $cityMall = Branch::create([
            'company_id' => $company->id,
            'name' => 'TechHub — City Mall',
            'email' => 'citymall@techhub.store',
            'phone' => '+855 23 900 201',
            'address' => 'City Mall, Level 2, Monireth Blvd, Phnom Penh',
            'is_active' => true,
        ]);

        $tkAvenue = Branch::create([
            'company_id' => $company->id,
            'name' => 'TechHub — TK Avenue',
            'email' => 'tkavenue@techhub.store',
            'phone' => '+855 23 900 202',
            'address' => 'TK Avenue Mall, Street 315, Toul Kork, Phnom Penh',
            'is_active' => true,
        ]);

        $this->seedUsers($company, $cityMall, $tkAvenue);
        $products = $this->seedCatalog($company);

        Tax::create(['company_id' => $company->id, 'name' => 'VAT 10%', 'rate' => 10.00, 'is_active' => true]);

        Discount::create(['company_id' => $company->id, 'name' => 'Clearance', 'type' => 'percentage', 'value' => 20.00, 'is_active' => true]);
        Discount::create(['company_id' => $company->id, 'name' => 'Bundle Deal', 'type' => 'percentage', 'value' => 5.00, 'is_active' => true]);
        Discount::create(['company_id' => $company->id, 'name' => '$10 Voucher', 'type' => 'fixed', 'value' => 10.00, 'is_active' => true]);

        $this->seedStockAndSerials([$cityMall, $tkAvenue], $products);
    }

    private function seedUsers(Company $company, Branch $cityMall, Branch $tkAvenue): void
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

        $make('Marcus Lim', 'marcus@techhub.store', 'Admin', $cityMall);
        $make('Bopha Nguon', 'bopha@techhub.store', 'Manager', $cityMall);
        $make('Kosal Var', 'kosal@techhub.store', 'Cashier', $cityMall);
        $make('Srey Neang', 'sreyneang@techhub.store', 'Cashier', $cityMall);
        $make('Visal Ung', 'visal@techhub.store', 'Manager', $tkAvenue);
        $make('Mony Keo', 'mony@techhub.store', 'Cashier', $tkAvenue);
        $make('Thida Sam', 'thida@techhub.store', 'Cashier', $tkAvenue);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function seedCatalog(Company $company): \Illuminate\Support\Collection
    {
        $categoryData = [
            'Smartphones' => 'Latest flagship and mid-range phones, all carriers unlocked',
            'Laptops & Tablets' => 'Ultrabooks, tablets and 2-in-1s',
            'Audio' => 'Headphones, earbuds and speakers',
            'Wearables' => 'Smartwatches and fitness trackers',
            'Accessories' => 'Chargers, cables, cases and more',
            'Gaming' => 'Consoles, controllers and gaming gear',
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

        // [name, category, price, cost, serialized, warranty months, variants, description]
        $items = [
            ['iPhone 16 Pro', 'Smartphones', 999.00, 850.00, true, 12,
                [['128GB Black Titanium', 0], ['256GB Black Titanium', 100], ['256GB Desert Titanium', 100], ['512GB Natural Titanium', 300]],
                'Apple A18 Pro chip, 48MP camera system, titanium design'],
            ['Samsung Galaxy S25', 'Smartphones', 799.00, 660.00, true, 12,
                [['128GB Onyx Black', 0], ['256GB Onyx Black', 60], ['256GB Icy Blue', 60]],
                'Snapdragon 8 Elite, 6.2" Dynamic AMOLED 2X'],
            ['Google Pixel 9', 'Smartphones', 699.00, 570.00, true, 12,
                [['128GB Obsidian', 0], ['256GB Porcelain', 60]],
                'Tensor G4, Gemini AI built in, 50MP camera'],
            ['Xiaomi Redmi Note 14', 'Smartphones', 229.00, 175.00, true, 12,
                [['128GB Midnight Black', 0], ['256GB Ocean Blue', 30]],
                'Best-selling budget phone, 108MP camera, 5500mAh'],
            ['MacBook Air 13" M3', 'Laptops & Tablets', 1099.00, 930.00, true, 12,
                [['8GB/256GB Midnight', 0], ['16GB/512GB Starlight', 200]],
                'Apple M3 chip, 18-hour battery, fanless design'],
            ['iPad Air 11"', 'Laptops & Tablets', 599.00, 495.00, true, 12,
                [['128GB Space Gray', 0], ['256GB Blue', 100]],
                'M2 chip, Liquid Retina display, Apple Pencil Pro support'],
            ['Samsung Galaxy Tab S9', 'Laptops & Tablets', 749.00, 610.00, true, 12,
                [['128GB Graphite', 0], ['256GB Beige', 70]],
                'Dynamic AMOLED 2X, S Pen included, IP68'],
            ['AirPods Pro 2', 'Audio', 249.00, 190.00, true, 12, [],
                'Active noise cancellation, USB-C, adaptive audio'],
            ['Sony WH-1000XM5', 'Audio', 349.00, 265.00, true, 12, [],
                'Industry-leading noise cancellation, 30-hour battery'],
            ['JBL Flip 6', 'Audio', 129.00, 88.00, false, 12, [],
                'Portable waterproof speaker, 12-hour playtime'],
            ['Marshall Emberton II', 'Audio', 169.00, 120.00, false, 12, [],
                'Iconic design, 30+ hours portable sound'],
            ['Apple Watch Series 10', 'Wearables', 399.00, 320.00, true, 12,
                [['42mm Jet Black', 0], ['46mm Rose Gold', 30]],
                'Thinnest Apple Watch ever, advanced health sensors'],
            ['Samsung Galaxy Watch 7', 'Wearables', 299.00, 230.00, true, 12,
                [['40mm Green', 0], ['44mm Silver', 30]],
                'Galaxy AI health insights, dual-band GPS'],
            ['Xiaomi Smart Band 9', 'Wearables', 44.99, 28.00, false, 6, [],
                '21-day battery, AMOLED display, 150+ sport modes'],
            ['Anker 65W GaN Charger', 'Accessories', 45.99, 26.00, false, 18, [],
                '3-port fast charger, laptop-capable, foldable plug'],
            ['Anker PowerCore 20K', 'Accessories', 49.99, 29.00, false, 18, [],
                '20,000mAh power bank with 30W fast charging'],
            ['Belkin USB-C Cable 2m', 'Accessories', 12.99, 4.50, false, 24, [],
                'Braided 100W USB-C to USB-C, USB-IF certified'],
            ['Spigen iPhone 16 Pro Case', 'Accessories', 19.99, 7.00, false, 0, [],
                'Ultra Hybrid MagFit, military-grade drop protection'],
            ['Tempered Glass Protector', 'Accessories', 9.99, 2.20, false, 0, [],
                '9H hardness, case-friendly, oleophobic coating'],
            ['Logitech MX Master 3S', 'Accessories', 99.99, 68.00, false, 12, [],
                '8K DPI, quiet clicks, multi-device flow'],
            ['Keychron K2 V2', 'Accessories', 89.99, 58.00, false, 12, [],
                'Wireless mechanical keyboard, hot-swappable, Mac/Win'],
            ['PS5 DualSense Controller', 'Gaming', 69.99, 48.00, false, 12,
                [['White', 0], ['Midnight Black', 0], ['Cosmic Red', 5]],
                'Haptic feedback, adaptive triggers'],
            ['Nintendo Switch OLED', 'Gaming', 349.00, 285.00, true, 12,
                [['White', 0], ['Neon Red/Blue', 0]],
                '7" OLED screen, enhanced audio, 64GB'],
            ['SteelSeries Arctis Nova 7', 'Gaming', 179.00, 128.00, false, 12, [],
                'Wireless gaming headset, 38-hour battery, multi-platform'],
        ];

        $skuSeq = 1;
        $products = collect();
        foreach ($items as [$name, $cat, $price, $cost, $serialized, $warranty, $variants, $description]) {
            $sku = sprintf('TH-%03d', $skuSeq++);
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
                'is_serialized' => $serialized,
                'warranty_period' => $warranty,
                'is_active' => true,
            ]);

            $v = 1;
            foreach ($variants as [$variantName, $delta]) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantName,
                    'additional_price' => $delta,
                    'sku' => sprintf('%s-V%d', $sku, $v++),
                    'barcode' => $this->ean13(),
                    'is_active' => true,
                ]);
            }

            $products->push($product);
        }

        return $products;
    }

    private function seedStockAndSerials(array $branches, $products): void
    {
        $now = now();
        $stockRows = [];
        $serialRows = [];

        foreach ($branches as $branch) {
            foreach ($products as $product) {
                $qty = $product->is_serialized ? rand(18, 30) : rand(40, 120);

                $stockRows[] = [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'quantity' => $qty,
                    'type' => 'purchase',
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => 'Opening stock',
                    'created_at' => $now->copy()->subDays(31),
                    'updated_at' => $now->copy()->subDays(31),
                ];

                if ($product->is_serialized) {
                    $variantIds = DB::table('product_variants')
                        ->where('product_id', $product->id)
                        ->pluck('id')
                        ->all();

                    for ($i = 0; $i < $qty; $i++) {
                        $serialRows[] = [
                            'product_id' => $product->id,
                            'product_variant_id' => $variantIds !== [] ? $variantIds[array_rand($variantIds)] : null,
                            'branch_id' => $branch->id,
                            'serial_number' => strtoupper(sprintf('%s-%s-%05d', str_replace('TH-', 'SN', $product->sku), $branch->id, $i + 1)),
                            'status' => 'in_stock',
                            'warranty_end_date' => null,
                            'created_at' => $now->copy()->subDays(31),
                            'updated_at' => $now->copy()->subDays(31),
                        ];
                    }
                }
            }
        }

        DB::table('stock_transactions')->insert($stockRows);
        foreach (array_chunk($serialRows, 500) as $chunk) {
            DB::table('serial_numbers')->insert($chunk);
        }
    }

    private function ean13(): string
    {
        return '880'.str_pad((string) rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
    }
}
