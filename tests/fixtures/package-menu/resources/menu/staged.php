<?php //>

return [

    'widget' => ['ranking' => 100, 'parent' => null, 'group' => true, 'tag' => 'query'],

    'widget/insert' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/{id}/update' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/staged/{key}' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/staged/{key}/validate' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/{id}/staged/{key}' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/{id}/staged/{key}/validate' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/{widget_id}/trinket' => ['parent' => 'widget', 'group' => true, 'tag' => 'query'],

    'widget/{widget_id}/gadget' => ['parent' => 'widget', 'group' => true, 'tag' => 'query'],

    'gadget' => ['ranking' => 200, 'parent' => null, 'group' => true, 'tag' => 'query'],

];
