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
}
