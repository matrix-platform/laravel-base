<?php //>

namespace MatrixPlatform\Messaging;

enum MessageStatus: int {

    case Failed = 3;
    case Scheduled = 1;
    case Sending = 4;
    case Success = 2;

}
