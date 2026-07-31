<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $invoiceId;

    public string $status;

    public ?string $tableName;

    private int $branchId;

    private ?string $tableUuid;

    public function __construct(Invoice $invoice)
    {
        $this->invoiceId = $invoice->id;
        $this->status = $invoice->status;
        $this->branchId = $invoice->branch_id;
        $this->tableName = $invoice->table?->name;
        $this->tableUuid = $invoice->table?->uuid;
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("branch.{$this->branchId}.kds")];

        // Customer-facing tracker: table UUID acts as an unguessable capability token
        if ($this->tableUuid !== null) {
            $channels[] = new Channel("order.{$this->tableUuid}.{$this->invoiceId}");
        }

        return $channels;
    }
}
