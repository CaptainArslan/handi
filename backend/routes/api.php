<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CustomDishController;
use App\Http\Controllers\Api\V1\DishController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\KitchenController;
use App\Http\Controllers\Api\V1\MealController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PreviewController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TodayController;
use App\Http\Middleware\TrackLastActive;
use Illuminate\Support\Facades\Route;

/*
| All routes are prefixed with /api (bootstrap/app.php) and /v1 here.
| Full reference: docs/api.md · interactive docs: /docs/api
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ---------------------------------------------------------------- Public
    Route::middleware('throttle:api')->group(function () {
        Route::get('health', HealthController::class)->name('health');
        Route::get('library/dishes', [CatalogController::class, 'dishes'])->name('library.dishes');
        Route::get('categories', [CatalogController::class, 'categories'])->name('categories');
        Route::get('cities', [CatalogController::class, 'cities'])->name('cities');
        Route::get('weather', [CatalogController::class, 'weather'])->name('weather');
        Route::get('legal/{document}', [CatalogController::class, 'legal'])->whereIn('document', ['terms', 'privacy'])->name('legal');
        Route::get('image-prompt', [CatalogController::class, 'imagePrompt'])->name('image-prompt');
        Route::post('preview-picks', PreviewController::class)->name('preview-picks');
    });

    // Contact us: open to everyone (a token, if sent, links the message to the account).
    Route::post('contact', ContactController::class)->middleware('throttle:contact')->name('contact');

    // ---------------------------------------------------------------- Auth
    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
        Route::post('password/resend', [PasswordResetController::class, 'resend'])->name('password.resend');
        Route::post('password/verify', [PasswordResetController::class, 'verify'])->name('password.verify');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
    });

    // ---------------------------------------------------------------- Signed in
    Route::middleware(['auth:sanctum', 'throttle:api', TrackLastActive::class])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('me')->name('me.')->group(function () {
            // Account
            Route::get('/', [AccountController::class, 'show'])->name('show');
            Route::patch('/', [AccountController::class, 'update'])->name('update');
            Route::delete('/', [AccountController::class, 'destroy'])->name('destroy');
            Route::post('email/verify', [AccountController::class, 'verifyEmail'])->middleware('throttle:sensitive')->name('email.verify');
            Route::put('password', [AccountController::class, 'password'])->name('password');

            // Kitchen settings
            Route::patch('kitchen', [KitchenController::class, 'update'])->name('kitchen.update');
            Route::put('location', [KitchenController::class, 'location'])->name('location');
            Route::post('devices', [KitchenController::class, 'registerDevice'])->name('devices.store');
            Route::delete('devices/{deviceId}', [KitchenController::class, 'removeDevice'])->name('devices.destroy');

            // Today
            Route::get('today', [TodayController::class, 'show'])->name('today');
            Route::post('today/shuffle', [TodayController::class, 'shuffle'])->name('today.shuffle');
            Route::put('today/plan', [TodayController::class, 'plan'])->name('today.plan');
            Route::delete('today/plan', [TodayController::class, 'unplan'])->name('today.unplan');

            // My dishes
            Route::get('dishes', [DishController::class, 'index'])->name('dishes.index');
            Route::post('dishes', [DishController::class, 'store'])->name('dishes.store');
            Route::get('dishes/{id}', [DishController::class, 'show'])->whereNumber('id')->name('dishes.show');
            Route::patch('dishes/{id}', [DishController::class, 'update'])->whereNumber('id')->name('dishes.update');
            Route::delete('dishes/{id}/overrides', [DishController::class, 'resetOverrides'])->whereNumber('id')->name('dishes.reset');
            Route::post('dishes/{id}/photo', [DishController::class, 'photo'])->whereNumber('id')->name('dishes.photo');
            Route::delete('dishes/{id}', [DishController::class, 'destroy'])->whereNumber('id')->name('dishes.destroy');
            Route::post('dishes/{id}/restore', [DishController::class, 'restore'])->whereNumber('id')->name('dishes.restore');

            Route::get('custom-dishes', [CustomDishController::class, 'index'])->name('custom.index');
            Route::post('custom-dishes', [CustomDishController::class, 'store'])->name('custom.store');
            Route::patch('custom-dishes/{id}', [CustomDishController::class, 'update'])->whereNumber('id')->name('custom.update');
            Route::delete('custom-dishes/{id}', [CustomDishController::class, 'destroy'])->whereNumber('id')->name('custom.destroy');
            Route::post('custom-dishes/{id}/restore', [CustomDishController::class, 'restore'])->whereNumber('id')->name('custom.restore');

            // History
            Route::get('meals', [MealController::class, 'index'])->name('meals.index');
            Route::post('meals', [MealController::class, 'store'])->name('meals.store');
            Route::patch('meals/{id}', [MealController::class, 'update'])->name('meals.update');
            Route::delete('meals/{id}', [MealController::class, 'destroy'])->name('meals.destroy');
            Route::post('meals/{id}/restore', [MealController::class, 'restore'])->name('meals.restore');
            Route::get('stats', [MealController::class, 'stats'])->name('stats');
            Route::post('sync', SyncController::class)->name('sync');

            // Notifications
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::patch('notifications/{id}', [NotificationController::class, 'update'])->whereNumber('id')->name('notifications.update');
            Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id')->name('notifications.destroy');
            Route::post('notifications/{id}/restore', [NotificationController::class, 'restore'])->whereNumber('id')->name('notifications.restore');
            Route::get('notification-settings', [KitchenController::class, 'notificationSettings'])->name('notification-settings.show');
            Route::put('notification-settings', [KitchenController::class, 'updateNotificationSettings'])->name('notification-settings.update');
            Route::post('notification-settings/test', [KitchenController::class, 'testNotification'])->middleware('throttle:sensitive')->name('notification-settings.test');
        });
    });
});
