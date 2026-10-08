<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests;

use DateTimeImmutable;
use League\Flysystem\ChecksumAlgoIsNotSupported;
use League\Flysystem\Config;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\PathPrefixing\Tests\Support\CapturesThrowable;
use League\Flysystem\PathPrefixing\Tests\Support\ChecksumRecordingAdapter;
use League\Flysystem\PathPrefixing\Tests\Support\RecordingAdapter;
use League\Flysystem\PathPrefixing\Tests\Support\UrlRecordingAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\TestCase;

class ChecksumAndUrlTest extends TestCase
{
    use CapturesThrowable;

    private const MD5_BLA = '128ecf542a35ac5270a87dc740918404';

    private const SHA256_BLA = '4df3c3f68fcc83b27e9d42c90431a72499f17875c81a599b566c9889b9696703';

    private const MD5_EMPTY = 'd41d8cd98f00b204e9800998ecf8427e';

    private const SHA256_BINARY = 'a37cc3026aae4d519e0b19c298fa913b4dccfdf0658cbccbb7deaa0226d5acdb';

    public function testChecksumDelegatesToProvider(): void
    {
        $inner = new ChecksumRecordingAdapter();
        $inner->setChecksumReturn('delegated:pfx/a.txt');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config();

        $result = $adapter->checksum('a.txt', $config);

        self::assertSame('delegated:pfx/a.txt', $result);
        $calls = $inner->callsFor('checksum');
        self::assertSame('pfx/a.txt', $calls[0]['args'][0]);
        self::assertSame($config, $calls[0]['args'][1]);
        self::assertSame([], $inner->callsFor('readStream'));
    }

    public function testChecksumAlgoIsNotSupportedPropagates(): void
    {
        $inner = new ChecksumRecordingAdapter();
        $exception = new ChecksumAlgoIsNotSupported('unsupported');
        $inner->setChecksumAlgoNotSupported($exception);
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $thrown = $this->captureThrowable(static function () use ($adapter): void {
            $adapter->checksum('a.txt', new Config());
        });

        self::assertSame($exception, $thrown);
    }

    public function testChecksumStreamFallbackMd5(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', 'bla');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(self::MD5_BLA, $adapter->checksum('a.txt', new Config()));
        self::assertCount(1, $inner->callsFor('readStream'));
        self::assertSame('pfx/a.txt', $inner->callsFor('readStream')[0]['args'][0]);
    }

    public function testChecksumStreamFallbackSha256(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', 'bla');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(
            self::SHA256_BLA,
            $adapter->checksum('a.txt', new Config(['checksum_algo' => 'sha256']))
        );
    }

    public function testChecksumStreamFallbackEmptyBody(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', '');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(self::MD5_EMPTY, $adapter->checksum('a.txt', new Config()));
    }

    public function testChecksumStreamFallbackBinary(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', "a\0b\xff");
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(
            self::SHA256_BINARY,
            $adapter->checksum('a.txt', new Config(['checksum_algo' => 'sha256']))
        );
    }

    public function testChecksumStreamFallbackLeadingSlash(): void
    {
        $inner = new RecordingAdapter();
        $inner->seedFile('pfx/a.txt', 'bla');
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        self::assertSame(self::MD5_BLA, $adapter->checksum('/a.txt', new Config()));
        self::assertSame('pfx/a.txt', $inner->callsFor('readStream')[0]['args'][0]);
    }

    public function testChecksumMissingFileUsesCallerPath(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $outer = $this->captureThrowable(static function () use ($adapter): void {
            $adapter->checksum('a.txt', new Config());
        });

        self::assertInstanceOf(UnableToProvideChecksum::class, $outer);
        $previous = $outer->getPrevious();
        self::assertInstanceOf(UnableToReadFile::class, $previous);
        self::assertSame('a.txt', $previous->location());
    }

    public function testPublicUrl(): void
    {
        $inner = new UrlRecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $config = new Config();

        $url = $adapter->publicUrl('/a.txt', $config);

        self::assertSame('https://cdn.test/pfx/a.txt', $url);
        $calls = $inner->callsFor('publicUrl');
        self::assertSame('pfx/a.txt', $calls[0]['args'][0]);
        self::assertSame($config, $calls[0]['args'][1]);
    }

    public function testPublicUrlMissingGenerator(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');

        $exception = $this->captureThrowable(static function () use ($adapter): void {
            $adapter->publicUrl('a.txt', new Config());
        });

        self::assertInstanceOf(UnableToGeneratePublicUrl::class, $exception);
        self::assertMatchesRegularExpression('/(?<![\\/])a\\.txt/', $exception->getMessage());
        self::assertSame([], $inner->records);
    }

    public function testTemporaryUrl(): void
    {
        $inner = new UrlRecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $expiresAt = new DateTimeImmutable('2030-06-15T12:30:00+00:00');
        $config = new Config();

        $url = $adapter->temporaryUrl('a.txt', $expiresAt, $config);

        self::assertSame('https://cdn.test/signed', $url);
        $calls = $inner->callsFor('temporaryUrl');
        self::assertSame('pfx/a.txt', $calls[0]['args'][0]);
        self::assertSame($expiresAt, $calls[0]['args'][1]);
        self::assertSame($config, $calls[0]['args'][2]);
    }

    public function testTemporaryUrlMissingGenerator(): void
    {
        $inner = new RecordingAdapter();
        $adapter = new PathPrefixedAdapter($inner, 'pfx');
        $expiresAt = new DateTimeImmutable('2030-06-15T12:30:00+00:00');

        $exception = $this->captureThrowable(static function () use ($adapter, $expiresAt): void {
            $adapter->temporaryUrl('a.txt', $expiresAt, new Config());
        });

        self::assertInstanceOf(UnableToGenerateTemporaryUrl::class, $exception);
        self::assertMatchesRegularExpression('/(?<![\\/])a\\.txt/', $exception->getMessage());
        self::assertSame([], $inner->records);
    }
}
