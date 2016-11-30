<?php
/**
 *   2009-2016 ohmyweb!
 *
 *   @author    ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
 */

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'hupilytics` (
    `id_hupilytics` int(11) NOT NULL AUTO_INCREMENT,
	`id_order` int(11) NOT NULL,
	`id_customer` int(10) NOT NULL,
	`id_shop` int(11) NOT NULL,
	`sent` tinyint(1) DEFAULT NULL,
	`date_add` datetime DEFAULT NULL,
	PRIMARY KEY (`id_hupilytics`),
	KEY `id_order` (`id_order`),
	KEY `sent` (`sent`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
