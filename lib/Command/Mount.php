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
use OCA\FuseMount\Filesystem\FilesystemFactory;
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

	private FilesystemFactory $filesystemFactory;

	public function __construct(
		FilesystemFactory $filesystemFactory
	) {
		parent::__construct();
		$this->filesystemFactory = $filesystemFactory;
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

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
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

		$mounter->mount($mountPoint, $this->filesystemFactory->createForUsers(...$users), $debug);

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

	}
}
