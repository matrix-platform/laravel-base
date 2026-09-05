<?php //>

namespace MatrixPlatform\Models;

enum UserLogType: string {

    case ChangePassword = 'ChangePassword';
    case Login = 'Login';
    case LoginFailed = 'LoginFailed';
    case Logout = 'Logout';
    case MfaChallengeFailed = 'MfaChallengeFailed';
    case MfaDisabled = 'MfaDisabled';
    case MfaEnabled = 'MfaEnabled';

}
