<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperationFailed;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\PathPrefixing\Tests\Support\CapturesThrowable;
use League\Flysystem\PathPrefixing\Tests\Support\RecordingAdapter;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FailureTranslationTest extends TestCase
{
    use CapturesThrowable;

    /**
     * @return iterable<string, array{callable(PathPrefixedAdapter): void, class-string, callable(\Throwable, RuntimeException): void}>
     */
    public function failureCases(): iterable
    {
        $caller = 'dir/a.txt';
        $previous = new RuntimeException('disk exploded');

        yield 'read' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->read($caller),
            UnableToReadFile::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToReadFile::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_READ, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'readStream' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->readStream($caller),
            UnableToReadFile::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToReadFile::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_READ, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'write' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->write($caller, 'x', new Config()),
            UnableToWriteFile::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToWriteFile::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_WRITE, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'writeStream' => [
            static function (PathPrefixedAdapter $adapter) use ($caller): void {
                $stream = fopen('php://memory', 'r+b');
                $adapter->writeStream($caller, $stream, new Config());
            },
            UnableToWriteFile::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToWriteFile::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_WRITE, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'delete' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->delete($caller),
            UnableToDeleteFile::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToDeleteFile::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_DELETE, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'deleteDirectory' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->deleteDirectory($caller),
            UnableToDeleteDirectory::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToDeleteDirectory::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_DELETE_DIRECTORY, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'createDirectory' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->createDirectory($caller, new Config()),
            UnableToCreateDirectory::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToCreateDirectory::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_CREATE_DIRECTORY, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'setVisibility' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->setVisibility($caller, 'private'),
            UnableToSetVisibility::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToSetVisibility::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FilesystemOperationFailed::OPERATION_SET_VISIBILITY, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'fileExists' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->fileExists($caller),
            UnableToCheckFileExistence::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToCheckFileExistence::class, $outer);
                self::assertSame(FilesystemOperationFailed::OPERATION_FILE_EXISTS, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'directoryExists' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->directoryExists($caller),
            UnableToCheckDirectoryExistence::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToCheckDirectoryExistence::class, $outer);
                self::assertSame(FilesystemOperationFailed::OPERATION_DIRECTORY_EXISTS, $outer->operation());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'lastModified' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->lastModified($caller),
            UnableToRetrieveMetadata::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToRetrieveMetadata::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FileAttributes::ATTRIBUTE_LAST_MODIFIED, $outer->metadataType());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'fileSize' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->fileSize($caller),
            UnableToRetrieveMetadata::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToRetrieveMetadata::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FileAttributes::ATTRIBUTE_FILE_SIZE, $outer->metadataType());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'mimeType' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->mimeType($caller),
            UnableToRetrieveMetadata::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToRetrieveMetadata::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FileAttributes::ATTRIBUTE_MIME_TYPE, $outer->metadataType());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'visibility' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->visibility($caller),
            UnableToRetrieveMetadata::class,
            static function (\Throwable $outer, RuntimeException $previous) use ($caller): void {
                self::assertInstanceOf(UnableToRetrieveMetadata::class, $outer);
                self::assertSame($caller, $outer->location());
                self::assertSame('disk exploded', $outer->reason());
                self::assertSame(FileAttributes::ATTRIBUTE_VISIBILITY, $outer->metadataType());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'move' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->move('dir/a.txt', 'dir/b.txt', new Config()),
            UnableToMoveFile::class,
            static function (\Throwable $outer, RuntimeException $previous): void {
                self::assertInstanceOf(UnableToMoveFile::class, $outer);
                self::assertSame('dir/a.txt', $outer->source());
                self::assertSame('dir/b.txt', $outer->destination());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];

        yield 'copy' => [
            static fn (PathPrefixedAdapter $adapter) => $adapter->copy('dir/a.txt', 'dir/b.txt', new Config()),
            UnableToCopyFile::class,
            static function (\Throwable $outer, RuntimeException $previous): void {
                self::assertInstanceOf(UnableToCopyFile::class, $outer);
                self::assertSame('dir/a.txt', $outer->source());
                self::assertSame('dir/b.txt', $outer->destination());
                self::assertSame($previous, $outer->getPrevious());
            },
        ];
    }

    /**
     * @dataProvider failureCases
     *
     * @param callable(PathPrefixedAdapter): void $operation
     * @param class-string $expectedClass
     * @param callable(\Throwable, RuntimeException): void $assertions
     */
    public function testFailureIsTranslated(callable $operation, string $expectedClass, callable $assertions): void
    {
        $inner = new RecordingAdapter();
        $previous = new RuntimeException('disk exploded');
        $inner->injectThrowableAfterNextCall($previous);
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $outer = $this->captureThrowable(static function () use ($operation, $adapter): void {
            $operation($adapter);
        });

        self::assertInstanceOf($expectedClass, $outer);
        self::assertNotEmpty($inner->records);
        $recordedPath = $inner->records[0]['args'][0];
        self::assertIsString($recordedPath);
        self::assertStringStartsWith('pfx/', $recordedPath);
        $assertions($outer, $previous);
    }
}
