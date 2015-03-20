<?php
function upgrade_module_1_2($module)
{
    $tbl = $module->table();
    $q = "CREATE TABLE IF NOT EXISTS $tbl("
       .' order_id INT NOT NULL,'
       .' token CHAR(32) NOT NULL,'
       .' PRIMARY KEY (`order_id`))';

    $res = DB::getInstance()->Execute($q);

    return $module->registerHook('newOrder')
        && $module->registerHook('paymentConfirm')
        && $res;
}
