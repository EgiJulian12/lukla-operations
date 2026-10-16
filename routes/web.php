<?php

use App\Http\Controllers\OrderDesignDownloadController;
use App\Http\Controllers\SampleDesignDownloadController;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->get('/admin/inventory-items/{item}/print-tag', function (InventoryItem $item) {
    return view('filament.resources.inventory-items.tag-label', ['record' => $item]);
})->name('inventory-items.print-tag');

Route::middleware(['web', 'auth'])
    ->get('/orders/{order}/designs/download', [OrderDesignDownloadController::class, 'download'])
    ->name('orders.designs.download');

Route::middleware(['web', 'auth'])
    ->get('/samples/{sample}/designs/download', [SampleDesignDownloadController::class, 'download'])
    ->name('samples.designs.download');
