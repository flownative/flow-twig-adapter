<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Tests\Unit\Twig\Loader;

use Flownative\TwigAdapter\Twig\Loader\FlowTemplateLoader;
use PHPUnit\Framework\TestCase;
use Twig\Error\LoaderError;

class FlowTemplateLoaderTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = sys_get_temp_dir() . '/twig-adapter-test-' . uniqid();
        mkdir($this->fixturesPath . '/Templates/Products', 0777, true);
        mkdir($this->fixturesPath . '/Layouts', 0777, true);
        mkdir($this->fixturesPath . '/Components/Atom', 0777, true);

        file_put_contents($this->fixturesPath . '/Templates/Products/Index.html.twig', '<h1>Products</h1>');
        file_put_contents($this->fixturesPath . '/Layouts/Default.html.twig', '{% block content %}{% endblock %}');
        file_put_contents($this->fixturesPath . '/Components/Atom/Button.html.twig', '<button>{{ label }}</button>');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    /** @test */
    public function resolvesTemplateFromBasePath(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        self::assertTrue($loader->exists('Templates/Products/Index.html.twig'));
        self::assertStringContainsString('Products', $loader->getSourceContext('Templates/Products/Index.html.twig')->getCode());
    }

    /** @test */
    public function resolvesLayoutFromBasePath(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        self::assertTrue($loader->exists('Layouts/Default.html.twig'));
    }

    /** @test */
    public function resolvesComponentFromNestedPath(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        self::assertTrue($loader->exists('Components/Atom/Button.html.twig'));
    }

    /** @test */
    public function returnsFalseForNonExistentTemplate(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        self::assertFalse($loader->exists('Templates/NoSuchTemplate.html.twig'));
    }

    /** @test */
    public function throwsLoaderErrorForMissingTemplate(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        $this->expectException(LoaderError::class);
        $loader->getSourceContext('Templates/Missing.html.twig');
    }

    /** @test */
    public function supportsFallbackChainWithMultipleBasePaths(): void
    {
        $overridePath = sys_get_temp_dir() . '/twig-adapter-override-' . uniqid();
        mkdir($overridePath . '/Templates/Products', 0777, true);
        file_put_contents($overridePath . '/Templates/Products/Index.html.twig', '<h1>Overridden</h1>');

        try {
            $loader = new FlowTemplateLoader([$this->fixturesPath, $overridePath]);

            $source = $loader->getSourceContext('Templates/Products/Index.html.twig');
            self::assertStringContainsString('Overridden', $source->getCode());
        } finally {
            $this->removeDirectory($overridePath);
        }
    }

    /** @test */
    public function fallbackReturnsBaseTemplateWhenOverrideDoesNotExist(): void
    {
        $overridePath = sys_get_temp_dir() . '/twig-adapter-override-' . uniqid();
        mkdir($overridePath, 0777, true);

        try {
            $loader = new FlowTemplateLoader([$this->fixturesPath, $overridePath]);

            self::assertTrue($loader->exists('Layouts/Default.html.twig'));
        } finally {
            $this->removeDirectory($overridePath);
        }
    }

    /** @test */
    public function ignoresNonExistentBasePaths(): void
    {
        $loader = new FlowTemplateLoader(['/nonexistent/path', $this->fixturesPath]);

        self::assertTrue($loader->exists('Templates/Products/Index.html.twig'));
    }

    /** @test */
    public function isFreshReturnsTrueForUnmodifiedTemplate(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        self::assertTrue($loader->isFresh('Templates/Products/Index.html.twig', time() + 10));
    }

    /** @test */
    public function cacheKeyIsStableForSameTemplate(): void
    {
        $loader = new FlowTemplateLoader([$this->fixturesPath]);

        $key1 = $loader->getCacheKey('Templates/Products/Index.html.twig');
        $key2 = $loader->getCacheKey('Templates/Products/Index.html.twig');

        self::assertSame($key1, $key2);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}
