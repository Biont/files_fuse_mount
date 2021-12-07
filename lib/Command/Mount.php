<?php

declare(strict_types=1);

namespace OCA\FuseMount\Command;

use Fuse\FuseOperations;
use Fuse\Mounter;
use OC\Core\Command\Base;
use OC\Files\Cache\HomeCache;
use OC\Files\Storage\Home;
use OC\Memcache\Memcached;
use OC\Memcache\NullCache;
use OC\Memcache\Redis;
use OCA\FuseMount\Filesystem\NextcloudFilesystem;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Mount extends Base
{

	private IRootFolder $storage;

	private IConfig $config;


	public function __construct(
		IConfig $config,
		IRootFolder $storage
	)
	{
		$this->storage = $storage;
		$this->config = $config;
		parent::__construct();
	}

	protected function configure()
	{
		parent::configure();

		$this
			->setName('files:fuse-mount')
			->setDescription('Mounts the filesystem of a given user')
			->addArgument(
				'user_id',
				InputArgument::REQUIRED,
				'will mount all files of the given user'
			);
	}

	private function assertValidCache()
	{
		$validCacheClasses = [
			Redis::class,
			Memcached::class,
			NullCache::class,
		];
		$localCacheClass = $this->config->getSystemValue('memcache.local', null);
		if (!$localCacheClass || !in_array(ltrim($localCacheClass,'\\'), $validCacheClasses, true)) {
			throw new \Exception(
				'"memcache.local" MUST be set to one of the following: '.implode(', ', $validCacheClasses)
			);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		//$this->assertValidCache();
		try {
			$mounter = new Mounter();
		} catch (\Throwable $exception) {
			$output->writeln('<error>'.$exception->getMessage().'</error>');

			return 1;
		}
		if (!file_exists('/dev/fuse')) {
			// mknod /dev/fuse -m 0666 c 10 229
			$output->writeln('<error>/dev/fuse not found! Please see README.md for assistance</error>');

			return 1;
		}
		$user = $input->getArgument('user_id');
		$nextcloudFilesystem = new NextcloudFilesystem($this->storage->getUserFolder($user), $this->homeCache);
		// I am unable to make readBuf/writeBuf work. When I figure this out, we can skip $fuseOperations
		$fuseOperations = new FuseOperations();
		$fuseOperations->getattr = [$nextcloudFilesystem, 'getattr'];
		$fuseOperations->open = [$nextcloudFilesystem, 'open'];
		$fuseOperations->read = [$nextcloudFilesystem, 'read'];
		$fuseOperations->readdir = [$nextcloudFilesystem, 'readdir'];
		$fuseOperations->write = [$nextcloudFilesystem, 'write'];
		$fuseOperations->truncate = [$nextcloudFilesystem, 'truncate'];
		$fuseOperations->flush = [$nextcloudFilesystem, 'flush'];
		$fuseOperations->getxattr = [$nextcloudFilesystem, 'getxattr'];
		$fuseOperations->removexattr = [$nextcloudFilesystem, 'removexattr'];
		$fuseOperations->mknod = [$nextcloudFilesystem, 'mknod'];
		$fuseOperations->mkdir = [$nextcloudFilesystem, 'mkdir'];
		$fuseOperations->unlink = [$nextcloudFilesystem, 'unlink'];
		//$fuseOperations->utime = [$nextcloudFilesystem, 'utime'];
		//$fuseOperations->chmod = [$nextcloudFilesystem, 'chown'];

		$path = '/var/www/html/mnt';
		$debug = null;
		$debug = [
			'',
			'-d',
			'-s',
			'-f',
			$path,
		];

		$mounter->mount($path, $fuseOperations, $debug);

		return 0;
	}
}
