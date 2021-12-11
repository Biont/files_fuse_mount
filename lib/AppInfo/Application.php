<?php

declare(strict_types=1);

namespace OCA\FuseMount\AppInfo;

use OCA\FuseMount\Filesystem\FilesystemFactory;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap
{

	public function __construct()
	{
		parent::__construct('files_fuse_mount');
	}

	public function register(IRegistrationContext $context): void
	{
		include_once __DIR__ . '/../../vendor/autoload.php';
		$context->registerService(FilesystemFactory::class, function (ContainerInterface $c) {
			return new FilesystemFactory($c->get(IRootFolder::class));
		});
	}

	public function boot(IBootContext $context): void
	{
		// TODO: Implement boot() method.
	}
}
