<?php //>

namespace MatrixPlatform\Models;

enum UserLogType: string {

    case ChangePassword = 'ChangePassword';
    case Login = 'Login';
    case LoginFailed = 'LoginFailed';
    case Logout = 'Logout';

}
