<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests;

use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\PathPrefixing\Tests\Support\CapturesThrowable;
use League\Flysystem\PathPrefixing\Tests\Support\RecordingAdapter;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

class PrefixTranslationTest extends TestCase
{
    use CapturesThrowable;

    public function testRejectsEmptyPrefix(): void
    {
        $inner = new RecordingAdapter();

        $exception = $this->captureThrowable(static function () use ($inner): void {
            new PathPrefixedAdapter($inner, '');
        });

        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
        self::assertSame('The prefix must not be empty.', $exception->getMessage());
        self::assertSame([], $inner->records);
    }

    public function testZeroPrefixIsAccepted(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, '0');

        $adapter->write('a.txt', 'x', new Config());

        self::assertTrue($inner->hasFile('0/a.txt'));
        self::assertSame('x', $adapter->read('a.txt'));
    }

    public function testTrailingSlashPrefixMatchesPlainPrefix(): void
    {
        $innerA = new RecordingAdapter();
        $innerB = new RecordingAdapter();
        $adapterA = new PathPrefixedAdapter($innerA, 'uploads');
        $adapterB = new PathPrefixedAdapter($innerB, 'uploads/');

        $adapterA->write('a.txt', 'one', new Config());
        $adapterB->write('a.txt', 'two', new Config());

        self::assertTrue($innerA->hasFile('uploads/a.txt'));
        self::assertTrue($innerB->hasFile('uploads/a.txt'));
        self::assertFalse($innerA->hasFile('uploads//a.txt'));
        self::assertFalse($innerB->hasFile('uploads//a.txt'));
    }

    public function testMultiSegmentPrefix(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'tenant/42');

        $adapter->write('dir/a.txt', 'payload', new Config());

        self::assertTrue($inner->hasFile('tenant/42/dir/a.txt'));
        self::assertSame('payload', $adapter->read('dir/a.txt'));
    }

    public function testLeadingSlashLocation(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $adapter->write('/a.txt', 'body', new Config());

        self::assertTrue($inner->hasFile('pfx/a.txt'));
        self::assertSame('body', $adapter->read('/a.txt'));
        self::assertSame('body', $adapter->read('a.txt'));
    }

    public function testBinaryWriteAndRead(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $body = "a\0b\xff";

        $adapter->write('bin.dat', $body, new Config());

        self::assertTrue($inner->hasFile('pfx/bin.dat'));
        self::assertFalse($inner->hasFile('bin.dat'));
        self::assertSame($body, $adapter->read('bin.dat'));
    }

    public function testWriteForwardsConfig(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config(['visibility' => Visibility::PRIVATE]);

        $adapter->write('a.txt', 'bytes', $config);

        $writeCalls = $inner->callsFor('write');
        self::assertCount(1, $writeCalls);
        self::assertSame('pfx/a.txt', $writeCalls[0]['args'][0]);
        self::assertSame('bytes', $writeCalls[0]['args'][1]);
        self::assertSame($config, $writeCalls[0]['args'][2]);
    }

    public function testWriteStreamForwardsResource(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, 'stream-body');
        rewind($stream);

        $adapter->writeStream('a.txt', $stream, new Config());

        $calls = $inner->callsFor('writeStream');
        self::assertCount(1, $calls);
        self::assertSame('pfx/a.txt', $calls[0]['args'][0]);
        self::assertSame($stream, $calls[0]['args'][1]);
        self::assertSame('stream-body', $inner->fileContents('pfx/a.txt'));
    }

    public function testReadStreamReturnsSameResource(): void
    {
        $inner = new RecordingAdapter();
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, 'stream-body');
        rewind($stream);
        $inner->seedStream('pfx/a.txt', $stream);

        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $returned = $adapter->readStream('a.txt');

        self::assertSame($stream, $returned);
        self::assertSame('stream-body', stream_get_contents($returned));
        rewind($returned);
        $calls = $inner->callsFor('readStream');
        self::assertSame('pfx/a.txt', $calls[0]['args'][0]);
    }

    public function testFileExistsTrueAndFalse(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/yes.txt', 'y');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertTrue($adapter->fileExists('yes.txt'));
        self::assertFalse($adapter->fileExists('no.txt'));

        $calls = $inner->callsFor('fileExists');
        self::assertSame('pfx/yes.txt', $calls[0]['args'][0]);
        self::assertSame('pfx/no.txt', $calls[1]['args'][0]);
    }

    public function testDirectoryExistsTrueAndFalse(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedDirectory('pfx/dir');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertTrue($adapter->directoryExists('dir'));
        self::assertFalse($adapter->directoryExists('missing'));

        $calls = $inner->callsFor('directoryExists');
        self::assertSame('pfx/dir', $calls[0]['args'][0]);
        self::assertSame('pfx/missing', $calls[1]['args'][0]);
    }

    public function testDeleteRemovesOnlyPrefixedKey(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', 'a');
        $inner->seedFile('pfx/b.txt', 'b');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $adapter->delete('a.txt');

        self::assertFalse($inner->hasFile('pfx/a.txt'));
        self::assertTrue($inner->hasFile('pfx/b.txt'));
    }

    public function testDeleteDirectory(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedDirectory('pfx/dir');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $adapter->deleteDirectory('dir');

        self::assertFalse($inner->hasDirectory('pfx/dir'));
        $calls = $inner->callsFor('deleteDirectory');
        self::assertSame('pfx/dir', $calls[0]['args'][0]);
    }

    public function testCreateDirectory(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config(['visibility' => Visibility::PRIVATE]);

        $adapter->createDirectory('dir/sub', $config);

        self::assertTrue($inner->hasDirectory('pfx/dir/sub'));
        $calls = $inner->callsFor('createDirectory');
        self::assertSame('pfx/dir/sub', $calls[0]['args'][0]);
        self::assertSame($config, $calls[0]['args'][1]);
    }

    public function testCopyPrefixesBothEnds(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/src.txt', 'src');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config();

        $adapter->copy('src.txt', 'dst.txt', $config);

        self::assertTrue($inner->hasFile('pfx/src.txt'));
        self::assertTrue($inner->hasFile('pfx/dst.txt'));
        self::assertSame('src', $inner->fileContents('pfx/dst.txt'));
        $calls = $inner->callsFor('copy');
        self::assertSame('pfx/src.txt', $calls[0]['args'][0]);
        self::assertSame('pfx/dst.txt', $calls[0]['args'][1]);
        self::assertSame($config, $calls[0]['args'][2]);
    }

    public function testMovePrefixesBothEnds(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/src.txt', 'src');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config();

        $adapter->move('src.txt', 'dst.txt', $config);

        self::assertFalse($inner->hasFile('pfx/src.txt'));
        self::assertTrue($inner->hasFile('pfx/dst.txt'));
        $calls = $inner->callsFor('move');
        self::assertSame('pfx/src.txt', $calls[0]['args'][0]);
        self::assertSame('pfx/dst.txt', $calls[0]['args'][1]);
        self::assertSame($config, $calls[0]['args'][2]);
    }

    public function testVisibilityRoundTrip(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', 'a');
        $inner->seedMetadata('pfx/a.txt', new FileAttributes('pfx/a.txt', null, Visibility::PRIVATE));
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $adapter->setVisibility('a.txt', Visibility::PRIVATE);

        $setCalls = $inner->callsFor('setVisibility');
        self::assertSame('pfx/a.txt', $setCalls[0]['args'][0]);
        self::assertSame(Visibility::PRIVATE, $setCalls[0]['args'][1]);
        self::assertSame(Visibility::PRIVATE, $adapter->visibility('a.txt')->visibility());
    }

    public function testMetadataScalars(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedMetadata('pfx/a.txt', new FileAttributes(
            'pfx/a.txt',
            17,
            Visibility::PUBLIC,
            1700000000,
            'application/octet-stream'
        ));
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(17, $adapter->fileSize('a.txt')->fileSize());
        self::assertSame('application/octet-stream', $adapter->mimeType('a.txt')->mimeType());
        self::assertSame(1700000000, $adapter->lastModified('a.txt')->lastModified());
        self::assertSame(Visibility::PUBLIC, $adapter->visibility('a.txt')->visibility());

        foreach (['fileSize', 'mimeType', 'lastModified', 'visibility'] as $method) {
            self::assertSame('pfx/a.txt', $inner->callsFor($method)[0]['args'][0]);
        }
    }

    public function testListContentsStripsPrefixOnce(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedListing('pfx/sub', [
            new DirectoryAttributes('pfx/sub', Visibility::PUBLIC, 1700000000),
            new FileAttributes(
                'pfx/foo/pfx/bar.txt',
                4,
                Visibility::PRIVATE,
                1700000001,
                'text/plain',
                ['etag' => 'abc']
            ),
        ]);
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $items = iterator_to_array($adapter->listContents('sub', true));

        $listCalls = $inner->callsFor('listContents');
        self::assertSame('pfx/sub', $listCalls[0]['args'][0]);
        self::assertTrue($listCalls[0]['args'][1]);

        self::assertCount(2, $items);
        self::assertTrue($items[0]->isDir());
        self::assertSame('sub', $items[0]->path());
        self::assertSame(Visibility::PUBLIC, $items[0]->visibility());
        self::assertSame(1700000000, $items[0]->lastModified());

        self::assertTrue($items[1]->isFile());
        self::assertSame('foo/pfx/bar.txt', $items[1]->path());
        self::assertSame(4, $items[1]->fileSize());
        self::assertSame(Visibility::PRIVATE, $items[1]->visibility());
        self::assertSame(1700000001, $items[1]->lastModified());
        self::assertSame('text/plain', $items[1]->mimeType());
        self::assertSame(['etag' => 'abc'], $items[1]->extraMetadata());
    }

    public function testListContentsRootAndShallowFlag(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedListing('pfx/', [
            new FileAttributes('pfx/nested/deep.txt', 1),
        ]);
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $items = iterator_to_array($adapter->listContents('', false));

        $listCalls = $inner->callsFor('listContents');
        self::assertSame('pfx/', $listCalls[0]['args'][0]);
        self::assertFalse($listCalls[0]['args'][1]);
        self::assertCount(1, $items);
        self::assertSame('nested/deep.txt', $items[0]->path());
    }

    public function testImplementsAdapterContracts(): void
    {
        $adapter = new PathPrefixedAdapter(new RecordingAdapter(), 'pfx');

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
        self::assertInstanceOf(ChecksumProvider::class, $adapter);
        self::assertInstanceOf(PublicUrlGenerator::class, $adapter);
        self::assertInstanceOf(TemporaryUrlGenerator::class, $adapter);
    }
}
