<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierSearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('search', [SearchController::class, 'index'])->name('search');

    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::put('suppliers', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::get('suppliers/search', [SupplierSearchController::class, 'index'])->name('suppliers.search');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket}/orders', [OrderController::class, 'store'])->name('tickets.orders.store');
    Route::delete('tickets/{ticket}/orders/{order}', [OrderController::class, 'destroy'])->name('tickets.orders.destroy');

    Route::post('tickets/{ticket}/tasks', [TaskController::class, 'store'])->name('tickets.tasks.store');
    Route::put('tickets/{ticket}/tasks/{task}', [TaskController::class, 'update'])->name('tickets.tasks.update');
    Route::delete('tickets/{ticket}/tasks/{task}', [TaskController::class, 'destroy'])->name('tickets.tasks.destroy');

    Route::post('tickets/{ticket}/checklist-items', [ChecklistItemController::class, 'store'])->name('tickets.checklist-items.store');
    Route::put('tickets/{ticket}/checklist-items/{item}', [ChecklistItemController::class, 'update'])->name('tickets.checklist-items.update');
    Route::delete('tickets/{ticket}/checklist-items/{item}', [ChecklistItemController::class, 'destroy'])->name('tickets.checklist-items.destroy');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('tickets/{ticket}/invoice', [InvoiceController::class, 'store'])->name('tickets.invoice.store');
    Route::post('tickets/{ticket}/invoice/approve', [InvoiceController::class, 'update'])->name('tickets.invoice.approve');
    Route::post('tickets/{ticket}/invoice/adjustments', [AdjustmentController::class, 'store'])->name('tickets.invoice.adjustments.store');
    Route::delete('tickets/{ticket}/invoice/adjustments/{adjustment}', [AdjustmentController::class, 'destroy'])->name('tickets.invoice.adjustments.destroy');
    Route::post('tickets/{ticket}/invoice/transactions', [TransactionController::class, 'store'])->name('tickets.invoice.transactions.store');
    Route::delete('tickets/{ticket}/invoice/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('tickets.invoice.transactions.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
