<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
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
        // Legacy public channel kept while older screens migrate to the private one
        $channels = [new Channel('kds')];

        if ($this->branchId !== null) {
            $channels[] = new PrivateChannel("branch.{$this->branchId}.kds");
        }

        return $channels;
    }
}
