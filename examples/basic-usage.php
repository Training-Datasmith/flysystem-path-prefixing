<?php

declare(strict_types=1);

/**
 * Example: Partitioning a shared Flysystem adapter using PathPrefixedAdapter.
 *
 * This lets two services share a single S3 bucket (or local disk) while
 * each seeing an isolated virtual root — no cross-contamination of paths.
 */

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;

require_once __DIR__ . '/../vendor/autoload.php';

// A single shared adapter (here in-memory; could be S3, local, etc.)
$shared = new InMemoryFilesystemAdapter();

// Two isolated virtual roots on the same backing store
$uploads = new Filesystem(new PathPrefixedAdapter($shared, 'uploads/'));
$exports = new Filesystem(new PathPrefixedAdapter($shared, 'exports/'));

// Writing through each virtual root — paths never collide
$uploads->write('photo.jpg', 'binary-image-data');
$exports->write('report.csv', 'date,amount\n2026-01-01,100.00');

// Reading back through the correct root
$photo = $uploads->read('photo.jpg');
echo strlen($photo) . ' bytes in photo.jpg' . PHP_EOL;

// Confirm isolation: 'photo.jpg' is not visible through the exports root
echo 'Photo visible via exports? ' . ($exports->fileExists('photo.jpg') ? 'yes' : 'no') . PHP_EOL;
// no

// Listing shows only files under each prefix
foreach ($uploads->listContents('/') as $item) {
    echo 'uploads: ' . $item->path() . PHP_EOL;
}
