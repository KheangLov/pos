<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $invoiceId;

    public string $method;

    public string $amount;

    private int $branchId;

    private ?string $tableUuid;

    public function __construct(Payment $payment)
    {
        $payment->loadMissing('invoice.table');

        $this->invoiceId = $payment->invoice_id;
        $this->method = $payment->method;
        $this->amount = (string) $payment->amount;
        $this->branchId = $payment->invoice->branch_id;
        $this->tableUuid = $payment->invoice->table?->uuid;
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("branch.{$this->branchId}.kds")];

        if ($this->tableUuid !== null) {
            $channels[] = new Channel("order.{$this->tableUuid}.{$this->invoiceId}");
        }

        return $channels;
    }
}
