<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Twig\Cache;

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
use Neos\Cache\Frontend\PhpFrontend;
use Twig\Cache\CacheInterface;

/**
 * Bridges Twig's compiled template cache to Flow's cache framework.
 *
 * Uses PhpFrontend + SimpleFileBackend under the hood, which means:
 * - flow:cache:flush automatically clears compiled Twig templates
 * - Cache files live in Flow's standard temp directory structure
 * - OPcache-friendly file-based storage
 */
readonly class FlowCacheAdapter implements CacheInterface
{
    /**
     * The extension SimpleFileBackend gives entries of a PhpFrontend.
     */
    private const CACHE_ENTRY_FILE_EXTENSION = '.php';

    private string $cacheDirectory;

    public function __construct(
        private PhpFrontend $cache,
    ) {
        $backend = $cache->getBackend();
        if (!$backend instanceof SimpleFileBackend) {
            throw new \RuntimeException(
                sprintf('FlowCacheAdapter requires SimpleFileBackend, got %s', get_class($backend)),
                1745502020
            );
        }
        $this->cacheDirectory = $backend->getCacheDirectory();
    }

    public function generateKey(string $name, string $className): string
    {
        return 'twig_' . hash('xxh128', $name);
    }

    public function write(string $key, string $content): void
    {
        $content = preg_replace('/^<\?php\s*/', '', $content);
        try {
            $this->cache->set($key, $content);
        } catch (\Exception) {
        }
    }

    public function load(string $key): void
    {
        $this->cache->requireOnce($key);
    }

    public function getTimestamp(string $key): int
    {
        $filePath = $this->cacheDirectory . $key . self::CACHE_ENTRY_FILE_EXTENSION;
        return is_file($filePath) ? (int)filemtime($filePath) : 0;
    }
}
