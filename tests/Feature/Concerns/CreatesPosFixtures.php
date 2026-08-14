<?php

namespace Tests\Feature\Concerns;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Discount;
use App\Models\FloorPlan;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockTransaction;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Shared fixture builders for the money-path feature tests (POS + eMenu).
 */
trait CreatesPosFixtures
{
    /**
     * The app always ships these roles (RolePermissionSeeder); listeners such
     * as NotifyLowStock query them, so tests need them present.
     */
    protected function makeRoles(): void
    {
        foreach (['Admin', 'Manager', 'Cashier', 'Kitchen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    protected function makeCompany(string $name = 'Test Co'): Company
    {
        return Company::create(['name' => $name]);
    }

    protected function makeBranch(Company $company, string $name = 'Main Branch'): Branch
    {
        return Branch::create(['company_id' => $company->id, 'name' => $name]);
    }

    protected function makeUser(Company $company, Branch $branch, array $permissions = ['create_invoice']): User
    {
        $this->makeRoles();

        $user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.local',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function makeTax(Company $company, float $rate = 10.0): void
    {
        DB::table('taxes')->insert([
            'company_id' => $company->id,
            'name' => 'VAT '.$rate.'%',
            'rate' => $rate,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeProduct(Company $company, float $basePrice = 5.00, string $slug = 'latte'): Product
    {
        return Product::create([
            'company_id' => $company->id,
            'name' => 'Latte',
            'slug' => $slug,
            'base_price' => $basePrice,
            'is_active' => true,
        ]);
    }

    protected function makeModifier(Product $product, string $name = 'Extra Shot', float $price = 1.00): Modifier
    {
        $group = ModifierGroup::create([
            'company_id' => $product->company_id,
            'name' => 'Add-ons',
            'selection_type' => 'single',
            'min_selections' => 0,
            'max_selections' => 1,
        ]);
        $group->products()->attach($product);

        return Modifier::create([
            'modifier_group_id' => $group->id,
            'name' => $name,
            'price' => $price,
        ]);
    }

    protected function makeDiscount(Company $company, string $type = 'percentage', float $value = 10.0): Discount
    {
        return Discount::create([
            'company_id' => $company->id,
            'name' => 'Test discount',
            'type' => $type,
            'value' => $value,
            'is_active' => true,
        ]);
    }

    protected function makePaymentMethod(Company $company, string $name, string $type, ?Branch $branch = null): PaymentMethod
    {
        return PaymentMethod::create([
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    protected function makeFloorPlan(Branch $branch, string $name = 'Main floor'): FloorPlan
    {
        return FloorPlan::create(['branch_id' => $branch->id, 'name' => $name]);
    }

    protected function makeTable(FloorPlan $floorPlan, string $name = 'T1'): Table
    {
        return Table::create(['floor_plan_id' => $floorPlan->id, 'name' => $name]);
    }

    protected function openShift(User $user, Branch $branch, float $openingAmount = 50.0): Shift
    {
        return Shift::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => $openingAmount,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    protected function stockIn(Branch $branch, Product $product, int $qty): void
    {
        StockTransaction::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'type' => 'purchase',
            'notes' => 'test fixture',
        ]);
    }

    /**
     * POS checkout cart line (server-side pricing, so only ids + qty matter).
     *
     * @param  array<int, int>  $modifierIds
     * @return array{id: int, qty: int, modifiers: array<int, array{id: int}>}
     */
    protected function cartLine(int $productId, int $qty = 1, array $modifierIds = []): array
    {
        return [
            'id' => $productId,
            'qty' => $qty,
            'modifiers' => array_map(fn ($id) => ['id' => $id], $modifierIds),
        ];
    }

    /**
     * eMenu cart line (same shape the Alpine/JS side builds: the blade renders
     * name/key, checkout() only trusts id + quantity and re-prices server-side).
     *
     * @return array{key: string, id: int, name: string, quantity: int, price: float, modifiers: array}
     */
    protected function emenuCartLine(int $productId, int $qty = 1, float $price = 0.0, array $modifiers = []): array
    {
        return [
            'key' => 'fixture-'.$productId,
            'id' => $productId,
            'name' => 'Latte',
            'quantity' => $qty,
            'price' => $price,
            'modifiers' => $modifiers,
        ];
    }
}
