<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Tests\Unit\Cache\Backend;

use Flownative\TwigAdapter\Cache\Backend\AtomicSimpleFileBackend;
use Neos\Cache\EnvironmentConfiguration;
use PHPUnit\Framework\TestCase;

class AtomicSimpleFileBackendTest extends TestCase
{
    private string $cacheDirectory;

    private AtomicSimpleFileBackend $backend;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/twig-adapter-cache-' . uniqid() . '/';
        mkdir($this->cacheDirectory, 0777, true);

        $this->backend = new AtomicSimpleFileBackend(new EnvironmentConfiguration('Testing', $this->cacheDirectory));
        $this->backend->setCacheDirectory($this->cacheDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDirectory . '{,.}*', GLOB_BRACE) ?: [] as $pathAndFilename) {
            if (is_file($pathAndFilename)) {
                unlink($pathAndFilename);
            }
        }
        rmdir($this->cacheDirectory);
    }

    /** @test */
    public function storesAndRetrievesEntries(): void
    {
        $this->backend->set('entry', 'some data');

        self::assertTrue($this->backend->has('entry'));
        self::assertSame('some data', $this->backend->get('entry'));
    }

    /** @test */
    public function replacesExistingEntries(): void
    {
        $this->backend->set('entry', 'first revision');
        $this->backend->set('entry', 'second revision');

        self::assertSame('second revision', $this->backend->get('entry'));
    }

    /**
     * Whoever opened the previous revision keeps reading it in full, which is what
     * makes a concurrent include() safe: the entry is published by moving a
     * finished file into place, not by rewriting the file readers are looking at.
     *
     * @test
     */
    public function publishingAnEntryLeavesTheRevisionAReaderAlreadyOpenedIntact(): void
    {
        $this->backend->set('entry', 'first revision');

        $handleOfReaderStartingBeforeTheWrite = fopen($this->cacheDirectory . 'entry', 'rb');
        $this->backend->set('entry', 'second revision, and a considerably longer one at that');

        self::assertSame('first revision', stream_get_contents($handleOfReaderStartingBeforeTheWrite));
        fclose($handleOfReaderStartingBeforeTheWrite);
    }

    /** @test */
    public function writingAnEntryLeavesNoTemporaryFilesBehind(): void
    {
        $this->backend->set('entry', 'some data');

        self::assertSame([], glob($this->cacheDirectory . '.*.tmp') ?: []);
    }

    /** @test */
    public function collectGarbageRemovesAbandonedTemporaryFilesOnly(): void
    {
        $this->backend->set('entry', 'some data');

        $abandonedTemporaryFile = $this->cacheDirectory . '.entry.4711.deadbeef.tmp';
        file_put_contents($abandonedTemporaryFile, 'half a file');
        touch($abandonedTemporaryFile, time() - 7200);

        $recentTemporaryFile = $this->cacheDirectory . '.entry.4712.deadbeef.tmp';
        file_put_contents($recentTemporaryFile, 'half a file');

        $this->backend->collectGarbage();

        self::assertFileDoesNotExist($abandonedTemporaryFile);
        self::assertFileExists($recentTemporaryFile);
        self::assertTrue($this->backend->has('entry'));
    }
}
