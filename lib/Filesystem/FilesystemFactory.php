<?php

namespace OCA\FuseMount\Filesystem;

use Fuse\FilesystemInterface;
use OC\Files\View;
use OCP\Files\IRootFolder;

class FilesystemFactory
{
	private IRootFolder $storage;

	public function __construct(IRootFolder $storage)
	{

		$this->storage = $storage;
	}

	/**
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function createForUsers(string ...$users): FilesystemInterface
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
