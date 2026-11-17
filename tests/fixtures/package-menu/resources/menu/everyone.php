<?php //>

return [

    'widget' => ['ranking' => 100, 'parent' => null, 'group' => true, 'tag' => 'query'],

    'widget/{widget_id}/trinket' => ['parent' => 'widget', 'group' => true, 'tag' => 'user'],

];
