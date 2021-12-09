<?php

declare(strict_types=1);

namespace OCA\FuseMount\Filesystem;

use Fuse\FilesystemDefaultImplementationTrait;
use Fuse\FilesystemInterface;
use Fuse\FuseOperations;
use Fuse\Libc\Fuse\FuseFileInfo;
use Fuse\Libc\Fuse\FuseFillDir;
use Fuse\Libc\Fuse\FuseReadDirBuffer;
use Fuse\Libc\String\CBytesBuffer;
use Fuse\Libc\Sys\Stat\Stat;
use Fuse\Libc\Time\TimeSpec;
use Fuse\Libc\Utime\UtimBuf;
use OC\Files\Cache\HomeCache;
use OC\Files\Filesystem;
use OC\Files\Node\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;

class UserFileSystem implements FilesystemInterface
{

	use FilesystemDefaultImplementationTrait;

	private Folder $rootNode;

	private HomeCache $homeCache;

	private string $userId;

	public function __construct(string $userId, Folder $rootNode)
	{
		$this->rootNode = $rootNode;
		$this->userId = $userId;
	}

	public function getOperations(): FuseOperations
	{
		$fuseOperations = new FuseOperations();
		$fuseOperations->getattr = [$this, 'getattr'];
		$fuseOperations->open = [$this, 'open'];
		$fuseOperations->read = [$this, 'read'];
		$fuseOperations->readdir = [$this, 'readdir'];
		$fuseOperations->write = [$this, 'write'];
		$fuseOperations->truncate = [$this, 'truncate'];
		$fuseOperations->ftruncate = [$this, 'ftruncate'];
		$fuseOperations->flush = [$this, 'flush'];
		$fuseOperations->getxattr = [$this, 'getxattr'];
		$fuseOperations->removexattr = [$this, 'removexattr'];
		$fuseOperations->mknod = [$this, 'mknod'];
		$fuseOperations->mkdir = [$this, 'mkdir'];
		$fuseOperations->unlink = [$this, 'unlink'];
		$fuseOperations->utime = [$this, 'utime'];
		//$fuseOperations->utimens = [$this, 'utimens'];
		$fuseOperations->chown = [$this, 'chown'];
		$fuseOperations->chmod = [$this, 'chmod'];

		return $fuseOperations;
	}

	public function getattr(string $path, Stat $stat): int
	{
		try {
			$node = $this->rootNode->get($path);
		} catch (NotFoundException $e) {
			return -2;
		}
		$stat->st_mode = $this->getMode($node);
		$stat->st_nlink = 1;
		$stat->st_size = $node->getSize();
		$stat->st_uid = getmyuid();
		$stat->st_gid = getmygid();
		$stat->st_atim = new TimeSpec($node->getMTime());
		$stat->st_mtim = new TimeSpec($node->getMTime());

		return 0;
	}

	private function getMode(Node $node)
	{
		$mode = $node instanceof Folder
			? Stat::S_IFDIR
			: Stat::S_IFREG;
		try {
			switch (true) {
				case $node->isUpdateable():
					return $mode | 0600;
				case  $node->isReadable():
					return $mode | 0400;
				default:
					return $mode | 0000;
			}
		} catch (InvalidPathException|NotFoundException $e) {
		} finally {
			return $mode | 0000;
		}
	}

	public function open(string $path, FuseFileInfo $fuse_file_info): int
	{
		//TODO what's a good use for this function?
		return 0;
	}

	public function read(string $path, CBytesBuffer $buffer, int $size, int $offset, FuseFileInfo $fuse_file_info): int
	{
		$node = $this->rootNode->get($path);
		assert($node instanceof File);

		$len = $node->getSize();

		if ($offset + $size > $len) {
			$size = ($len - $offset);
		}

		$content = substr($node->getContent(), $offset, $size);
		$buffer->write($content, $size);

		return $size;
	}

	public function readdir(
		string $path,
		FuseReadDirBuffer $buf,
		FuseFillDir $filler,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		try {
			$subDir = $this->rootNode->get($path);
		} catch (NotFoundException $e) {
			return -2;
		}

		/**
		 * Ensure we get up-to-date results for shared files & folders.
		 * -> Clean and reload mount points
		 */
		Filesystem::clearMounts();
		Filesystem::initMountPoints($this->userId);

		try {
			$filler($buf, '.', null, 0);
			$filler($buf, '..', null, 0);

			assert($subDir instanceof Folder);
			foreach ($subDir->getDirectoryListing() as $item) {
				$filler($buf, $item->getName(), null, 0);
			}
		} catch (NotFoundException $e) {
			return -2;
		}

		return 0;
	}

	public function truncate(string $path, int $offset): int
	{
		$node = $this->rootNode->get($path);
		assert($node instanceof File);
		try {
			$node->putContent('');
		} catch (GenericFileException|NotPermittedException|LockedException $e) {
			return -1;
		}

		return 0;
	}

	public function ftruncate(string $path, int $offset, FuseFileInfo $fuse_file_info): int{
		try {
			$node = $this->rootNode->get($path);
			assert($node instanceof File);
			$res = $node->fopen('rw+');
			ftruncate($res, $offset);
			fclose($res);
			$node->touch();
		} catch (LockedException|NotPermittedException $e) {
			return -1;
		}

		return 0;
	}

	public function write(string $path, string $buffer, int $size, int $offset, FuseFileInfo $fuse_file_info): int
	{
		try {
			$node = $this->rootNode->get($path);
			assert($node instanceof File);
			$res = $node->fopen('rw+');
			fseek(
				$res,
				$offset,
				$offset
					? SEEK_SET
					: SEEK_END
			);
			fwrite($res, $buffer, $size);
			fclose($res);
			$node->touch();
		} catch (LockedException|NotPermittedException $e) {
			return -1;
		}

		return $size;
	}

	//public function getxattr(string $path, string $name, ?string &$value, int $size): int
	//{
	//	return 0;
	//}
	//
	//public function removexattr(string $path, string $name): int
	//{
	//	return 0;
	//}

	public function flush(string $path, FuseFileInfo $fuse_file_info): int
	{
		return 0;
	}

	public function mknod(string $path, int $mode, int $dev): int
	{
		try {
			$this->rootNode->newFile($path);
		} catch (NotPermittedException $e) {
			return -1;
		}

		return 0;
	}

	public function mkdir(string $path, int $mode): int
	{
		try {
			$this->rootNode->newFolder($path);
		} catch (NotPermittedException $e) {
			return -1;
		}

		return 0;
	}

	public function unlink(string $path): int
	{
		try {
			$this->rootNode->get($path)->delete();
		} catch (InvalidPathException|NotPermittedException $e) {
			return -1;
		} catch (NotFoundException $e) {
			return -2;
		}

		return 0;
	}

	public function utime(string $path, UtimBuf $utime_buf): int{
		try {
			$node=$this->rootNode->get($path);
			$utime_buf->actime=$node->getMTime();
			$utime_buf->modtime=$node->getMTime();
			return 0;
		} catch (InvalidPathException|NotPermittedException $e) {
			return -1;
		} catch (NotFoundException $e) {
			return -2;
		}

	}
	public function chmod(string $path, int $mode): int{
		echo __FUNCTION__.': '.$path.PHP_EOL;
		return 0;
	}
	public function chown(string $path, int $uid, int $gid): int{
		echo __FUNCTION__.': '.$path.PHP_EOL;
		return 0;
	}

	public function getNodeSize(string $path)
	{
		return $this->rootNode->get($path)->getSize();
	}

	public function getNodeMTime(string $path)
	{
		return $this->rootNode->get($path)->getMTime();
	}
}
