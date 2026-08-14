<?php

use App\Livewire\EMenu;
use App\Livewire\OrderTracking;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/emenu/table/{uuid}', EMenu::class)->name('emenu.table');

    // Order tracking is gated behind the table's unguessable UUID so
    // sequential invoice ids can't be walked (P1-2).
    Route::get('/emenu/order/{tableUuid}/{invoice}', OrderTracking::class)->name('order.tracking');
});

Route::get('/invoices/{invoice}/receipt', function (Invoice $invoice) {
    abort_unless($invoice->branch->company_id === auth()->user()->company_id, 403);

    $invoice->loadMissing(['orderItems.product', 'orderItems.productVariant', 'orderItems.modifiers', 'branch.company', 'payments', 'table']);

    return view('invoices.receipt', compact('invoice'));
})->name('invoices.receipt')->middleware('auth');
