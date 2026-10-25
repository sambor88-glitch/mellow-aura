<?php

use App\Modules\Shipping\Http\Controllers\Admin\ShipmentController;
use Illuminate\Support\Facades\Route;

// Under admin.orders.*, so a helper who packs the parcels may send them and print the labels (PanelAccess).
Route::post('/zamowienia/{order:number}/przesylka', [ShipmentController::class, 'store'])->name('orders.shipment.store');
Route::get('/zamowienia/{order:number}/etykieta', [ShipmentController::class, 'label'])->name('orders.shipment.label');
