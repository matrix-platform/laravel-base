<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use MatrixPlatform\Routing\ActionRoutes;

Route::middleware(['envelope-api', 'locale-api'])->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        Route::prefix('auth')->group(function () {
            ActionRoutes::scan(AuthController::class, 'anonymous');

            Route::middleware('user-api')->group(fn () => ActionRoutes::scan(AuthController::class));
        });
    });
});
