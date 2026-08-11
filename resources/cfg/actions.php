<?php //>

return [

    'delete' => ['icon' => 'fa-solid fa-trash-can', 'severity' => 'danger', 'confirm' => 'actions.delete-confirm', 'modify' => true, 'url' => '{prefix}/delete'],

    'edit' => ['icon' => 'fa-solid fa-pen-to-square', 'severity' => 'secondary', 'navigate' => true, 'url' => '{prefix}/{id}'],

    'insert' => ['icon' => 'fa-solid fa-check', 'severity' => 'primary', 'url' => '{prefix}/insert'],

    'new' => ['icon' => 'fa-solid fa-plus', 'severity' => 'primary', 'navigate' => true, 'url' => '{prefix}/new'],

    'update' => ['icon' => 'fa-solid fa-check', 'severity' => 'primary', 'url' => '{prefix}/{id}/update'],

];
