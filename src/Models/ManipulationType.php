<?php //>

namespace MatrixPlatform\Models;

enum ManipulationType: int {

    case Created = 1;
    case Deleted = 3;
    case Restored = 4;
    case Updated = 2;

}
