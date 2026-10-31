<?php
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaListController;
use App\Http\Controllers\NewznabController;
use App\Http\Controllers\VipController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Öffentlich: Newznab-API (Sonarr/Radarr) und BTCPay-Webhook (ohne CSRF)
Route::get('/api', NewznabController::class)->middleware('throttle:120,1'); // zusätzlich zum User-Limit: pro IP
Route::post('/btcpay/webhook', [VipController::class, 'webhook'])->withoutMiddleware([ValidateCsrfToken::class]);
Route::post('/nowpayments/ipn', [VipController::class, 'nowpaymentsIpn'])->withoutMiddleware([ValidateCsrfToken::class])->name('nowpayments.ipn');

// Registrierung (ersetzt die Breeze-Route, weil diese Datei nach routes/auth.php geladen wird)
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

// Eingeloggte User
Route::middleware('auth')->group(function () {
    Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
    Route::get('/titel/{media}', [MediaController::class, 'show'])->name('title');
    Route::get('/filme', [MediaListController::class, 'movies'])->name('movies');
    Route::get('/serien', [MediaListController::class, 'shows'])->name('shows');
    Route::get('/konto', [DashboardController::class, 'index'])->name('account');
    Route::get('/download/{guid}', [BrowseController::class, 'download'])->name('download');
    Route::post('/account/regenerate-key', [DashboardController::class, 'regenerateKey'])->name('key.regenerate');
    Route::get('/vip', [VipController::class, 'index'])->name('vip');
    Route::post('/vip/buy/{plan}', [VipController::class, 'buy'])->name('vip.buy');
});

// Admin
Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::get('/groups', [AdminController::class, 'groups'])->name('groups');
    Route::post('/groups', [AdminController::class, 'storeGroup'])->name('groups.store');
    Route::post('/groups/{group}/toggle', [AdminController::class, 'toggleGroup'])->name('groups.toggle');
    Route::post('/groups/{group}/reset', [AdminController::class, 'resetGroup'])->name('groups.reset');
    Route::delete('/groups/{group}', [AdminController::class, 'destroyGroup'])->name('groups.destroy');
    Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
    Route::get('/einladungen', [InviteController::class, 'index'])->name('invites');
    Route::post('/einladungen', [InviteController::class, 'store'])->name('invites.store');
    Route::delete('/einladungen/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');
    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings');
    Route::post('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/releases', [AdminController::class, 'releases'])->name('releases');
    Route::post('/releases/bulk', [AdminController::class, 'releasesBulk'])->name('releases.bulk');
    Route::get('/blacklist', [AdminController::class, 'blacklist'])->name('blacklist');
    Route::post('/blacklist', [AdminController::class, 'storeBlacklist'])->name('blacklist.store');
    Route::delete('/blacklist/{rule}', [AdminController::class, 'destroyBlacklist'])->name('blacklist.destroy');
});
