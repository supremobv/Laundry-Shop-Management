<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:10,1');
});
Route::get('/business-logo', [SettingsController::class, 'logo'])->name('business.logo');
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::put('/profile/password', [AuthController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware('permission:orders.create')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['permission:orders.create', 'throttle:60,1'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware('permission:orders.view')->name('orders.receipt');
    Route::patch('/orders/{order}/status', [OrderController::class, 'status'])->middleware('permission:orders.process')->name('orders.status');
    Route::post('/orders/{order}/payments', [OrderController::class, 'payment'])->middleware(['permission:payments.create', 'throttle:30,1'])->name('orders.payment');
    Route::post('/orders/{order}/payments/{payment}/refund', [OrderController::class, 'refund'])->middleware(['permission:payments.refund', 'throttle:10,1'])->name('orders.refund');
    Route::resource('customers', CustomerController::class)->middleware('permission:customers.manage');
    Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->withTrashed()->middleware('permission:customers.manage')->name('customers.restore');
    Route::resource('services', CatalogController::class)->except('show', 'destroy')->middleware('permission:catalog.manage');
    Route::get('/inventory', [OperationsController::class, 'inventory'])->middleware('permission:inventory.manage')->name('inventory');
    Route::post('/inventory', [OperationsController::class, 'supplyStore'])->middleware('permission:inventory.manage')->name('inventory.store');
    Route::post('/inventory/{supply}/adjust', [OperationsController::class, 'stock'])->middleware('permission:inventory.manage')->name('inventory.adjust');
    Route::get('/expenses', [OperationsController::class, 'expenses'])->middleware('permission:expenses.manage')->name('expenses');
    Route::post('/expenses', [OperationsController::class, 'expenseStore'])->middleware('permission:expenses.manage')->name('expenses.store');
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');
    Route::resource('staff', StaffController::class)->except('show', 'destroy')->middleware('permission:staff.manage');
    Route::get('/settings', [SettingsController::class, 'edit'])->middleware('permission:settings.manage')->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::get('/audit-log', [StaffController::class, 'audit'])->middleware('permission:audit.view')->name('audit');
    Route::view('/help', 'help')->name('help');
    Route::prefix('api/v1')->name('api.')->middleware('throttle:60,1')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
        Route::get('/customers',[CustomerController::class, 'index'])->middleware('permission:customers.manage')->name('customers.index');
    });
});
