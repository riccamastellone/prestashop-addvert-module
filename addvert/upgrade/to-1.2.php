<?php
function upgrade_module_1_2($module)
{
    return $module->registerHook('newOrder')
        && $module->registerHook('paymentConfirm')
        && $module->create_table();
}
