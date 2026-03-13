<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Models\User;

class ResetUserPassword extends Command {

    protected $signature = 'matrix:passwd {username}';

    public function handle() {
        $user = User::where('username', $this->argument('username'))->first();

        if (!$user) {
            $this->error("User '{$this->argument('username')}' does not exist");

            return Command::FAILURE;
        }

        $password = $this->secret('New password');

        if (!$password) {
            $this->error('No password supplied');

            return Command::FAILURE;
        }

        if ($password !== $this->secret('Retype new password')) {
            $this->error('Passwords do not match');

            return Command::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info('Password updated successfully');

        return Command::SUCCESS;
    }

}
