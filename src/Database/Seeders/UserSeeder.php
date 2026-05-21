<?php //>

namespace MatrixPlatform\Database\Seeders;

use Illuminate\Database\Seeder;
use MatrixPlatform\Models\User;

class UserSeeder extends Seeder {

    public function run() {
        $root = new User();
        $root->id = User::ROOT;
        $root->username = 'root@matrix';
        $root->enable_time = now();
        $root->save();

        $admin = new User();
        $admin->id = User::ADMIN;
        $admin->username = 'admin';
        $admin->enable_time = now();
        $admin->save();
    }

}
