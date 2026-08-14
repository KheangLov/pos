<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $orderData;

    public ?int $branchId;

    public function __construct(array $orderData, ?int $branchId = null)
    {
        $this->orderData = $orderData;
        $this->branchId = $branchId;
    }

    public function broadcastOn(): array
    {
        // Private per-branch channel only; the old public 'kds' channel was
        // removed (nothing subscribed to it, and it leaked order events to
        // anyone listening).
        $channels = [];

        if ($this->branchId !== null) {
            $channels[] = new PrivateChannel("branch.{$this->branchId}.kds");
        }

        return $channels;
    }
}
