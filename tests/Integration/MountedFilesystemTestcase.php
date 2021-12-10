<?php

declare(strict_types=1);

namespace OCA\FuseMount\Tests\Integration;

use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OC\Files\Node\Folder;
use OCP\AppFramework\App;
use OCP\Files\IRootFolder;
use OCP\Files\Node;

/**
 * @group DB
 */
class MountedFilesystemTestcase extends TestCase
{

	use DatabaseTransaction;

	private App $app;

	private string $testSubdir;

	private string $firstUser;

	private Node $firstUserFolder;

	private string $firstUserFilesystemRoot;

	private string $secondUser;

	private Node $secondUserFolder;

	private string $secondUserFilesystemRoot;

	public function setUp(): void
	{
		parent::setUp();
		$this->testSubdir = uniqid('.phpunit_integration_');
		$this->app = new App('files_fuse_mount');
		$container = $this->app->getContainer();
		$rootFolder = $container->get(IRootFolder::class);
		assert($rootFolder instanceof IRootFolder);
		$this->firstUser = getenv('NC_USER_1');
		$this->secondUser = getenv('NC_USER_2');
		$this->firstUserFilesystemRoot = getenv('FUSE_MOUNT_DIR').'/'.$this->firstUser.'/'.$this->testSubdir;
		$this->secondUserFilesystemRoot = getenv('FUSE_MOUNT_DIR').'/'.$this->secondUser.'/'.$this->testSubdir;
		$this->firstUserFolder = $this->setUpTestBedForUser($this->firstUser);
		$this->secondUserFolder = $this->setUpTestBedForUser($this->secondUser);
	}

	public function tearDown(): void
	{
		$this->firstUserFolder->delete();
		$this->secondUserFolder->delete();
	}

	private function setUpTestBedForUser(string $user): Folder
	{
		$container = $this->app->getContainer();
		$rootFolder = $container->get(IRootFolder::class);
		assert($rootFolder instanceof IRootFolder);

		$folder = $rootFolder->getUserFolder($user);

		return $folder->newFolder($this->testSubdir);
	}

	private function userFsRoot(string $user): string
	{
		switch ($user) {
			case $this->firstUser:
				return $this->firstUserFilesystemRoot;
			case $this->secondUser:
				return $this->secondUserFilesystemRoot;
			default:
				throw new \InvalidArgumentException('Unexpected username');
		}
	}

	private function userFsPath(string $user, string $relativePath): string
	{
		return $this->userFsRoot($user).'/'.ltrim($relativePath, '/');
	}

	private function userNextcloudNode(string $user): Node
	{
		switch ($user) {
			case $this->firstUser:
				return $this->firstUserFolder;
			case $this->secondUser:
				return $this->secondUserFolder;
			default:
				throw new \InvalidArgumentException('Unexpected username');
		}
	}

	public function firstUserFsPath(string $relativePath): string
	{
		return $this->userFsPath($this->firstUser, $relativePath);
	}

	public function secondUserFsPath(string $relativePath): string
	{
		return $this->userFsPath($this->secondUser, $relativePath);
	}

	public function firstUserNextcloudNode(string $relativePath = '/'): Node
	{
		return $this->userNextcloudNode($this->firstUser)->get($relativePath);
	}

	public function firstUserNextcloudRoot(): Folder
	{
		$folder = $this->firstUserNextcloudNode();
		assert($folder instanceof Folder);

		return $folder;
	}

	public function secondUserNextcloudNode(string $relativePath = '/'): Node
	{
		return $this->userNextcloudNode($this->secondUser)->get($relativePath);
	}

	public function secondUserNextcloudRoot(): Folder
	{
		$folder = $this->secondUserNextcloudNode();
		assert($folder instanceof Folder);

		return $folder;
	}
}
