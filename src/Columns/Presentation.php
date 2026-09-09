<?php //>

namespace MatrixPlatform\Columns;

enum Presentation: string {

    case Count = 'count';
    case DriveFile = 'drive-file';
    case DriveImage = 'drive-image';
    case Hidden = 'hidden';
    case MultiSelect = 'multi-select';
    case Password = 'password';
    case Plain = 'plain';
    case Select = 'select';
    case Switch = 'switch';

}
