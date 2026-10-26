<?php

use App\Http\Controllers\BioController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\KaltaController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShortController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Pages people can land on are available in every language: the default
| language at the root, the others under a prefix (/ru, /en). Route names are
| "home", "ru.home", "en.home", ... so build their URLs with lroute().
|
*/

foreach (config('kalta.locales') as $locale => $label) {
    $isDefault = $locale === config('kalta.default_locale');

    Route::middleware("locale:$locale")
        ->prefix($isDefault ? '' : $locale)
        ->name($isDefault ? '' : "$locale.")
        ->group(function () {
            Route::get('/', [HomeController::class, 'index'])->name('home');
            Route::get('files/create', [FileController::class, 'create'])->name('files.create');
            Route::get('qr', [QrController::class, 'create'])->name('qr.create');
            Route::get('codes/create', [CodeController::class, 'create'])->name('codes.create');
            Route::get('bio/create', [BioController::class, 'create'])->name('bio.create');
            Route::get('invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
            Route::get('created/{kalta}', [KaltaController::class, 'created'])->middleware('noindex')->name('created');

            Route::middleware('throttle:create')->group(function () {
                Route::post('kaltas/make', [ShortController::class, 'make'])->name('shorts.make');
                Route::post('files', [FileController::class, 'store'])->name('files.store');
                Route::post('codes', [CodeController::class, 'store'])->name('codes.store');
                Route::post('bio', [BioController::class, 'store'])->name('bio.store');
                Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            });

            Route::post('qr', [QrController::class, 'generate'])->middleware('throttle:qr')->name('qr.generate');
        });
}

// Wedding invitations: private pages reached through secret links
Route::middleware('noindex')->group(function () {
    Route::get('invitations/{invitation:manage_token}/manage', [InvitationController::class, 'manage'])->name('invitations.manage');
    Route::get('invitations/{invitation:manage_token}/edit', [InvitationController::class, 'edit'])->name('invitations.edit');
    Route::get('invitations/{invitation:manage_token}/guests.csv', [InvitationController::class, 'export'])->name('invitations.export');
    Route::get('invitations/{invitation:manage_token}/guests/{guest}/edit', [InvitationController::class, 'editGuest'])
        ->scopeBindings()->name('invitations.guests.edit');
    Route::get('i/{guest:token}', [InvitationController::class, 'guest'])->name('invitations.guest');
    Route::get('i/{guest:token}/event.ics', [InvitationController::class, 'calendar'])->name('invitations.guest.calendar');

    // What the hosts do on their page; tapping "Telegram" for 30 guests in a row must not hit a limit
    Route::middleware('throttle:manage')->group(function () {
        Route::patch('invitations/{invitation:manage_token}', [InvitationController::class, 'update'])->name('invitations.update');
        Route::delete('invitations/{invitation:manage_token}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        Route::post('invitations/{invitation:manage_token}/guests', [InvitationController::class, 'addGuests'])->name('invitations.guests.store');
        Route::patch('invitations/{invitation:manage_token}/guests/{guest}', [InvitationController::class, 'updateGuest'])
            ->scopeBindings()->name('invitations.guests.update');
        Route::delete('invitations/{invitation:manage_token}/guests/{guest}', [InvitationController::class, 'destroyGuest'])
            ->scopeBindings()->name('invitations.guests.destroy');
        Route::post('invitations/{invitation:manage_token}/guests/{guest}/sent', [InvitationController::class, 'markSent'])
            ->scopeBindings()->name('invitations.guests.sent');
    });

    Route::middleware('throttle:invite')->group(function () {
        Route::post('invitations/{invitation:manage_token}/send', [InvitationController::class, 'send'])->name('invitations.send');
        Route::post('i/{guest:token}/rsvp', [InvitationController::class, 'rsvp'])->name('invitations.rsvp');
    });
});

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('kaltas/{kalta}', [KaltaController::class, 'destroy'])->name('kaltas.destroy');
});

// Language switch for pages that have no language in their URL (login, link-in-bio pages, ...)
Route::get('lang/{locale}', function (Request $request, string $locale) {
    abort_unless(array_key_exists($locale, config('kalta.locales')), 404);

    // only ever go back to a page on this site ("kalta.uz.evil.example" must not pass)
    $back = url()->previous();
    $target = parse_url($back, PHP_URL_HOST) === $request->getHost() ? $back : url('/');

    return redirect($target)->withCookie(cookie('lang', $locale, 60 * 24 * 365));
})->name('lang.switch');

// The picture chat apps show for an invitation link
Route::get('preview/{kalta}.jpg', [InvitationController::class, 'preview'])->where('kalta', '[A-Za-z0-9_-]+')->name('invitations.preview');

Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

// Uploaded avatars and covers
Route::get('media/{folder}/{file}', [MediaController::class, 'show'])
    ->where(['folder' => 'avatars|covers', 'file' => '[A-Za-z0-9_-]+\.[A-Za-z]{3,4}'])
    ->name('media');

// Old numeric bio addresses
Route::get('bio/{bio}', [BioController::class, 'show'])->whereNumber('bio')->name('bio.show');

require __DIR__ . '/auth.php';

// Short codes: keep these last so they never shadow a real page
Route::get('{kalta}/event.ics', [KaltaController::class, 'calendar'])->where('kalta', '[A-Za-z0-9_-]+')->name('kaltas.calendar');
Route::get('{kalta}/raw', [KaltaController::class, 'raw'])->where('kalta', '[A-Za-z0-9_-]+')->name('kaltas.raw');
Route::get('{kalta}', [KaltaController::class, 'show'])->where('kalta', '[A-Za-z0-9_-]+')->name('kaltas.show');
