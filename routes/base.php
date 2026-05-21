<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use MatrixPlatform\Http\Controllers\Admin\FileController;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Http\Controllers\Admin\I18nController;
use MatrixPlatform\Http\Controllers\Admin\UserController;
use MatrixPlatform\Http\Controllers\CommonController;

Route::middleware('locale-api')->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        Route::prefix('auth')->controller(AuthController::class)->scan('anonymous');
        Route::prefix('i18n')->controller(I18nController::class)->scan();

        Route::middleware('user-api')->group(function () {
            Route::prefix('auth')->controller(AuthController::class)->scan();
            Route::prefix('file')->controller(FileController::class)->scan();
        });

        Route::middleware('user-api:admin')->group(function () {
            Route::prefix('group')->controller(GroupController::class)->scan();
            Route::prefix('user')->controller(UserController::class)->scan();
        });
    });

    Route::prefix(config('matrix.api-prefix'))->group(function () {
        Route::prefix('common')->controller(CommonController::class)->scan();
    });
});
