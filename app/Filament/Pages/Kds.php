<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Kds extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Kitchen Display (KDS)';
    protected static ?string $title = 'Kitchen Display System';
    protected static ?string $slug = 'kds';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.kds';
}
