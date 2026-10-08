<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests\Support;

use DateTimeInterface;
use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

class UrlRecordingAdapter extends RecordingAdapter implements PublicUrlGenerator, TemporaryUrlGenerator
{
    private string $publicUrlPrefix = 'https://cdn.test/';

    private string $temporaryUrlReturn = 'https://cdn.test/signed';

    public function setPublicUrlPrefix(string $prefix): void
    {
        $this->publicUrlPrefix = $prefix;
    }

    public function setTemporaryUrlReturn(string $url): void
    {
        $this->temporaryUrlReturn = $url;
    }

    public function publicUrl(string $path, Config $config): string
    {
        $this->record('publicUrl', [$path, $config]);

        return $this->publicUrlPrefix . ltrim($path, '/');
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        $this->record('temporaryUrl', [$path, $expiresAt, $config]);

        return $this->temporaryUrlReturn;
    }
}
