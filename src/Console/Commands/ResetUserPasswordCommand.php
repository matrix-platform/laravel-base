<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Models\User;

class ResetUserPasswordCommand extends Command {

    protected $description = 'Reset the password of an administrator account';

    protected $signature = 'matrix:passwd {username}';

    public function handle(): int {
        $username = strval(array_get_value($this->arguments(), 'username'));
        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $this->error("User '{$username}' does not exist");

            return self::FAILURE;
        }

        $password = $this->secret('New password');

        if ($password === null || $password === '') {
            $this->error('No password supplied');

            return self::FAILURE;
        }

        if ($password !== $this->secret('Retype new password')) {
            $this->error('Passwords do not match');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info('Password updated successfully');

        return self::SUCCESS;
    }

}
