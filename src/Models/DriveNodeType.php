<?php //>

namespace MatrixPlatform\Models;

enum DriveNodeType: string {

    case Folder = 'folder';
    case File = 'file';
    case Root = 'root';

}
