<?php //>

use Illuminate\Support\Facades\Route;
use MatrixPlatform\Http\Controllers\Admin\AuthController;
use MatrixPlatform\Http\Controllers\Admin\CfgResourceController;
use MatrixPlatform\Http\Controllers\Admin\CityAreaController;
use MatrixPlatform\Http\Controllers\Admin\CityController;
use MatrixPlatform\Http\Controllers\Admin\DriveController;
use MatrixPlatform\Http\Controllers\Admin\FileController;
use MatrixPlatform\Http\Controllers\Admin\GroupController;
use MatrixPlatform\Http\Controllers\Admin\I18nController;
use MatrixPlatform\Http\Controllers\Admin\I18nResourceController;
use MatrixPlatform\Http\Controllers\Admin\MailLogController;
use MatrixPlatform\Http\Controllers\Admin\MenuResourceController;
use MatrixPlatform\Http\Controllers\Admin\ModelResourceController;
use MatrixPlatform\Http\Controllers\Admin\OptionsResourceController;
use MatrixPlatform\Http\Controllers\Admin\PushLogController;
use MatrixPlatform\Http\Controllers\Admin\SmsLogController;
use MatrixPlatform\Http\Controllers\Admin\TelegramLogController;
use MatrixPlatform\Http\Controllers\Admin\TemplateResourceController;
use MatrixPlatform\Http\Controllers\Admin\UserController;
use MatrixPlatform\Http\Controllers\Admin\UserTelegramSubscriptionController;
use MatrixPlatform\Http\Controllers\CommonController;
use MatrixPlatform\Http\Controllers\MemberPushSubscriptionController;
use MatrixPlatform\Http\Controllers\PreferenceController;
use MatrixPlatform\Http\Controllers\TelegramWebhookController;
use MatrixPlatform\Routing\ActionRoutes;

Route::middleware(['envelope-api', 'locale-api'])->group(function () {
    Route::prefix(config('matrix.admin-api-prefix'))->group(function () {
        ActionRoutes::mount('i18n', I18nController::class);

        Route::prefix('auth')->group(function () {
            ActionRoutes::scan(AuthController::class, 'anonymous');

            Route::middleware('user-api')->group(fn () => ActionRoutes::scan(AuthController::class));

            Route::middleware('user-aware-api')->group(fn () => ActionRoutes::scan(AuthController::class, 'user-aware'));
        });

        Route::middleware('user-api')->group(function () {
            ActionRoutes::mount('drive', DriveController::class);
            ActionRoutes::mount('file', FileController::class);
            ActionRoutes::mount('user/preference', PreferenceController::class);
            ActionRoutes::mount('user/telegram', UserTelegramSubscriptionController::class);

            Route::middleware('permission-api')->group(function () {
                ActionRoutes::mount('city', CityController::class);
                ActionRoutes::mount('city/{city_id}/area', CityAreaController::class);
                ActionRoutes::mount('group', GroupController::class);
                ActionRoutes::mount('mail-log', MailLogController::class);
                ActionRoutes::mount('push-log', PushLogController::class);
                ActionRoutes::mount('resource/cfg', CfgResourceController::class);
                ActionRoutes::mount('resource/i18n/menu', MenuResourceController::class);
                ActionRoutes::mount('resource/i18n/model', ModelResourceController::class);
                ActionRoutes::mount('resource/i18n/options', OptionsResourceController::class);
                ActionRoutes::mount('resource/i18n/template', TemplateResourceController::class);
                ActionRoutes::mount('resource/i18n', I18nResourceController::class);
                ActionRoutes::mount('sms-log', SmsLogController::class);
                ActionRoutes::mount('telegram-log', TelegramLogController::class);
                ActionRoutes::mount('user', UserController::class);
            });
        });

        ActionRoutes::fallback();
    });

    Route::prefix(config('matrix.api-prefix'))->group(function () {
        ActionRoutes::mount('common', CommonController::class);

        Route::middleware('member-api')->group(function () {
            ActionRoutes::mount('member/preference', PreferenceController::class);
            ActionRoutes::mount('member/push', MemberPushSubscriptionController::class);
        });

        Route::middleware('telegram-webhook-api')->group(fn () => ActionRoutes::mount('telegram', TelegramWebhookController::class));

        ActionRoutes::fallback();
    });

    Route::prefix(config('matrix.vendor-api-prefix'))->group(function () {
        Route::middleware('vendor-api')->group(fn () => ActionRoutes::mount('preference', PreferenceController::class));

        ActionRoutes::fallback();
    });
});
