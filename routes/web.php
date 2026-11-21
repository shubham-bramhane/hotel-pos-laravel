<?php

use App\Http\Controllers\AuthPinController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\WaiterController;
use Illuminate\Support\Facades\Route;

// Fast Staff PIN Auth
Route::get('/', [AuthPinController::class, 'root'])->name('pos.root');
Route::get('/login', [AuthPinController::class, 'showLogin'])->name('pos.login');
Route::post('/login', [AuthPinController::class, 'login'])->name('pos.login.submit');
Route::post('/logout', [AuthPinController::class, 'logout'])->name('pos.logout');

// Waiter Mobile POS (Waiter, Cashier, Owner)
Route::middleware(['pos.auth:waiter,cashier'])->prefix('waiter')->group(function () {
    Route::get('/', [WaiterController::class, 'index'])->name('waiter.index');
    Route::get('/table/{table}', [WaiterController::class, 'table'])->name('waiter.table');
    Route::post('/table/{table}/kot', [WaiterController::class, 'sendKot'])->name('waiter.kot.send');
    Route::post('/order/{order}/request-bill', [WaiterController::class, 'requestBill'])->name('waiter.bill.request');
});
Route::middleware(['pos.auth:waiter,cashier'])->get('/api/waiter/tables', [WaiterController::class, 'liveTables'])->name('api.waiter.tables');

// Live Kitchen Display System - KDS (Kitchen, Cashier, Owner)
Route::middleware(['pos.auth:kitchen,cashier'])->prefix('kitchen')->group(function () {
    Route::get('/', [KitchenController::class, 'index'])->name('kitchen.index');
    Route::patch('/kot/{kot}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.kot.status');
    Route::patch('/menu/{item}/toggle', [KitchenController::class, 'toggleItemStock'])->name('kitchen.menu.toggle');
});
Route::middleware(['pos.auth:kitchen,cashier'])->get('/api/kitchen/live', [KitchenController::class, 'live'])->name('api.kitchen.live');

// Counter / Cashier POS & Thermal Printing (Cashier, Owner)
Route::middleware(['pos.auth:cashier'])->prefix('counter')->group(function () {
    Route::get('/', [CounterController::class, 'index'])->name('counter.index');
    Route::get('/order/{order}', [CounterController::class, 'index'])->name('counter.order');
    Route::post('/table/{table}/open', [CounterController::class, 'openTable'])->name('counter.table.open');
    Route::post('/parcel', [CounterController::class, 'createParcel'])->name('counter.parcel.create');
    Route::post('/order/{order}/items', [CounterController::class, 'addItems'])->name('counter.order.items');
    Route::post('/item/{item}/cancel', [CounterController::class, 'cancelItem'])->name('counter.item.cancel');
    Route::post('/order/{order}/discount', [CounterController::class, 'applyDiscount'])->name('counter.order.discount');
    Route::post('/order/{order}/transfer', [CounterController::class, 'transferTable'])->name('counter.order.transfer');
    Route::post('/order/{order}/settle', [CounterController::class, 'settle'])->name('counter.order.settle');
    Route::post('/order/{order}/cancel', [CounterController::class, 'cancelOrder'])->name('counter.order.cancel');
});
Route::middleware(['pos.auth:cashier'])->get('/api/counter/live', [CounterController::class, 'liveSummary'])->name('api.counter.live');

// Thermal Receipts (80mm / 58mm Print Ready)
Route::middleware(['pos.auth:waiter,kitchen,cashier'])->group(function () {
    Route::get('/print/bill/{order}', [CounterController::class, 'printBill'])->name('print.bill');
    Route::get('/print/kot/{kot}', [CounterController::class, 'printKot'])->name('print.kot');
});

// Owner Admin Dashboard, Reports, Menu, Tables, Staff, Audit & Backup (Owner Only)
Route::middleware(['pos.auth:owner'])->prefix('owner')->group(function () {
    Route::get('/', [OwnerController::class, 'dashboard'])->name('owner.dashboard');
    Route::get('/export-sales', [OwnerController::class, 'exportSales'])->name('owner.export.sales');

    Route::get('/menu', [OwnerController::class, 'menu'])->name('owner.menu');
    Route::post('/menu/category', [OwnerController::class, 'storeCategory'])->name('owner.category.store');
    Route::post('/menu/item', [OwnerController::class, 'storeMenuItem'])->name('owner.item.store');
    Route::put('/menu/item/{item}', [OwnerController::class, 'updateMenuItem'])->name('owner.item.update');
    Route::delete('/menu/item/{item}', [OwnerController::class, 'destroyMenuItem'])->name('owner.item.destroy');

    Route::get('/tables', [OwnerController::class, 'tables'])->name('owner.tables');
    Route::post('/tables', [OwnerController::class, 'storeTable'])->name('owner.tables.store');
    Route::delete('/tables/{table}', [OwnerController::class, 'destroyTable'])->name('owner.tables.destroy');

    Route::get('/staff', [OwnerController::class, 'staff'])->name('owner.staff');
    Route::post('/staff', [OwnerController::class, 'storeStaff'])->name('owner.staff.store');
    Route::put('/staff/{staffUser}', [OwnerController::class, 'updateStaff'])->name('owner.staff.update');

    Route::get('/audit', [OwnerController::class, 'audit'])->name('owner.audit');
    Route::get('/settings', [OwnerController::class, 'settings'])->name('owner.settings');
    Route::post('/settings', [OwnerController::class, 'saveSettings'])->name('owner.settings.save');
    Route::post('/backup', [OwnerController::class, 'downloadBackup'])->name('owner.backup');
});
