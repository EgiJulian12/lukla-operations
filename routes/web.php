<?php

use App\Models\InventoryItem;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->get('/admin/inventory-items/{item}/print-tag', function (InventoryItem $item) {
    return view('filament.resources.inventory-items.tag-label', ['record' => $item]);
})->name('inventory-items.print-tag');
