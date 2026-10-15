<?php

use App\Http\Controllers\Site\SiteController;
use App\Http\Middleware\SiteHeaders;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| Public website (domain root). Stateless: no session or cookies, so pages can be cached.
| The admin panel lives at /{ADMIN_PATH} (default /admin) and is never linked from the site.
*/
Route::middleware(SiteHeaders::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->name('site.')
    ->group(function () {
        Route::get('/', [SiteController::class, 'home'])->name('home');
        Route::get('/privacy', [SiteController::class, 'legal'])->defaults('document', 'privacy')->name('privacy');
        Route::get('/terms', [SiteController::class, 'legal'])->defaults('document', 'terms')->name('terms');
        Route::get('/delete-account', [SiteController::class, 'deleteAccount'])->name('delete-account');
        Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
    });
