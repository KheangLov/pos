<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Services\KhqrService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrderTracking extends Component
{
    public $invoice;

    public function mount($invoice)
    {
        $this->invoice = Invoice::with('orderItems.product', 'table', 'payments.paymentMethod')->findOrFail($invoice);
    }

    public function checkStatus()
    {
        $this->invoice = $this->invoice->fresh(['orderItems.product', 'table', 'payments.paymentMethod']);
    }

    public function getIsPaidProperty(): bool
    {
        return $this->invoice->payments
            ->where('status', 'successful')
            ->sum('amount') >= (float) $this->invoice->total;
    }

    public function getPendingKhqrSvgProperty(): ?string
    {
        if ($this->isPaid) {
            return null;
        }

        $pendingPayment = $this->invoice->payments->where('status', 'pending')->first();
        if (! $pendingPayment) {
            return null;
        }

        $method = $pendingPayment->paymentMethod;
        if (! $method || $method->type !== 'khqr' || ! $method->account_details) {
            return null;
        }

        $branch = $this->invoice->table?->floorPlan?->branch;
        if (! $branch) {
            return null;
        }

        $service = app(KhqrService::class);
        $khqr = $service->generateKhqr(
            bakongId: $method->account_details,
            amount: (float) $this->invoice->total,
            currency: $method->currency ?? 'USD',
            merchantName: $branch->company->name,
            city: 'Phnom Penh',
            merchantId: $method->merchant_id,
            acquiringBank: $method->acquiring_bank,
        );

        return $khqr ? $service->generateQrImage($khqr['qr']) : null;
    }

    public function render()
    {
        return view('livewire.order-tracking');
    }
}
