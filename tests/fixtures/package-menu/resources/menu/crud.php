<?php //>

return [

    'widget' => ['icon' => 'fa-cube', 'ranking' => 100, 'parent' => null, 'group' => true, 'tag' => 'query'],

    'widget/{id}' => ['parent' => 'widget', 'tag' => 'query'],

    'widget/{id}/update' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/{id}/copy' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/new' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/insert' => ['parent' => 'widget', 'tag' => 'insert'],

    'widget/delete' => ['parent' => 'widget', 'tag' => 'delete'],

    'widget/sort' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/sort/save' => ['parent' => 'widget', 'tag' => 'update'],

    'widget/{widget_id}/trinket' => ['parent' => 'widget/{id}', 'tag' => 'query'],

    'widget/{widget_id}/trinket/{id}' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'query'],

    'widget/{widget_id}/trinket/{id}/update' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'update'],

    'widget/{widget_id}/trinket/{id}/copy' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'insert'],

    'widget/{widget_id}/trinket/new' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'insert'],

    'widget/{widget_id}/trinket/insert' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'insert'],

    'widget/{widget_id}/trinket/delete' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'delete'],

    'widget/{widget_id}/trinket/sort' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'update'],

    'widget/{widget_id}/trinket/sort/save' => ['parent' => 'widget/{widget_id}/trinket', 'tag' => 'update'],

    'gizmo' => ['ranking' => 200, 'parent' => null, 'group' => true, 'tag' => 'query'],

];
