<?php

declare(strict_types=1);

namespace OCA\FuseMount\Tests\Unit\Command;

use Fuse\Mounter;
use OCA\FuseMount\Command\Mount;
use OCA\FuseMount\Filesystem\UserFileSystem;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class MountCommandTest extends TestCase
{

	public function testConfigureRejectsMissingUserConfig()
	{
		$mounter = \Mockery::mock('overload:'.Mounter::class);
		$mounter->expects('mount');
		$iConfig = \Mockery::mock(IConfig::class);
		$iRootFolder = \Mockery::mock(IRootFolder::class);
		$command = new Mount($iConfig, $iRootFolder);
		//$this->expectException(RuntimeException::class);
		$commandTester = new CommandTester($command);
		$commandTester->execute([
			'mount_point' => '/tmp',
		]);
		$this->assertSame(1, $commandTester->getStatusCode());

	}

	public function testConfigureRejectsMissingMountPoint()
	{
		$users = ['admin', 'heinz'];
		$mounter = \Mockery::mock('overload:'.Mounter::class);
		$mounter->expects('mount');
		$iConfig = \Mockery::mock(IConfig::class);
		$iRootFolder = \Mockery::mock(IRootFolder::class);
		foreach ($users as $user) {
			$userRootFolder = \Mockery::mock(Folder::class);
			$iRootFolder->expects('getUserFolder')->once()->with($user)->andReturn($userRootFolder);
		}
		$command = new Mount($iConfig, $iRootFolder);
		$this->expectException(RuntimeException::class);
		$commandTester = new CommandTester($command);
		$commandTester->execute([
			'-u' => $users,
		]);
	}

	public function testConfigureAcceptsMultipleUsers()
	{
		$users = ['admin', 'heinz'];
		$mounter = \Mockery::mock('overload:'.Mounter::class);
		$mounter->expects('mount');
		$iConfig = \Mockery::mock(IConfig::class);
		$iRootFolder = \Mockery::mock(IRootFolder::class);
		foreach ($users as $user) {
			$userRootFolder = \Mockery::mock(Folder::class);
			$iRootFolder->expects('getUserFolder')->once()->with($user)->andReturn($userRootFolder);
		}
		$command = new Mount($iConfig, $iRootFolder);
		$commandTester = new CommandTester($command);
		$commandTester->execute([
			'-u' => $users,
			'mount_point' => '/tmp',
		]);
		$this->assertSame(0, $commandTester->getStatusCode());
	}
}
