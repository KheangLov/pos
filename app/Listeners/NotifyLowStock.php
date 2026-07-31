<?php

namespace App\Listeners;

use App\Events\StockLow;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class NotifyLowStock
{
    public function handle(StockLow $event): void
    {
        // Throttle: one alert per product/branch every 6 hours
        if (! Cache::add("stock-low-notified.{$event->branchId}.{$event->productId}", true, now()->addHours(6))) {
            return;
        }

        $recipients = User::role(['Manager', 'Admin'])
            ->where('branch_id', $event->branchId)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title('Low stock: '.$event->productName)
            ->body("Only {$event->onHand} left in stock. Consider reordering.")
            ->warning()
            ->icon('heroicon-o-exclamation-triangle');

        // sendToDatabase persists it for the bell icon (30s poll, still there
        // after a refresh); broadcast pushes it over the already-configured
        // App.Models.User.{id} channel so it also appears as an instant toast
        // for whoever is looking at the screen right now.
        $notification->sendToDatabase($recipients);
        $notification->broadcast($recipients);
    }
}
