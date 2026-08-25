<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Tests\Unit\Twig\Cache;

use Flownative\TwigAdapter\Cache\Backend\AtomicSimpleFileBackend;
use Flownative\TwigAdapter\Twig\Cache\FlowCacheAdapter;
use Neos\Cache\EnvironmentConfiguration;
use Neos\Cache\Frontend\PhpFrontend;
use PHPUnit\Framework\TestCase;

class FlowCacheAdapterTest extends TestCase
{
    private string $cacheDirectory;

    private FlowCacheAdapter $adapter;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/twig-adapter-adapter-' . uniqid() . '/';
        mkdir($this->cacheDirectory, 0777, true);

        $backend = new AtomicSimpleFileBackend(new EnvironmentConfiguration('Testing', $this->cacheDirectory));
        $backend->setCacheDirectory($this->cacheDirectory);

        $frontend = new PhpFrontend('twig-adapter-test', $backend);
        $backend->setCache($frontend);

        $this->adapter = new FlowCacheAdapter($frontend);
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
    public function generateKeyDependsOnTheTemplateName(): void
    {
        $key = $this->adapter->generateKey('Components/Button.html.twig', '__TwigTemplate_abc');

        self::assertStringStartsWith('twig_', $key);
        self::assertNotSame($key, $this->adapter->generateKey('Components/Card.html.twig', '__TwigTemplate_abc'));
    }

    /** @test */
    public function writtenTemplatesCanBeLoaded(): void
    {
        $key = $this->adapter->generateKey('Components/Button.html.twig', '__TwigTemplate_abc');
        $this->adapter->write($key, '<?php define("TWIG_ADAPTER_TEST_TEMPLATE_LOADED", true);');

        $this->adapter->load($key);

        self::assertTrue(defined('TWIG_ADAPTER_TEST_TEMPLATE_LOADED'));
    }

    /**
     * Twig only trusts a cached template when its timestamp is at least as recent
     * as the template source, so a timestamp of zero would recompile and rewrite
     * every template on every request whenever auto reloading is enabled.
     *
     * @test
     */
    public function getTimestampReturnsTheModificationTimeOfAWrittenTemplate(): void
    {
        $key = $this->adapter->generateKey('Components/Button.html.twig', '__TwigTemplate_abc');
        $this->adapter->write($key, '<?php // compiled template');

        self::assertSame(filemtime($this->cacheDirectory . $key . '.php'), $this->adapter->getTimestamp($key));
    }

    /** @test */
    public function getTimestampReturnsZeroForUnknownTemplates(): void
    {
        self::assertSame(0, $this->adapter->getTimestamp($this->adapter->generateKey('Nowhere.html.twig', '__TwigTemplate_abc')));
    }
}
