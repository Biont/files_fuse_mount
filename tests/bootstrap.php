<?php

declare(strict_types=1);

(function(){
	$ncAutoloadFile=__DIR__.'/../../../lib/autoloader.php';
	class OC
	{

		public static $SERVERROOT = '';

		public static $CLASSPATH = [];
	}

	OC::$SERVERROOT = str_replace("\\", '/', substr(dirname($ncAutoloadFile), 0, -4));
	require_once OC::$SERVERROOT . '/lib/composer/autoload.php';
	require_once $ncAutoloadFile;
	require_once OC::$SERVERROOT. '/3rdparty/autoload.php';
	$ncAutoloader = new \OC\Autoloader([
		'/lib/private/legacy',
	]);
	$ncAutoloader->addValidRoot(OC::$SERVERROOT.'/tests');
	spl_autoload_register([$ncAutoloader, 'load']);
	require_once __DIR__.'/../vendor/autoload.php';
})();

