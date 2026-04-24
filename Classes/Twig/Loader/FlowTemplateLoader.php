<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Twig\Loader;

/*
 * This file is part of the Flownative.TwigAdapter package.
 *
 * (c) Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Twig template loader that follows Neos Flow's resource path conventions.
 *
 * Uses Resources/Private/ as the base path, so templates can reference each other
 * with paths relative to that directory:
 *
 *     {% extends "Layouts/Default.html.twig" %}
 *     {% include "Components/Button.html.twig" %}
 *     {% include "Templates/Shared/Header.html.twig" %}
 */
class FlowTemplateLoader implements LoaderInterface
{
    private FilesystemLoader $filesystemLoader;

    /**
     * @param string[] $basePaths Base paths to search for templates (typically Resources/Private/)
     */
    public function __construct(array $basePaths = [])
    {
        $this->filesystemLoader = new FilesystemLoader();
        foreach ($basePaths as $path) {
            $this->addBasePath($path);
        }
    }

    public function addBasePath(string $path): void
    {
        if (is_dir($path)) {
            try {
                $this->filesystemLoader->prependPath($path);
            } catch (LoaderError) {
            }
        }
    }

    public function getSourceContext(string $name): Source
    {
        return $this->filesystemLoader->getSourceContext($name);
    }

    public function getCacheKey(string $name): string
    {
        return $this->filesystemLoader->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->filesystemLoader->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->filesystemLoader->exists($name);
    }
}
