<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;

Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('captcha', 'captcha');
        Route::post('login', 'login');
    });

    Route::middleware('user-api')->group(function () {
        Route::prefix('auth')->controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');
            Route::post('passwd', 'passwd');
            Route::post('profile', 'profile');
        });
    });
});
