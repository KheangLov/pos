<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->extraAttributes(fn (Invoice $record) => [
                    'onclick' => 'printReceiptFrame(\''.route('invoices.receipt', $record).'\')',
                ]),
        ];
    }
}
