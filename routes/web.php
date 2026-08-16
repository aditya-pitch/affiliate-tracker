<?php

use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sign in (spec section 3)
|--------------------------------------------------------------------------
|
| Four steps, in order: email, password, date of birth, emailed one-time code.
| Each step checks the flow is actually at that stage before doing anything, so
| the ordering cannot be skipped by posting directly to a later route.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showEmail'])->name('login');
    Route::post('/login', [LoginController::class, 'submitEmail'])->name('login.email');

    Route::get('/login/password', [LoginController::class, 'showPassword'])->name('login.password');
    Route::post('/login/password', [LoginController::class, 'submitPassword']);

    Route::get('/login/verify', [LoginController::class, 'showDob'])->name('login.dob');
    Route::post('/login/verify', [LoginController::class, 'submitDob']);

    Route::get('/login/code', [LoginController::class, 'showCode'])->name('login.code');
    Route::post('/login/code', [LoginController::class, 'submitCode']);
    Route::post('/login/code/resend', [LoginController::class, 'resendCode'])->name('login.code.resend');

    // Forgot password / reset (spec section 3)
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| The affiliate dashboard (spec sections 4, 5 and 6)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'affiliate'])->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sales/{sale}', [DashboardController::class, 'show'])->name('sales.show');

    // Polled every few seconds by an open dashboard (spec section 5.6).
    Route::get('/sales/{sale}/live', [DashboardController::class, 'live'])->name('dashboard.live');

    // Settlement, once a sale has ended (spec section 5.7).
    Route::get('/sales/{sale}/report.xlsx', [ReportController::class, 'download'])->name('sales.report');
    Route::post('/sales/{sale}/invoice', [InvoiceController::class, 'store'])->name('sales.invoice.store');
    Route::get('/sales/{sale}/invoice', [InvoiceController::class, 'download'])->name('sales.invoice.download');

    // Settings, from the header (spec sections 4 and 6).
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});

/*
|--------------------------------------------------------------------------
| Internal — our team only (spec section 5.7)
|--------------------------------------------------------------------------
|
| "This is an internal / admin function; please restrict it to authorised team
| members." Creators cannot reach any of this.
|
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::post('/settlements/{settlement}/pay', [SettlementController::class, 'markPaid'])->name('settlements.pay');
    Route::get('/settlements/{settlement}/invoice', [SettlementController::class, 'downloadInvoice'])->name('settlements.invoice');
    Route::post('/sales/{sale}/close', [SettlementController::class, 'closeSale'])->name('sales.close');
});
