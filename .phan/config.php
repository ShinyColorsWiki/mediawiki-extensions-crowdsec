<?php

$config = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$ip = getenv( 'MW_INSTALL_PATH' ) ?: '../..';
$config['directory_list'][] = $ip . '/extensions/AbuseFilter/includes';
$config['exclude_analysis_directory_list'][] = $ip . '/extensions/AbuseFilter/includes';
return $config;
