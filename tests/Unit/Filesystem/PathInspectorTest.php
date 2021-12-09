<?php

declare(strict_types=1);

namespace OCA\FuseMount\Tests\Unit\Filesystem;

use OCA\FuseMount\Filesystem\PathInspector;
use PHPUnit\Framework\TestCase;

class PathInspectorTest extends TestCase
{

	/**
	 * @dataProvider firstLevelTestData
	 * @return void
	 */
	public function testIsFirstLevel(string $path, bool $expectedResult)
	{
		$pathInspector = new PathInspector();
		$this->assertSame($expectedResult, $pathInspector->isFirstLevel($path));
	}

	public function firstLevelTestData()
	{
		yield '#1' => [
			'/foo/bar/baz.txt',
			false,
		];

		yield '#2' => [
			'/foo/bar.txt',
			false,
		];

		yield '#3' => [
			'/foo',
			true,
		];

		yield '#4' => [
			'/foo/',
			true,
		];

		yield '#5' => [
			'/',
			false,
		];
	}
}
