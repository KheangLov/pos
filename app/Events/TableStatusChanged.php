<?php

namespace App\Events;

use App\Models\Table;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TableStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tableId;

    public string $name;

    public string $status;

    private int $branchId;

    public function __construct(Table $table)
    {
        $this->tableId = $table->id;
        $this->name = $table->name;
        $this->status = $table->status;
        $this->branchId = $table->floorPlan->branch_id;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("branch.{$this->branchId}.tables")];
    }
}
