<?php
/**
 *   2009-2016 ohmyweb!
 *
 *   @author    ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_4($object)
{
    return 
        $object->registerHook('displayHomeTab') &&
        $object->registerHook('displayHomeTabContent') &&
        $object->registerHook('productTab') &&
        $object->registerHook('productTabContent') &&
        $object->registerHook('displayShoppingCart') &&
        $object->registerHook('displayHupiRecommendations')
    ;
    
}
