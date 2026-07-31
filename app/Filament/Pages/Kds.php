<?php

namespace App\Filament\Pages;

use App\Events\OrderStatusUpdated;
use App\Events\TableStatusChanged;
use App\Models\Invoice;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class Kds extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Kitchen Display (KDS)';
    protected static ?string $title = 'Kitchen Display System';
    protected static ?string $slug = 'kds';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.kds';

    public static function getNavigationBadge(): ?string
    {
        $count = Invoice::query()
            ->where('branch_id', auth()->user()?->branch_id)
            ->whereIn('status', ['pending', 'preparing'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getViewData(): array
    {
        return [
            'orders' => $this->activeOrders(),
            'branchId' => auth()->user()->branch_id,
        ];
    }

    /**
     * @return Collection<int, Invoice>
     */
    protected function activeOrders(): Collection
    {
        return Invoice::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->with(['table', 'orderItems.product', 'orderItems.productVariant', 'orderItems.modifiers'])
            ->orderBy('created_at')
            ->get();
    }

    public function setStatus(int $invoiceId, string $status): void
    {
        if (! in_array($status, ['preparing', 'ready', 'completed'], true)) {
            return;
        }

        $invoice = Invoice::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->with('table')
            ->find($invoiceId);

        if (! $invoice) {
            return;
        }

        $invoice->update(['status' => $status]);
        $invoice->orderItems()->update(['status' => $status === 'completed' ? 'completed' : $status]);

        event(new OrderStatusUpdated($invoice));

        // Serving the order frees the table
        if ($status === 'completed' && $invoice->table) {
            $invoice->table->update(['status' => 'available']);
            event(new TableStatusChanged($invoice->table->fresh('floorPlan')));
        }
    }
}
