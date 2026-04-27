<?php //>

return [

    'delete' => ['icon' => 'pi pi-trash', 'severity' => 'danger', 'confirm' => 'actions.delete-confirm', 'modify' => true, 'url' => '{prefix}/delete'],

    'edit' => ['icon' => 'pi pi-pen-to-square', 'severity' => 'secondary', 'navigate' => true, 'url' => '{prefix}/{id}'],

    'insert' => ['icon' => 'pi pi-check', 'severity' => 'primary', 'url' => '{prefix}/insert'],

    'new' => ['icon' => 'pi pi-plus', 'severity' => 'primary', 'navigate' => true, 'url' => '{prefix}/new'],

    'update' => ['icon' => 'pi pi-check', 'severity' => 'primary', 'url' => '{prefix}/{id}/update'],

];
