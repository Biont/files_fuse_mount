<?php

declare(strict_types=1);

namespace OCA\FuseMount\Tests\Integration\FileOperation;

use OC\Files\Node\Folder;
use OCA\FuseMount\Tests\Integration\MountedFilesystemTestcase;
use OCP\Files\NotFoundException;

class FileOperationsTest extends MountedFilesystemTestcase
{

	public function testCreateNewFileInNextcloud()
	{
		/**
		 * Create file via NC API
		 */
		$fileName = '/'.uniqid('testFile_').'.txt';
		$fileContents = 'Lorem ipsum';
		$fsPath = $this->firstUserFsPath($fileName);
		$file = $this->firstUserNextcloudNode()->newFile($fileName, $fileContents);

		/**
		 * The file should now be visible on the FUSE fs. Match the contents of both files to be sure
		 */
		$this->assertFileExists($fsPath);
		$fsContents = file_get_contents($fsPath);
		$this->assertSame(
			$file->getContent(),
			$fsContents,
			'File contents should obviously be identical'
		);

		/**
		 * Delete the file via NC API again
		 */
		$file->delete();
		sleep(1); // Seems like this is not a synchronous task

		$this->assertFileDoesNotExist(
			$fsPath,
			'Testfile should no longer exist after deleting it via NC'
		);
	}

	public function testCreateNewFileInFilesystem()
	{
		/**
		 * Create file on FUSE fs
		 */
		$fileName = '/'.uniqid('testFile_').'.txt';
		$fileContents = 'Lorem ipsum';
		$fsPath = $this->firstUserFsPath($fileName);
		file_put_contents($fsPath, $fileContents);

		/**
		 * Check if we can find that very file via NC API
		 * This throws an exception if it cannot be found, so our tests only move on if this works ;)
		 */
		$file = $this->firstUserNextcloudNode($fileName);
		$this->assertSame(
			$file->getContent(),
			$fileContents,
			'File contents should obviously be identical'
		);

		/**
		 * Delete file on FUSE fs again.
		 */
		unlink($fsPath);
		sleep(1); // Seems like this is not a synchronous task

		/**
		 * Check NC API again. We want the file to be gone now
		 */
		$this->assertFalse($this->firstUserNextcloudRoot()->nodeExists($fileName));
	}

	public function testRenamingFilesIsReflectedInNextcloud()
	{
		/**
		 * Create file on FUSE fs
		 */
		$fileName = '/'.uniqid('testFile_').'.txt';
		$newFileName = '/'.uniqid('testFile_').'.txt';
		$fileContents = 'Lorem ipsum';
		$fsPath = $this->firstUserFsPath($fileName);
		$newFsPath = $this->firstUserFsPath($newFileName);
		file_put_contents($fsPath, $fileContents);
		sleep(1); // Seems like this is not a synchronous task

		$this->assertTrue($this->firstUserNextcloudRoot()->nodeExists($fileName));

		$renameResult = rename($fsPath, $newFsPath);
		$this->assertTrue($renameResult);
		sleep(1); // Seems like this is not a synchronous task

		$this->assertFalse($this->firstUserNextcloudRoot()->nodeExists($fileName));
		$this->assertTrue($this->firstUserNextcloudRoot()->nodeExists($newFileName));
	}

	public function testFilesCanBeCopiedFromOneUserToAnother()
	{
		/**
		 * Create file on FUSE fs
		 */
		$fileName = '/'.uniqid('testFile_').'.txt';
		$fsPath = $this->firstUserFsPath($fileName);
		$fileContents = 'Lorem ipsum';
		file_put_contents($fsPath, $fileContents);
		sleep(1); // Seems like this is not a synchronous task

		$this->assertTrue($this->firstUserNextcloudRoot()->nodeExists($fileName));

		$newFsPath = $this->secondUserFsPath($fileName);
		$renameResult = rename($fsPath, $newFsPath);
		$this->assertTrue($renameResult);
		sleep(1); // Seems like this is not a synchronous task

		$this->assertFalse($this->firstUserNextcloudRoot()->nodeExists($fileName));
		$this->assertTrue($this->secondUserNextcloudRoot()->nodeExists($fileName));
	}

	public function testFileStatReturnsAsExpected()
	{
		/**
		 * Create file on FUSE fs
		 */
		$fileName = '/'.uniqid('testFile_').'.txt';
		$fsPath = $this->firstUserFsPath($fileName);
		$fileContents = 'Lorem ipsum';
		file_put_contents($fsPath, $fileContents);
		sleep(1); // Seems like this is not a synchronous task

		$stat = stat($fsPath);
		$size = $stat['size'];
		$this->assertSame(
			strlen($fileContents),
			$size,
			'File should report the expected size'
		);
	}
}
