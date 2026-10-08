<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests\Support;

use League\Flysystem\ChecksumAlgoIsNotSupported;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;

class ChecksumRecordingAdapter extends RecordingAdapter implements ChecksumProvider
{
    private ?string $checksumReturn = null;

    private ?ChecksumAlgoIsNotSupported $checksumAlgoException = null;

    public function setChecksumReturn(?string $checksumReturn): void
    {
        $this->checksumReturn = $checksumReturn;
    }

    public function setChecksumAlgoNotSupported(ChecksumAlgoIsNotSupported $exception): void
    {
        $this->checksumAlgoException = $exception;
    }

    public function checksum(string $path, Config $config): string
    {
        $this->record('checksum', [$path, $config]);

        if ($this->checksumAlgoException !== null) {
            throw $this->checksumAlgoException;
        }

        if ($this->checksumReturn !== null) {
            return $this->checksumReturn;
        }

        return 'delegated:' . $path;
    }
}
