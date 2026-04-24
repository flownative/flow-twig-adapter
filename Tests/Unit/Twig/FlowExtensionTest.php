<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Tests\Unit\Twig;

use Flownative\TwigAdapter\Twig\FlowExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class FlowExtensionTest extends TestCase
{
    /** @test */
    public function registersFiveFunctions(): void
    {
        $extension = new FlowExtension();
        $functions = $extension->getFunctions();

        self::assertCount(5, $functions);

        $names = array_map(static fn(TwigFunction $f) => $f->getName(), $functions);
        self::assertContains('uri_action', $names);
        self::assertContains('uri_resource', $names);
        self::assertContains('csrf_token', $names);
        self::assertContains('csrf_field', $names);
        self::assertContains('translate', $names);
    }

    /** @test */
    public function uriActionThrowsWithoutControllerContext(): void
    {
        $extension = new FlowExtension();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1745502001);

        $extension->uriAction('index');
    }

    /** @test */
    public function uriResourceThrowsWithoutPackageAndContext(): void
    {
        $extension = new FlowExtension();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1745502002);

        $extension->uriResource('JavaScript/app.js');
    }

    /** @test */
    public function csrfFieldFunctionIsRegistered(): void
    {
        $extension = new FlowExtension();
        $functions = $extension->getFunctions();

        $csrfField = null;
        foreach ($functions as $function) {
            if ($function->getName() === 'csrf_field') {
                $csrfField = $function;
            }
        }

        self::assertNotNull($csrfField);
    }
}
