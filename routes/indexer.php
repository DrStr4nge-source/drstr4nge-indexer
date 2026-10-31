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
Route::get('/rss', \App\Http\Controllers\FeedController::class)->middleware('throttle:60,1')->name('rss');
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
    Route::get('/merkliste', [\App\Http\Controllers\WatchlistController::class, 'index'])->name('watchlist');
    Route::post('/merken/{media}', [\App\Http\Controllers\WatchlistController::class, 'toggle'])->name('watch.toggle')->middleware('throttle:60,1');
    Route::post('/melden/{release:guid}', [\App\Http\Controllers\ReportController::class, 'store'])->name('report')->middleware('throttle:20,1');
    Route::get('/vip', [VipController::class, 'index'])->name('vip');
    Route::post('/vip/buy/{plan}', [VipController::class, 'buy'])->name('vip.buy');
});

// Admin
Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/system', [AdminController::class, 'system'])->name('system');
    Route::get('/log', [\App\Http\Controllers\Admin\LogController::class, 'index'])->name('log');
    Route::post('/log/leeren', [\App\Http\Controllers\Admin\LogController::class, 'clear'])->name('log.clear');
    Route::get('/meldungen', [\App\Http\Controllers\Admin\ReportAdminController::class, 'index'])->name('reports');
    Route::post('/meldungen/{report}', [\App\Http\Controllers\Admin\ReportAdminController::class, 'act'])->name('reports.act');
    Route::get('/2fa', [\App\Http\Controllers\Admin\TwoFactorController::class, 'show'])->name('2fa');
    Route::post('/2fa/start', [\App\Http\Controllers\Admin\TwoFactorController::class, 'start'])->name('2fa.start');
    Route::post('/2fa/bestaetigen', [\App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('2fa.confirm')->middleware('throttle:10,1');
    Route::post('/2fa/aus', [\App\Http\Controllers\Admin\TwoFactorController::class, 'disable'])->name('2fa.disable')->middleware('throttle:5,1');
    Route::post('/2fa/codes', [\App\Http\Controllers\Admin\TwoFactorController::class, 'recovery'])->name('2fa.recovery')->middleware('throttle:5,1');
    Route::get('/2fa/pruefen', [\App\Http\Controllers\Admin\TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/pruefen', [\App\Http\Controllers\Admin\TwoFactorController::class, 'verify'])->name('2fa.challenge.verify')->middleware('throttle:6,1');
    Route::get('/zugangsdaten', [\App\Http\Controllers\Admin\CredentialsController::class, 'edit'])->name('credentials');
    Route::post('/zugangsdaten/{group}', [\App\Http\Controllers\Admin\CredentialsController::class, 'update'])->name('credentials.update')->where('group', 'usenet|tmdb|mail|payment|backup');
    Route::post('/zugangsdaten/test/{what}', [\App\Http\Controllers\Admin\CredentialsController::class, 'test'])->name('credentials.test')->where('what', 'usenet|mail|gdrive');
    Route::post('/zugangsdaten/gdrive', [\App\Http\Controllers\Admin\CredentialsController::class, 'gdrive'])->name('credentials.gdrive');
    Route::post('/zugangsdaten/sichern', [\App\Http\Controllers\Admin\CredentialsController::class, 'backupNow'])->name('credentials.backup');
    Route::get('/predb', [\App\Http\Controllers\Admin\PreController::class, 'index'])->name('predb');
    Route::post('/predb/irc', [\App\Http\Controllers\Admin\PreController::class, 'updateIrc'])->name('predb.irc');
    Route::post('/predb/irc/reconnect', [\App\Http\Controllers\Admin\PreController::class, 'reconnect'])->name('predb.reconnect');
    Route::post('/predb/irc/reset', [\App\Http\Controllers\Admin\PreController::class, 'resetIrc'])->name('predb.reset');
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
