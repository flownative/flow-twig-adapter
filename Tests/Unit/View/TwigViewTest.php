<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Tests\Unit\View;

use Flownative\TwigAdapter\View\TwigView;
use PHPUnit\Framework\TestCase;

class TwigViewTest extends TestCase
{
    /** @test */
    public function createWithOptionsReturnsInstance(): void
    {
        $view = TwigView::createWithOptions([]);

        self::assertInstanceOf(TwigView::class, $view);
    }

    /** @test */
    public function assignStoresVariableAndReturnsSelf(): void
    {
        $view = TwigView::createWithOptions([]);

        $result = $view->assign('name', 'Beach');

        self::assertSame($view, $result);
    }

    /** @test */
    public function assignMultipleStoresVariablesAndReturnsSelf(): void
    {
        $view = TwigView::createWithOptions([]);

        $result = $view->assignMultiple(['name' => 'Beach', 'version' => 2]);

        self::assertSame($view, $result);
    }

    /** @test */
    public function createWithUnsupportedOptionsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1745502013);

        TwigView::createWithOptions(['nonExistentOption' => 'value']);
    }

    /** @test */
    public function renderWithExplicitTemplatePathReturnsRenderedContent(): void
    {
        $tempDir = sys_get_temp_dir() . '/twig-view-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test.html.twig', 'Hello {{ name }}!');

        try {
            $view = TwigView::createWithOptions([
                'templatePathAndFilename' => $tempDir . '/test.html.twig',
            ]);
            $view->assign('name', 'World');

            $stream = $view->render();

            self::assertSame('Hello World!', (string)$stream);
        } finally {
            unlink($tempDir . '/test.html.twig');
            rmdir($tempDir);
        }
    }

    /** @test */
    public function renderWithoutControllerContextAndWithoutExplicitTemplateThrowsException(): void
    {
        $view = TwigView::createWithOptions([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1745502010);

        $view->render();
    }

    /** @test */
    public function templateExtendsAndIncludeWorkWithPrivateBasePath(): void
    {
        $tempDir = sys_get_temp_dir() . '/twig-view-extend-test-' . uniqid();
        mkdir($tempDir . '/Private/Templates', 0777, true);
        mkdir($tempDir . '/Private/Layouts', 0777, true);

        file_put_contents($tempDir . '/Private/Layouts/Base.html.twig', 'HEADER|{% block content %}{% endblock %}|FOOTER');
        file_put_contents($tempDir . '/Private/Templates/Page.html.twig', '{% extends "Layouts/Base.html.twig" %}{% block content %}BODY{% endblock %}');

        try {
            $view = TwigView::createWithOptions([
                'templatePathAndFilename' => $tempDir . '/Private/Templates/Page.html.twig',
            ]);

            $stream = $view->render();

            self::assertSame('HEADER|BODY|FOOTER', (string)$stream);
        } finally {
            unlink($tempDir . '/Private/Templates/Page.html.twig');
            unlink($tempDir . '/Private/Layouts/Base.html.twig');
            rmdir($tempDir . '/Private/Templates');
            rmdir($tempDir . '/Private/Layouts');
            rmdir($tempDir . '/Private');
            rmdir($tempDir);
        }
    }

    /** @test */
    public function autoEscapingIsEnabledByDefault(): void
    {
        $tempDir = sys_get_temp_dir() . '/twig-view-escape-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/test.html.twig', '{{ html }}');

        try {
            $view = TwigView::createWithOptions([
                'templatePathAndFilename' => $tempDir . '/test.html.twig',
            ]);
            $view->assign('html', '<script>alert("xss")</script>');

            $stream = $view->render();

            self::assertStringNotContainsString('<script>', (string)$stream);
            self::assertStringContainsString('&lt;script&gt;', (string)$stream);
        } finally {
            unlink($tempDir . '/test.html.twig');
            rmdir($tempDir);
        }
    }
}
