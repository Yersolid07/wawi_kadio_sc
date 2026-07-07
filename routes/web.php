<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\FoodOrderController as AdminFoodOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservationController;
use App\Http\Controllers\Customer\FoodOrderController as CustomerFoodOrderController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Staff\ReservationController as StaffReservationController;
use App\Http\Controllers\Staff\FoodOrderController as StaffFoodOrderController;
use App\Http\Controllers\Public\FacilityController as PublicFacilityController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/fasilitas', [PublicFacilityController::class, 'index'])->name('facilities.public');
Route::get('/fasilitas/{facility}', [PublicFacilityController::class, 'show'])->name('facilities.public.show');
Route::get('/fasilitas/{facility}/booked-dates', [PublicFacilityController::class, 'bookedDates'])->name('facilities.public.booked-dates');
Route::get('/katalog', [App\Http\Controllers\Public\MenuItemController::class, 'index'])->name('catalog.public');

// Payment gateway webhooks (no auth)
Route::post('/webhook/tripay', [AdminPaymentController::class, 'tripayWebhook'])->name('webhook.tripay');

// Guest & Customer Food Orders (Publicly accessible but handled smartly)
Route::prefix('pesanan')->name('customer.orders.')->group(function () {
    Route::get('/baru', [CustomerFoodOrderController::class, 'create'])->name('create');
    Route::post('/', [CustomerFoodOrderController::class, 'store'])->name('store');
    Route::get('/{order}/track', [CustomerFoodOrderController::class, 'show'])->name('show');
    Route::get('/{order}/print', [InvoiceController::class, 'foodOrder'])->name('print');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard (role-based)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |------------------------------------------------------------------
    | Customer Routes
    |------------------------------------------------------------------
    */
    Route::prefix('reservasi')->name('customer.reservations.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::get('/', [CustomerReservationController::class, 'index'])->name('index');
        Route::get('/baru', [CustomerReservationController::class, 'create'])->name('create');
        Route::post('/', [CustomerReservationController::class, 'store'])->name('store');
        Route::get('/{reservation}', [CustomerReservationController::class, 'show'])->name('show');
        Route::get('/{reservation}/kupon', [CustomerReservationController::class, 'coupon'])->name('coupon');
        Route::patch('/{reservation}/cancel', [CustomerReservationController::class, 'cancel'])->name('cancel');
        Route::get('/{reservation}/print', [InvoiceController::class, 'reservation'])->name('print');
    });

    Route::prefix('pesanan')->name('customer.orders.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::get('/', [CustomerFoodOrderController::class, 'index'])->name('index');
    });



    Route::prefix('ulasan')->name('customer.reviews.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::post('/', [CustomerReviewController::class, 'store'])->name('store');
        Route::put('/{review}', [CustomerReviewController::class, 'update'])->name('update');
    });

    Route::prefix('pembayaran')->name('customer.payments.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::get('/', [CustomerPaymentController::class, 'index'])->name('index');
        Route::post('/', [CustomerPaymentController::class, 'store'])->name('store');
        Route::get('/{payment}', [CustomerPaymentController::class, 'show'])->name('show');
    });

    /*
    |------------------------------------------------------------------
    | Staff Routes
    |------------------------------------------------------------------
    */
    Route::prefix('staff')->name('staff.')->middleware('role:admin|manager|staff')->group(function () {
        Route::get('/reservations', [StaffReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}/status', [StaffReservationController::class, 'updateStatus'])->name('reservations.status');
        
        // POS & Food Orders
        Route::get('/pos', [\App\Http\Controllers\Staff\POSController::class, 'index'])->name('pos.index');
        Route::post('/pos', [\App\Http\Controllers\Staff\POSController::class, 'store'])->name('pos.store');
        Route::get('/pos/print/{order}', [\App\Http\Controllers\Staff\POSController::class, 'print'])->name('pos.print');
        
        Route::get('/food-orders', [StaffFoodOrderController::class, 'index'])->name('food-orders.index');
        Route::get('/kds', [StaffFoodOrderController::class, 'kds'])->name('kds');
        Route::patch('/food-orders/{order}/status', [StaffFoodOrderController::class, 'updateStatus'])->name('food-orders.status');
        Route::patch('/food-orders/{order}/timer', [StaffFoodOrderController::class, 'updateTimer'])->name('food-orders.timer');
    });

    /*
    |------------------------------------------------------------------
    | Admin Routes
    |------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware('role:admin|manager')->group(function () {
        // QR Codes
        Route::get('/qrcodes', [\App\Http\Controllers\Admin\QRCodeController::class, 'index'])->name('qrcodes.index');
        Route::post('/qrcodes/generate', [\App\Http\Controllers\Admin\QRCodeController::class, 'generate'])->name('qrcodes.generate');
        Route::delete('/qrcodes/{qrcode}', [\App\Http\Controllers\Admin\QRCodeController::class, 'destroy'])->name('qrcodes.destroy');

        // Inventories
        Route::get('/inventories', [\App\Http\Controllers\Admin\InventoryController::class, 'index'])->name('inventories.index');
        Route::post('/inventories', [\App\Http\Controllers\Admin\InventoryController::class, 'store'])->name('inventories.store');
        Route::put('/inventories/{inventory}', [\App\Http\Controllers\Admin\InventoryController::class, 'update'])->name('inventories.update');
        Route::delete('/inventories/{inventory}', [\App\Http\Controllers\Admin\InventoryController::class, 'destroy'])->name('inventories.destroy');
        Route::post('/inventories/{inventory}/transaction', [\App\Http\Controllers\Admin\InventoryController::class, 'transaction'])->name('inventories.transaction');

        // Facilities
        Route::resource('facilities', AdminFacilityController::class);
        Route::patch('facilities/{facility}/toggle-status', [AdminFacilityController::class, 'toggleStatus'])
            ->name('facilities.toggle-status');

        // Reservations
        Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/calendar', [AdminReservationController::class, 'calendar'])->name('reservations.calendar');
        Route::get('reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::patch('reservations/{reservation}/status', [AdminReservationController::class, 'updateStatus'])->name('reservations.status');

        // Menu Items
        Route::resource('menu-items', AdminMenuItemController::class);
        Route::patch('menu-items/{menuItem}/toggle', [AdminMenuItemController::class, 'toggleAvailability'])
            ->name('menu-items.toggle');

        // Food Orders
        Route::get('food-orders', [AdminFoodOrderController::class, 'index'])->name('food-orders.index');
        Route::patch('food-orders/{order}/status', [AdminFoodOrderController::class, 'updateStatus'])->name('food-orders.status');

        // Payments
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [AdminPaymentController::class, 'store'])->name('payments.store');
        Route::patch('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');

        // Reviews
        Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{review}/toggle-visibility', [AdminReviewController::class, 'toggleVisibility'])->name('reviews.toggle');
        Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        // Users & Roles (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::resource('users', AdminUserController::class)->except(['show']);
            Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        });

        // Reports
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export-pdf', [AdminReportController::class, 'exportPdf'])->name('reports.pdf');
        Route::get('reports/export-excel', [AdminReportController::class, 'exportExcel'])->name('reports.excel');

        // Settings
        Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/auth.php';
