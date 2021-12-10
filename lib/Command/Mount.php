<?php

declare(strict_types=1);

namespace OCA\FuseMount\Command;

use Fuse\FilesystemInterface;
use Fuse\FuseOperations;
use Fuse\Mounter;
use OC\Core\Command\Base;
use OC\Files\View;
use OC\Memcache\Memcached;
use OC\Memcache\NullCache;
use OC\Memcache\Redis;
use OCA\FuseMount\Filesystem\DelegatingUserFilesystem;
use OCA\FuseMount\Filesystem\UserFileSystem;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use Symfony\Component\Console\Exception\RuntimeException;
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
	) {
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
				'mount_point',
				InputArgument::REQUIRED,
				'Where to mount the filesystem root'
			)
			->addOption(
				'user',
				'u',
				InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
				'Specify the user of the target filesystem. Can be passed multiple times.'
				.' If multiple users are configured, the filesystem will contain the individual user folders at its root'
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
		if (!$localCacheClass || !in_array(ltrim($localCacheClass, '\\'), $validCacheClasses, true)) {
			throw new \Exception(
				'"memcache.local" MUST be set to one of the following: '.implode(', ', $validCacheClasses)
			);
		}
	}

	private function tryCompileUserList(InputInterface $input): array
	{
		if (!$input->hasOption('user')) {
			goto error;
		}
		$users = $input->getOption('user');
		if (!$users) {
			goto error;
		}
		if (is_string($users)) {
			return [$users];
		}

		//TODO handle groups?
		return $users;

		error:
		throw new RuntimeException(
			'No users specified. Please define at least one user using the -u|--user flag'
		);
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
		try {
			$users = $this->tryCompileUserList($input);
		} catch (RuntimeException $exception) {
			$output->writeln('<error>'.$exception->getMessage().'</error>');

			return 1;
		}
		$mountPoint = $input->getArgument('mount_point');

		$debug = null;
		$debug = [
			'',
			'-d',
			'-s',
			'-f',
			$mountPoint,
		];

		$mounter->mount($mountPoint, $this->createFilesystem($users), $debug);

		return 0;
	}

	/**
	 * @param array $users
	 *
	 * @return FilesystemInterface
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function createFilesystem(array $users): FilesystemInterface
	{
		if (count($users) === 1) {
			return new UserFileSystem($users[0], $this->storage->getUserFolder($users[0]));
		}
		$filesystem = new DelegatingUserFilesystem(new View('/'));
		foreach ($users as $user) {
			$filesystem->pushUserFilesystem($user, new UserFileSystem($user, $this->storage->getUserFolder($user)));
		}

		return $filesystem;
	}
}
