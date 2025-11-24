<?php

$authType = 'config';
$compress = false;
$allowNoPassword = false;

$i = 1;

$cfg['Servers'][$i]['auth_type'] = $authType;
$cfg['Servers'][$i]['host'] = 'methone-panel-mariadb';
$cfg['Servers'][$i]['compress'] = $compress;
$cfg['Servers'][$i]['AllowNoPassword'] = $allowNoPassword;
$cfg['Servers'][$i]['port'] = '3306';
$cfg['Servers'][$i]['user'] = 'root';
$cfg['Servers'][$i]['password'] = getenv('MARIADB_ROOT_PASSWORD');
$cfg['Servers'][$i]['verbose'] = 'Localhost MariaDB';
$cfg['Servers'][$i]['only_db'] = '';

$cfg['ServerDefault'] = 1;
?>
