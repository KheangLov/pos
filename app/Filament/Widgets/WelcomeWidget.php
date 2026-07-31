<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

/**
 * Filament's own AccountWidget doesn't expose columnSpan as a registration
 * option, only as a protected instance property — so a thin subclass is the
 * only way to make it span the full dashboard row.
 */
class WelcomeWidget extends AccountWidget
{
    protected int|string|array $columnSpan = 'full';
}
