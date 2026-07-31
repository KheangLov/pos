<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLow implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $productId;

    public string $productName;

    public int $onHand;

    public int $branchId;

    public function __construct(Product $product, int $branchId, int $onHand)
    {
        $this->productId = $product->id;
        $this->productName = $product->name;
        $this->onHand = $onHand;
        $this->branchId = $branchId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.inventory")];
    }
}
