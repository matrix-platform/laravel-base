<?php //>

namespace Tests\Feature\Services;

use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use MatrixPlatform\Database\Seeders\UserSeeder;
use MatrixPlatform\Services\Admin\AuthService;
use Tests\FeatureTestCase;

class AuthServiceOctaneTest extends FeatureTestCase {

    protected function afterRefreshingDatabase() {
        $this->seed(UserSeeder::class);
    }

    public function test_failed_logins_do_not_accumulate_event_listeners() {
        $before = count(Event::getListeners(TransactionRolledBack::class));

        for ($i = 0; $i < 100; $i++) {
            Cache::put("captcha:tok{$i}", hash('sha256', '00000'), cfg('admin.captcha-ttl'));

            try {
                app(AuthService::class)->login('root@matrix', 'wrong-password', "tok{$i}", '00000');
            } catch (\Throwable) {}

            app()->forgetScopedInstances();
        }

        $this->assertEquals($before, count(Event::getListeners(TransactionRolledBack::class)));
    }

}
