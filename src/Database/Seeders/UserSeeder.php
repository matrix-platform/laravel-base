<?php //>

namespace MatrixPlatform\Database\Seeders;

use Illuminate\Database\Seeder;
use MatrixPlatform\Models\User;

class UserSeeder extends Seeder {

    public function run(): void {
        $this->account(User::ROOT, 'root@matrix');
        $this->account(2, 'admin');
    }

    private function account(int $id, string $username): void {
        $user = new User();

        $user->id = $id;
        $user->username = $username;
        $user->enable_time = now();

        $user->save();
    }

}
