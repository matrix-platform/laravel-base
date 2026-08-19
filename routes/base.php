<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use MatrixPlatform\Http\Controllers\Admin\CfgResourceController;
use MatrixPlatform\Http\Controllers\Admin\FileController;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Http\Controllers\Admin\I18nController;
use MatrixPlatform\Http\Controllers\Admin\I18nResourceController;
use MatrixPlatform\Http\Controllers\Admin\MenuResourceController;
use MatrixPlatform\Http\Controllers\Admin\ModelResourceController;
use MatrixPlatform\Http\Controllers\Admin\OptionsResourceController;
use MatrixPlatform\Http\Controllers\Admin\TemplateResourceController;
use MatrixPlatform\Http\Controllers\Admin\UserController;
use MatrixPlatform\Http\Controllers\CommonController;
use MatrixPlatform\Routing\ActionRoutes;

Route::middleware(['envelope-api', 'locale-api'])->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        ActionRoutes::mount('i18n', I18nController::class);

        Route::prefix('auth')->group(function () {
            ActionRoutes::scan(AuthController::class, 'anonymous');

            Route::middleware('user-api')->group(fn () => ActionRoutes::scan(AuthController::class));
        });

        Route::middleware('user-api')->group(function () {
            ActionRoutes::mount('file', FileController::class);

            Route::middleware('permission-api')->group(function () {
                ActionRoutes::mount('user', UserController::class);
                ActionRoutes::mount('group', GroupController::class);
                ActionRoutes::mount('resource/cfg', CfgResourceController::class);
                ActionRoutes::mount('resource/i18n', I18nResourceController::class);
                ActionRoutes::mount('resource/i18n/menu', MenuResourceController::class);
                ActionRoutes::mount('resource/i18n/model', ModelResourceController::class);
                ActionRoutes::mount('resource/i18n/options', OptionsResourceController::class);
                ActionRoutes::mount('resource/i18n/template', TemplateResourceController::class);
            });
        });
    });

    Route::prefix(config('matrix.api-prefix'))->group(fn () => ActionRoutes::mount('common', CommonController::class));
});
