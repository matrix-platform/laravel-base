<?php //>

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeatureTestCase extends TestCase {

    use RefreshDatabase;

    protected function defineEnvironment($app) {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel_base_test'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
        $app['config']->set('matrix.admin-api-prefix', 'admin');
        $app['config']->set('matrix.admin-menus', 'base');
        $app['config']->set('matrix.exclude-admin-menu-nodes', '');
        $app['config']->set('matrix.packages', 'base');
    }

    protected function defineDatabaseMigrations() {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function loginAs($userId) {
        $token = Str::uuid()->toString();

        DB::table('base_auth_token')->insert([
            'create_time' => now(),
            'modify_time' => now()->startOfMinute(),
            'target_id' => $userId,
            'token' => $token,
            'type' => 'User',
        ]);

        return $this->withToken($token);
    }

    protected function loginAsMember($memberId) {
        $token = Str::uuid()->toString();

        DB::table('base_auth_token')->insert([
            'create_time' => now(),
            'modify_time' => now()->startOfMinute(),
            'target_id' => $memberId,
            'token' => $token,
            'type' => 'Member',
        ]);

        return $this->withToken($token);
    }

}
