<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;

Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->scan('anonymous');

    Route::middleware('user-api')->group(function () {
        Route::prefix('auth')->controller(AuthController::class)->scan();
    });

    Route::middleware('user-api:admin')->group(function () {
    });
});
