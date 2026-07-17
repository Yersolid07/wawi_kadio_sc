<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\FoodOrderController as AdminFoodOrderController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MenuItemController as AdminMenuItemController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\QRCodeController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Customer\FoodOrderController as CustomerFoodOrderController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservationController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\FacilityController as PublicFacilityController;
use App\Http\Controllers\Public\MenuItemController;
use App\Http\Controllers\Staff\FoodOrderController as StaffFoodOrderController;
use App\Http\Controllers\Staff\POSController;
use App\Http\Controllers\Staff\ReservationController as StaffReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/fasilitas', [PublicFacilityController::class, 'index'])->name('facilities.public');
Route::get('/fasilitas/{facility}', [PublicFacilityController::class, 'show'])->name('facilities.public.show');
Route::get('/fasilitas/{facility}/booked-dates', [PublicFacilityController::class, 'bookedDates'])->name('facilities.public.booked-dates');
Route::get('/katalog', [MenuItemController::class, 'index'])->name('catalog.public');

// Payment gateway webhooks (no auth)
Route::post('/webhook/tripay', [AdminPaymentController::class, 'tripayWebhook'])->name('webhook.tripay');

Route::prefix('pesanan')->name('customer.orders.')->group(function () {
    Route::get('/baru', [CustomerFoodOrderController::class, 'create'])->name('create');
    Route::post('/', [CustomerFoodOrderController::class, 'store'])->middleware('throttle:3,1')->name('store');
    Route::get('/{order}/track', [CustomerFoodOrderController::class, 'show'])->name('show');
    Route::get('/{order}/print', [InvoiceController::class, 'foodOrder'])->name('print');
});

// Guest & Customer Reservations (Publicly accessible but handled smartly)
Route::prefix('reservasi')->name('customer.reservations.')->group(function () {
    Route::get('/baru', [CustomerReservationController::class, 'create'])->name('create');
    Route::post('/', [CustomerReservationController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::post('/cek-kupon', [CustomerReservationController::class, 'checkCoupon'])->name('check-coupon');
    Route::get('/{reservation}', [CustomerReservationController::class, 'show'])->name('show');
    Route::get('/{reservation}/kupon', [CustomerReservationController::class, 'coupon'])->name('coupon');
    Route::get('/{reservation}/print', [InvoiceController::class, 'reservation'])->name('print');
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
        Route::patch('/{reservation}/cancel', [CustomerReservationController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('pesanan')->name('customer.orders.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::get('/', [CustomerFoodOrderController::class, 'index'])->name('index');
    });

    Route::prefix('ulasan')->name('customer.reviews.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::post('/', [CustomerReviewController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::put('/{review}', [CustomerReviewController::class, 'update'])->name('update');
    });

    Route::prefix('pembayaran')->name('customer.payments.')->middleware('role:customer|admin|manager|staff')->group(function () {
        Route::get('/', [CustomerPaymentController::class, 'index'])->name('index');
        Route::post('/', [CustomerPaymentController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::get('/{payment}', [CustomerPaymentController::class, 'show'])->name('show');
    });

    /*
    |------------------------------------------------------------------
    | Staff Routes
    |------------------------------------------------------------------
    */
    Route::prefix('staff')->name('staff.')->middleware('role:admin|manager|staff')->group(function () {
        Route::get('/reservations', [StaffReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/scan', [StaffReservationController::class, 'scan'])->name('reservations.scan');
        Route::post('/reservations/verify', [StaffReservationController::class, 'verify'])->name('reservations.verify');
        Route::patch('/reservations/{reservation}/status', [StaffReservationController::class, 'updateStatus'])->name('reservations.status');

        // POS & Food Orders
        Route::get('/pos', [POSController::class, 'index'])->name('pos.index');
        Route::post('/pos', [POSController::class, 'store'])->name('pos.store');
        Route::get('/pos/print/{order}', [POSController::class, 'print'])->name('pos.print');

        Route::get('/food-orders', [StaffFoodOrderController::class, 'index'])->name('food-orders.index');
        Route::delete('/food-orders/{order}', [StaffFoodOrderController::class, 'destroy'])->name('food-orders.destroy');
        Route::get('/kds', [StaffFoodOrderController::class, 'kds'])->name('kds');
        Route::patch('/food-orders/{order}/status', [StaffFoodOrderController::class, 'updateStatus'])->name('food-orders.status');
        Route::patch('/food-orders/{order}/timer', [StaffFoodOrderController::class, 'updateTimer'])->name('food-orders.timer');

        // Daily Stock
        Route::get('/daily-stock', [\App\Http\Controllers\Staff\DailyStockController::class, 'index'])->name('daily-stock.index');
        Route::post('/daily-stock', [\App\Http\Controllers\Staff\DailyStockController::class, 'update'])->name('daily-stock.update');

        // POS Closing
        Route::get('/pos-closing', [\App\Http\Controllers\Staff\PosClosingController::class, 'index'])->name('pos-closing.index');
        Route::post('/pos-closing', [\App\Http\Controllers\Staff\PosClosingController::class, 'store'])->name('pos-closing.store');
    });

    /*
    |------------------------------------------------------------------
    | Admin Routes
    |------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware('role:admin|manager')->group(function () {
        // QR Codes
        Route::get('/qrcodes', [QRCodeController::class, 'index'])->name('qrcodes.index');
        Route::post('/qrcodes/generate', [QRCodeController::class, 'generate'])->name('qrcodes.generate');
        Route::delete('/qrcodes/{qrcode}', [QRCodeController::class, 'destroy'])->name('qrcodes.destroy');

        // Inventories
        Route::get('/inventories', [InventoryController::class, 'index'])->name('inventories.index');
        Route::post('/inventories', [InventoryController::class, 'store'])->name('inventories.store');
        Route::put('/inventories/{inventory}', [InventoryController::class, 'update'])->name('inventories.update');
        Route::delete('/inventories/{inventory}', [InventoryController::class, 'destroy'])->name('inventories.destroy');
        Route::post('/inventories/{inventory}/transaction', [InventoryController::class, 'transaction'])->name('inventories.transaction');

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

        // Promo / Coupons
        Route::middleware('role:admin|manager')->group(function () {
            Route::resource('coupons', AdminCouponController::class)->except(['show']);
            Route::patch('coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])->name('coupons.toggle');
        });

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

        // Banners (CMS)
        Route::resource('banners', BannerController::class)->except(['create', 'show', 'edit']);
        Route::patch('banners/{banner}/toggle-status', [BannerController::class, 'toggleStatus'])->name('banners.toggle-status');
        Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
    });
});

// Google OAuth
Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

require __DIR__.'/auth.php';
