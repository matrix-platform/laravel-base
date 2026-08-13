<?php //>

return [

    'copy' => ['icon' => 'fa-solid fa-copy', 'severity' => 'secondary', 'confirm' => 'actions.copy-confirm', 'modify' => true, 'url' => '{prefix}/{id}/copy'],

    'delete' => ['icon' => 'fa-solid fa-trash-can', 'severity' => 'danger', 'confirm' => 'actions.delete-confirm', 'modify' => true, 'url' => '{prefix}/delete'],

    'edit' => ['icon' => 'fa-solid fa-pen-to-square', 'severity' => 'secondary', 'navigate' => true, 'url' => '{prefix}/{id}'],

    'export' => ['icon' => 'fa-solid fa-file-export', 'severity' => 'secondary', 'url' => '{prefix}/export'],

    'insert' => ['icon' => 'fa-solid fa-check', 'severity' => 'primary', 'url' => '{prefix}/insert'],

    'new' => ['icon' => 'fa-solid fa-plus', 'severity' => 'primary', 'navigate' => true, 'url' => '{prefix}/new'],

    'sort' => ['icon' => 'fa-solid fa-up-down', 'severity' => 'secondary', 'navigate' => true, 'url' => '{prefix}/sort'],

    'update' => ['icon' => 'fa-solid fa-check', 'severity' => 'primary', 'url' => '{prefix}/{id}/update'],

];
