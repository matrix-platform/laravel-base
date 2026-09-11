<?php //>

use Tests\Stubs\TestHeaderFields;
use Tests\Stubs\TestMenuTypeResolver;
use Tests\Stubs\TestSocialFields;

return [

    'driver' => TestMenuTypeResolver::class,

    'header' => TestHeaderFields::class,

    'social' => TestSocialFields::class,

];
