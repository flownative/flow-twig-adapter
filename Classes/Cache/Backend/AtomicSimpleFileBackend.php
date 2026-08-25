<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Cache\Backend;

/*
 * This file is part of the Flownative.TwigAdapter package.
 *
 * (c) Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\Backend\SimpleFileBackend;

/**
 * A file backend which publishes cache entries atomically.
 *
 * Entries are written to a temporary file in the cache directory and then moved
 * into place with rename(), which is atomic on POSIX filesystems as long as both
 * paths live on the same filesystem. A concurrent reader therefore always sees
 * either the previous or the complete new entry, never a partially written one.
 *
 * This matters for caches which are read through PhpFrontend::requireOnce(),
 * because include() cannot participate in the advisory locking the base class
 * uses: a template compiled lazily while other requests are already rendering
 * would otherwise be included halfway through being written, which surfaces as
 * a PHP ParseError or as a silently missing class.
 *
 * On Windows, rename() over an existing open file can fail. If publishing keeps
 * failing, writing falls back to the base class, which trades atomicity for a
 * working cache.
 */
class AtomicSimpleFileBackend extends SimpleFileBackend
{
    /**
     * Temporary files older than this many seconds are considered abandoned and
     * are removed by collectGarbage().
     */
    private const TEMPORARY_FILE_MAXIMUM_AGE = 3600;

    private const TEMPORARY_FILE_EXTENSION = '.tmp';

    /**
     * Writes the given data to the given file, making it visible to readers in
     * one step.
     *
     * @param string $cacheEntryPathAndFilename Absolute path and filename of the cache entry
     * @param string $data The data to write
     * @return bool true on success, false on failure
     */
    protected function writeCacheFile(string $cacheEntryPathAndFilename, string $data): bool
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $temporaryPathAndFilename = $this->temporaryPathAndFilename($cacheEntryPathAndFilename);
            $numberOfBytesWritten = @file_put_contents($temporaryPathAndFilename, $data);

            if ($numberOfBytesWritten === strlen($data) && @rename($temporaryPathAndFilename, $cacheEntryPathAndFilename)) {
                clearstatcache(true, $cacheEntryPathAndFilename);
                return true;
            }

            @unlink($temporaryPathAndFilename);
            usleep(random_int(10, 500));
        }

        return parent::writeCacheFile($cacheEntryPathAndFilename, $data);
    }

    /**
     * Removes temporary files left behind by processes which died between
     * writing and publishing an entry.
     */
    public function collectGarbage(): void
    {
        $now = time();

        foreach (glob($this->cacheDirectory . '.*' . self::TEMPORARY_FILE_EXTENSION) ?: [] as $pathAndFilename) {
            $modificationTime = @filemtime($pathAndFilename);
            if ($modificationTime !== false && $now - $modificationTime > self::TEMPORARY_FILE_MAXIMUM_AGE) {
                @unlink($pathAndFilename);
            }
        }
    }

    /**
     * Returns a path for a temporary file next to the given cache entry. The name
     * starts with a dot so that it is not mistaken for a cache entry while it is
     * waiting to be published.
     */
    private function temporaryPathAndFilename(string $cacheEntryPathAndFilename): string
    {
        return sprintf(
            '%s/.%s.%d.%s%s',
            dirname($cacheEntryPathAndFilename),
            basename($cacheEntryPathAndFilename),
            getmypid(),
            bin2hex(random_bytes(4)),
            self::TEMPORARY_FILE_EXTENSION
        );
    }
}
