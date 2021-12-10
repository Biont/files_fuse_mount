<?php

declare(strict_types=1);
foreach ([
	'FUSE_MOUNT_DIR',
	'NC_USER_1',
	'NC_USER_2',
] as $envVarName) {
	$value = getenv($envVarName);
	if ($value === false) {
		throw new RuntimeException('"'.$envVarName.'" environment variable not set. Aborting');
	}
}
unset($value);
unset($envVarName);

define('PHPUNIT_RUN', 1);

require_once __DIR__.'/../../../lib/base.php';
require_once __DIR__.'/../vendor/autoload.php';

//\OC::$loader->addValidRoot(OC::$SERVERROOT.'/tests');
//\OC_App::loadApp('files_fuse_mount');
//
//OC_Hook::clear();

