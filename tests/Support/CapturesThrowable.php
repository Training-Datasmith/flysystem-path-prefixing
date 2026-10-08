<?php

declare(strict_types=1);

namespace League\Flysystem\PathPrefixing\Tests\Support;

use PHPUnit\Framework\Assert;
use Throwable;

trait CapturesThrowable
{
    private function captureThrowable(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            return $exception;
        }

        Assert::fail('Expected an exception.');
    }
}
