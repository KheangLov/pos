<?php

use App\Livewire\EMenu;
use App\Livewire\OrderTracking;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/emenu/table/{uuid}', EMenu::class)->name('emenu.table');
Route::get('/emenu/order/{invoice}', OrderTracking::class)->name('order.tracking');
