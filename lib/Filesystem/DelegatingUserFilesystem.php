<?php

declare(strict_types=1);

namespace OCA\FuseMount\Filesystem;

use Fuse\FilesystemInterface;
use Fuse\FuseOperations;
use Fuse\Libc\Fcntl\Flock;
use Fuse\Libc\Fuse\FuseBufVec;
use Fuse\Libc\Fuse\FuseConnInfo;
use Fuse\Libc\Fuse\FuseDirFill;
use Fuse\Libc\Fuse\FuseDirHandle;
use Fuse\Libc\Fuse\FuseFileInfo;
use Fuse\Libc\Fuse\FuseFillDir;
use Fuse\Libc\Fuse\FuseIoctlArgPointer;
use Fuse\Libc\Fuse\FuseIoctlDataPointer;
use Fuse\Libc\Fuse\FusePollHandle;
use Fuse\Libc\Fuse\FusePrivateData;
use Fuse\Libc\Fuse\FuseReadDirBuffer;
use Fuse\Libc\String\CBytesBuffer;
use Fuse\Libc\String\CStringBuffer;
use Fuse\Libc\Sys\Stat\Stat;
use Fuse\Libc\Sys\StatVfs\StatVfs;
use Fuse\Libc\Time\TimeSpec;
use Fuse\Libc\Utime\UtimBuf;
use OC\Files\Filesystem;
use OCA\FuseMount\Filesystem\UserFileSystem;
use OCP\Files\Folder;
use TypedCData\TypedCDataArray;

class DelegatingUserFilesystem implements FilesystemInterface
{

	/**
	 * @var UserFileSystem[]
	 */
	private array $stack = [];

	public function pushUserFilesystem(string $userId, UserFileSystem $fs)
	{
		$this->stack[$userId] = $fs;
	}

	private function delegateCall(string $function, array $args)
	{
		$path = reset($args);
		$fs = $this->getFilesystemForUser($this->getUserFromPath($path));
		$args[0] = $this->getSubPath($path);

		return $fs->$function(...$args);
	}

	private function getSubPath(string $path): string
	{
		$segments = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $path)));
		array_shift($segments);

		return DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
	}

	public function getattr(string $path, Stat $stat): int
	{
		if ($path === '/') {
			$stat->st_mode = Stat::S_IFDIR | 0600;
			$stat->st_nlink = 1;
			$stat->st_size = $this->getCombinedSize();
			$stat->st_uid = getmyuid();
			$stat->st_gid = getmygid();
			$stat->st_atim = new TimeSpec($this->getMostRecentMTime());
			$stat->st_mtim = new TimeSpec($this->getMostRecentMTime());

			return 0;
		}
		if ($this->isFirstLevel($path)) {
			$fs = $this->getFilesystemForUser($this->getUserFromPath($path));
			$stat->st_mode = Stat::S_IFDIR | 0600;
			$stat->st_nlink = 1;
			$stat->st_size = $fs->getNodeSize('/');
			$stat->st_uid = getmyuid();
			$stat->st_gid = getmygid();
			$stat->st_atim = new TimeSpec($fs->getNodeSize('/'));
			$stat->st_mtim = new TimeSpec($fs->getNodeSize('/'));

			return 0;
		}

		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	private function getCombinedSize(): int
	{
		$size = 0;
		Filesystem::clearMounts(); // Mounts/shares might have changed
		foreach ($this->stack as $user => $fs) {
			Filesystem::initMountPoints($user);
			$size += $fs->getNodeSize('/');
		}

		return $size;
	}

	private function getMostRecentMTime(): int
	{
		$smallest = PHP_INT_MAX;
		Filesystem::clearMounts(); // Mounts/shares might have changed
		foreach ($this->stack as $user => $fs) {
			Filesystem::initMountPoints($user);
			$mtime = $fs->getNodeMTime('/');
			if ($mtime < $smallest) {
				$smallest = $mtime;
			}
		}

		return $smallest;
	}

	private function isFirstLevel(string $path): bool
	{
		$segments = array_filter(explode(DIRECTORY_SEPARATOR, $path));

		return count($segments) === 1;
	}

	private function getUserFromPath(string $path): string
	{
		$segments = array_filter(explode(DIRECTORY_SEPARATOR, $path));

		return reset($segments);
	}

	private function getFilesystemForUser(string $user): UserFileSystem
	{
		if (!isset($this->stack[$user])) {
			//TODO throw sth
		}

		return $this->stack[$user];
	}

	public function readlink(string $path, CStringBuffer $buffer, int $size): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function getdir(string $path, FuseDirHandle $dirhandle, FuseDirFill $dirfill): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function mknod(string $path, int $mode, int $dev): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function mkdir(string $path, int $mode): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function unlink(string $path): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function rmdir(string $path): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function symlink(string $path, string $link): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function rename(string $from, string $to): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function link(string $path, string $link): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function chmod(string $path, int $mode): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function chown(string $path, int $uid, int $gid): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function truncate(string $path, int $offset): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function utime(string $path, UtimBuf $utime_buf): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function open(string $path, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function read(string $path, CBytesBuffer $buffer, int $size, int $offset, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function write(string $path, string $buffer, int $size, int $offset, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function statfs(string $path, StatVfs $statvfs): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function flush(string $path, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function release(string $path, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function fsync(string $path, int $flags, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function setxattr(string $path, string $name, string $value, int $size): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function getxattr(string $path, string $name, ?string &$value, int $size): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function listxattr(string $path, ?string &$value, int $size): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function removexattr(string $path, string $name): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function opendir(string $path, FuseFileInfo $fuse_file_info): int
	{
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function readdir(
		string $path,
		FuseReadDirBuffer $buf,
		FuseFillDir $filler,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		if ($path === '/') {
			return $this->readRootDir(...func_get_args());
		}

		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	private function readRootDir(
		string $path,
		FuseReadDirBuffer $buf,
		FuseFillDir $filler,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		$filler($buf, '.', null, 0);
		$filler($buf, '..', null, 0);

		foreach (array_keys($this->stack) as $userId) {
			$filler($buf, $userId, null, 0);
		}

		return 0;
	}

	public function releasedir(
		string $path,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function fsyncdir(
		string $path,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function init(
		FuseConnInfo $conn
	): ?FusePrivateData {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function destroy(
		?FusePrivateData $private_data
	): void {
		//TODO Figure out what this does
	}

	public function access(
		string $path,
		int $mode
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function create(
		string $path,
		int $mode,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function ftruncate(
		string $path,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function fgetattr(
		string $path,
		Stat $stat,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function lock(
		string $path,
		FuseFileInfo $fuse_file_info,
		int $cmd,
		Flock $flock
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function utimens(
		string $path,
		TypedCDataArray $tv
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function bmap(
		string $path,
		int $blocksize,
		int &$idx
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function setFlagNullpathOk(
		bool $flag
	): void {
		//TODO Figure out what this does
	}

	public function getFlagNullpathOk(): bool
	{
		//TODO Figure out what this does
	}

	public
	function setFlagNopath(
		bool $flag
	): void {
		//TODO Figure out what this does
	}

	public function getFlagNopath(): bool
	{
		//TODO Figure out what this does
	}

	public function setFlagUtimeOmitOk(
		bool $flag
	): void {
		//TODO Figure out what this does
	}

	public function getFlagUtimeOmitOk(): bool
	{
		//TODO Figure out what this does
	}

	public function ioctl(
		string $path,
		int $cmd,
		FuseIoctlArgPointer $arg,
		FuseFileInfo $fuse_file_info,
		int $flags,
		FuseIoctlDataPointer $data
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function poll(
		string $path,
		FuseFileInfo $fuse_file_info,
		FusePollHandle $fuse_pollhandle,
		int &$reventsp
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function writeBuf(
		string $path,
		FuseBufVec $buf,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function readBuf(
		string $path,
		TypedCDataArray $bufp,
		int $size,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function flock(
		string $path,
		FuseFileInfo $fuse_file_info,
		int $op
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
	}

	public function fallocate(
		string $path,
		int $mode,
		int $offset,
		FuseFileInfo $fuse_file_info
	): int {
		return $this->delegateCall(__FUNCTION__, func_get_args());
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
}
