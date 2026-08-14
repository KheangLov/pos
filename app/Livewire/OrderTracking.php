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

    public function mount(string $tableUuid, int $invoice)
    {
        // The table UUID is the capability token: without it, sequential
        // invoice ids are not enumerable (P1-2). A mismatch is a 404.
        $this->invoice = Invoice::with('orderItems.product', 'table', 'payments.paymentMethod')
            ->whereHas('table', fn ($q) => $q->where('uuid', $tableUuid))
            ->findOrFail($invoice);
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

        if (! $khqr) {
            return null;
        }

        // Remember the generated payload's md5 on the payment row so the
        // khqr:check-pending job can poll Bakong for confirmation (P1-5).
        if (! $pendingPayment->khqr_md5) {
            $pendingPayment->forceFill(['khqr_md5' => $khqr['md5']])->save();
        }

        return $service->generateQrImage($khqr['qr']);
    }

    public function render()
    {
        return view('livewire.order-tracking');
    }
}
