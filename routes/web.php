<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard') : view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
});

Route::post('/webhooks/cashfree/secure-id', [WebhookController::class, 'secureId'])->name('webhooks.secureid');
Route::post('/webhooks/cashfree/pg', [WebhookController::class, 'pg'])->name('webhooks.pg');
Route::get('/done/{vid}', [WebhookController::class, 'done'])->name('public.done');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/checks', [CheckController::class, 'index'])->name('checks');
    Route::get('/checks/{product}', [CheckController::class, 'show'])->name('checks.show');
    Route::post('/checks/{product}', [CheckController::class, 'run'])->name('checks.run')->middleware('throttle:checks');

    Route::get('/history', [VerificationController::class, 'index'])->name('verifications');
    Route::get('/history/export', [VerificationController::class, 'export'])->name('verifications.export');
    Route::get('/v/{verification}', [VerificationController::class, 'show'])->name('verifications.show');
    Route::post('/v/{verification}/refresh', [VerificationController::class, 'refresh'])->name('verifications.refresh')->middleware('throttle:refresh');
    Route::post('/v/{verification}/otp', [VerificationController::class, 'otp'])->name('verifications.otp')->middleware('throttle:otp');
    Route::post('/v/{verification}/documents/{type}', [VerificationController::class, 'document'])->name('verifications.document');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/topup', [WalletController::class, 'topup'])->name('wallet.topup');
    Route::get('/wallet/topup/return', [WalletController::class, 'topupReturn'])->name('wallet.topup.return');
    Route::post('/wallet/bank-transfer', [WalletController::class, 'bank'])->name('wallet.bank');

    Route::get('/bulk', [BulkController::class, 'index'])->name('bulk');
    Route::post('/bulk', [BulkController::class, 'store'])->name('bulk.store');
    Route::get('/bulk/sample/{product}', [BulkController::class, 'sample'])->name('bulk.sample');
    Route::get('/bulk/{job}', [BulkController::class, 'show'])->name('bulk.show');
    Route::post('/bulk/{job}/resume', [BulkController::class, 'resume'])->name('bulk.resume');
    Route::get('/bulk/{job}/download', [BulkController::class, 'download'])->name('bulk.download');

    Route::get('/developers', [ApiKeyController::class, 'index'])->name('api-keys');
    Route::post('/developers/keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/developers/keys/{key}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
    Route::get('/developers/docs', [ApiKeyController::class, 'docs'])->name('docs');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/password', [SettingsController::class, 'password'])->name('settings.password');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
        Route::get('/customers/{user}', [AdminController::class, 'customer'])->name('customer');
        Route::post('/customers/{user}/kyb', [AdminController::class, 'kyb'])->name('customer.kyb');
        Route::post('/customers/{user}/status', [AdminController::class, 'status'])->name('customer.status');
        Route::post('/customers/{user}/wallet', [AdminController::class, 'adjust'])->name('customer.wallet');
        Route::get('/verifications', [AdminController::class, 'verifications'])->name('verifications');
        Route::get('/topups', [AdminController::class, 'topups'])->name('topups');
        Route::post('/topups/{topup}/review', [AdminController::class, 'approveTopup'])->name('topups.review');
        Route::post('/topups/{topup}/recheck', [AdminController::class, 'recheckTopup'])->name('topups.recheck');
        Route::get('/pricing', [AdminController::class, 'pricing'])->name('pricing');
        Route::post('/pricing', [AdminController::class, 'savePricing'])->name('pricing.save');
        Route::get('/cashfree', [AdminController::class, 'provider'])->name('provider');
        Route::post('/cashfree/keys', [AdminController::class, 'saveKeys'])->name('provider.keys');
        Route::post('/cashfree/ping', [AdminController::class, 'ping'])->name('provider.ping');
    });
});
