<?php

declare(strict_types=1);

namespace OCA\FuseMount\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{

	public function __construct()
	{
		parent::__construct('files_fuse_mount');
	}

	public function register(IRegistrationContext $context): void
	{
		include_once __DIR__.'/../../vendor/autoload.php';
	}

	public function boot(IBootContext $context): void
	{
		// TODO: Implement boot() method.
	}
}
