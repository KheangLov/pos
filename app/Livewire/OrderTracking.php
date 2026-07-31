<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrderTracking extends Component
{
    public $invoice;

    public function mount($invoice)
    {
        $this->invoice = Invoice::with('orderItems.product', 'table', 'payments')->findOrFail($invoice);
    }

    public function checkStatus()
    {
        $this->invoice = $this->invoice->fresh(['orderItems.product', 'table', 'payments']);
    }

    public function getIsPaidProperty(): bool
    {
        return $this->invoice->payments
            ->where('status', 'successful')
            ->sum('amount') >= (float) $this->invoice->total;
    }

    public function render()
    {
        return view('livewire.order-tracking');
    }
}
