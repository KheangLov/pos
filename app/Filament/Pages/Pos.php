<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class Pos extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'POS Terminal';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'pos';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pos';

    public function checkout(array $cart)
    {
        if (empty($cart)) {
            return;
        }

        $orderData = [
            'id' => rand(1000, 9999),
            'table' => 'Counter',
            'time' => now()->format('h:i A'),
            'items' => collect($cart)->map(fn($item) => ['name' => $item['name'], 'qty' => $item['qty']])->toArray(),
            'status' => 'pending'
        ];

        event(new \App\Events\OrderCreated($orderData));

        \Filament\Notifications\Notification::make()
            ->title('Order Placed Successfully!')
            ->success()
            ->send();

        $this->dispatch('clear-cart');
    }
}
