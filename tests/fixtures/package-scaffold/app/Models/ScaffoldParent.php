<?php //>

namespace App\Models;

use App\Models\Declarations\ScaffoldParentDeclaration;
use MatrixPlatform\Attributes\Declared;
use MatrixPlatform\Models\BaseModel;

#[Declared(ScaffoldParentDeclaration::class)]
class ScaffoldParent extends BaseModel {

    const TRACEABLE = false;

    protected $table = 'scaffold_parent';

}
