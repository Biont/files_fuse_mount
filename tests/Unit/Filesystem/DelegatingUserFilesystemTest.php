<?php

declare(strict_types=1);

namespace OCA\FuseMount\Tests\Unit\Filesystem;

use OCA\FuseMount\Filesystem\DelegatingUserFilesystem;
use OCA\FuseMount\Filesystem\UserFileSystem;
use PHPUnit\Framework\TestCase;
use OC\Files\View;

class DelegatingUserFilesystemTest extends TestCase
{

	public function testDelegation()
	{
		$userFs = \Mockery::mock(UserFileSystem::class);
		$view = \Mockery::mock(View::class);
		$delegatingFs = new DelegatingUserFilesystem($view);
		$delegatingFs->pushUserFilesystem('foo', $userFs);
		$result = 42;
		$userFs->expects('mknod')->once()->andReturn($result);
		$exitCode = $delegatingFs->mknod('/foo/bar', 0, 0);
		$this->assertSame($result, $exitCode);
	}
}
