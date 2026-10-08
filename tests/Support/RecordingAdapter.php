<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests\Support;

use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use Throwable;

class RecordingAdapter implements FilesystemAdapter
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var array<string, true> */
    private array $directories = [];

    /** @var array<string, resource> */
    private array $streams = [];

    /** @var array<string, FileAttributes> */
    private array $metadataByPath = [];

    /** @var array<string, list<StorageAttributes>> */
    private array $listingsByPath = [];

    /** @var list<array{method: string, args: array<int, mixed>}> */
    public array $records = [];

    private ?Throwable $throwAfterRecord = null;

    public function injectThrowableAfterNextCall(Throwable $throwable): void
    {
        $this->throwAfterRecord = $throwable;
    }

    public function clearInjectedThrowable(): void
    {
        $this->throwAfterRecord = null;
    }

    public function seedFile(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }

    public function seedDirectory(string $path): void
    {
        $this->directories[$path] = true;
    }

    /**
     * @param resource $stream
     */
    public function seedStream(string $path, $stream): void
    {
        $this->streams[$path] = $stream;
    }

    public function seedMetadata(string $path, FileAttributes $attributes): void
    {
        $this->metadataByPath[$path] = $attributes;
    }

    /**
     * @param list<StorageAttributes> $items
     */
    public function seedListing(string $listPath, array $items): void
    {
        $this->listingsByPath[$listPath] = $items;
    }

    public function hasFile(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function fileContents(string $path): string
    {
        return $this->files[$path];
    }

    public function hasDirectory(string $path): bool
    {
        return array_key_exists($path, $this->directories);
    }

    public function wasCalled(string $method): bool
    {
        foreach ($this->records as $record) {
            if ($record['method'] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{method: string, args: array<int, mixed>}>
     */
    public function callsFor(string $method): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['method'] === $method
        ));
    }

    public function fileExists(string $path): bool
    {
        $this->record('fileExists', [$path]);

        return array_key_exists($path, $this->files);
    }

    public function directoryExists(string $path): bool
    {
        $this->record('directoryExists', [$path]);

        if (array_key_exists($path, $this->directories)) {
            return true;
        }

        $prefix = rtrim($path, '/') . '/';
        foreach (array_keys($this->directories) as $directory) {
            if (str_starts_with($directory, $prefix) || $directory === rtrim($path, '/')) {
                return true;
            }
        }

        foreach (array_keys($this->files) as $filePath) {
            if (str_starts_with($filePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->record('write', [$path, $contents, $config]);
        $this->files[$path] = $contents;
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->record('writeStream', [$path, $contents, $config]);
        $this->files[$path] = (string) stream_get_contents($contents);
    }

    public function read(string $path): string
    {
        $this->record('read', [$path]);

        if (! array_key_exists($path, $this->files)) {
            throw UnableToReadFile::fromLocation($path, 'file does not exist');
        }

        return $this->files[$path];
    }

    public function readStream(string $path)
    {
        $this->record('readStream', [$path]);

        if (array_key_exists($path, $this->streams)) {
            return $this->streams[$path];
        }

        if (! array_key_exists($path, $this->files)) {
            throw UnableToReadFile::fromLocation($path, 'file does not exist');
        }

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $this->files[$path]);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->record('delete', [$path]);
        unset($this->files[$path]);
    }

    public function deleteDirectory(string $path): void
    {
        $this->record('deleteDirectory', [$path]);
        unset($this->directories[$path]);
        $prefix = rtrim($path, '/') . '/';
        foreach (array_keys($this->files) as $filePath) {
            if (str_starts_with($filePath, $prefix)) {
                unset($this->files[$filePath]);
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->record('createDirectory', [$path, $config]);
        $this->directories[$path] = true;
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->record('setVisibility', [$path, $visibility]);

        if (! array_key_exists($path, $this->files)) {
            throw UnableToSetVisibility::atLocation($path, 'file does not exist');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        $this->record('visibility', [$path]);

        return $this->metadataFor($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $this->record('mimeType', [$path]);

        return $this->metadataFor($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        $this->record('lastModified', [$path]);

        return $this->metadataFor($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        $this->record('fileSize', [$path]);

        return $this->metadataFor($path);
    }

    public function listContents(string $path, bool $deep): Generator
    {
        $this->record('listContents', [$path, $deep]);

        foreach ($this->listingsByPath[$path] ?? [] as $item) {
            yield $item;
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->record('move', [$source, $destination, $config]);

        if (! array_key_exists($source, $this->files)) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        if ($source !== $destination) {
            $this->files[$destination] = $this->files[$source];
            unset($this->files[$source]);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->record('copy', [$source, $destination, $config]);

        if (! array_key_exists($source, $this->files)) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        $this->files[$destination] = $this->files[$source];
    }

    private function metadataFor(string $path): FileAttributes
    {
        if (! array_key_exists($path, $this->metadataByPath)) {
            throw UnableToRetrieveMetadata::mimeType($path, 'no metadata configured');
        }

        return $this->metadataByPath[$path];
    }

    /**
     * @param array<int, mixed> $args
     */
    protected function record(string $method, array $args): void
    {
        $this->records[] = ['method' => $method, 'args' => $args];
        $this->maybeThrowAfterRecord();
    }

    protected function maybeThrowAfterRecord(): void
    {
        if ($this->throwAfterRecord !== null) {
            $throwable = $this->throwAfterRecord;
            $this->throwAfterRecord = null;
            throw $throwable;
        }
    }
}
