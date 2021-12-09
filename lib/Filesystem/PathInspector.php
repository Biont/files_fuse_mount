<?php

declare(strict_types=1);

namespace OCA\FuseMount\Filesystem;

class PathInspector
{
	public function isFirstLevel(string $path): bool
	{
		$segments = array_filter(explode(DIRECTORY_SEPARATOR, $path));

		return count($segments) === 1;
	}

	public function getUserFromPath(string $path): string
	{
		$segments = array_filter(explode(DIRECTORY_SEPARATOR, $path));

		return reset($segments);
	}
}
