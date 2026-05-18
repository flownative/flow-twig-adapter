<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\View;

/*
 * This file is part of the Flownative.TwigAdapter package.
 *
 * (c) Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flownative\TwigAdapter\Twig\Cache\FlowCacheAdapter;
use Flownative\TwigAdapter\Twig\FlowAppVariable;
use Flownative\TwigAdapter\Twig\FlowExtension;
use Flownative\TwigAdapter\Twig\Loader\FlowTemplateLoader;
use Neos\Cache\Frontend\PhpFrontend;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Flow\Package\FlowPackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Utility\Environment;
use Neos\Http\Factories\StreamFactoryTrait;
use Psr\Http\Message\StreamInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\Error as TwigError;
use Twig\Extension\DebugExtension;

/**
 * Twig-based view implementation for Flow's MVC system.
 *
 * Drop-in replacement for Neos\FluidAdaptor\View\TemplateView. Activated per
 * controller via Views.yaml or globally via Settings.yaml:
 *
 *     Neos:
 *       Flow:
 *         mvc:
 *           view:
 *             defaultImplementation: 'Flownative\TwigAdapter\View\TwigView'
 *
 * Templates are resolved to:
 *     Resources/Private/Templates/{Controller}/{Action}.html.twig
 *
 * Inside templates, all paths are relative to Resources/Private/:
 *     {% extends "Layouts/Default.html.twig" %}
 *     {% include "Components/Button.html.twig" %}
 *
 * @phpstan-consistent-constructor
 */
class TwigView implements ViewInterface
{
    use StreamFactoryTrait;

    protected array $supportedOptions = [
        'templateRootPathPattern' => [
            '@packageResourcesPath/Private/Templates',
            'Pattern for template root. Placeholder: @packageResourcesPath',
            'string',
        ],
        'templateRootPaths' => [
            [],
            'Explicit template root paths (overrides pattern)',
            'array',
        ],
        'layoutRootPaths' => [
            [],
            'Explicit layout root paths',
            'array',
        ],
        'partialRootPaths' => [
            [],
            'Explicit partial root paths',
            'array',
        ],
        'templatePathAndFilename' => [
            null,
            'Explicit path to a single template file (overrides convention)',
            'string',
        ],
    ];

    protected array $options = [];
    protected array $variables = [];
    protected ?ControllerContext $controllerContext = null;
    protected ?TwigEnvironment $twigEnvironment = null;

    /** @var string[] Resolved base paths for the template loader (typically Resources/Private/) */
    private array $twigBasePaths = [];

    public static function createWithOptions(array $options): self
    {
        return new static($options);
    }

    public function __construct(array $options = [])
    {
        $this->validateOptions($options);
        $this->setOptions($options);
    }

    public function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    public function assignMultiple(array $values): self
    {
        $this->variables = array_merge($this->variables, $values);
        return $this;
    }

    public function setControllerContext(ControllerContext $controllerContext): void
    {
        $this->controllerContext = $controllerContext;
        $this->twigEnvironment = null;

        $request = $controllerContext->getRequest();
        if (!$request instanceof ActionRequest) {
            return;
        }

        if ($this->options['templateRootPaths'] !== []) {
            $this->twigBasePaths = array_map(
                static fn(string $path) => dirname($path) . '/',
                $this->options['templateRootPaths']
            );
            return;
        }

        $packageResourcesPath = $this->resolvePackageResourcesPath($request->getControllerPackageKey());
        if ($packageResourcesPath !== null) {
            $this->twigBasePaths = [$packageResourcesPath . 'Private/'];
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function render(): StreamInterface
    {
        $templateName = $this->resolveTemplateName();
        $twig = $this->initializeTwigEnvironment();

        try {
            $output = $twig->render($templateName, $this->variables);
        } catch (TwigError $e) {
            throw new \RuntimeException(
                sprintf('Twig rendering failed for template "%s": %s', $templateName, $e->getMessage()),
                1745502030,
                $e
            );
        }

        return $this->createStream($output);
    }

    protected function resolveTemplateName(): string
    {
        if ($this->options['templatePathAndFilename'] !== null) {
            return $this->resolveExplicitTemplatePath();
        }

        if ($this->controllerContext === null) {
            throw new \RuntimeException('No controller context set and no templatePathAndFilename configured', 1745502010);
        }

        $request = $this->controllerContext->getRequest();
        $controllerName = str_replace('\\', '/', $request->getControllerName());
        $actionName = ucfirst($request->getControllerActionName());
        $extension = $this->getDefaultExtension();

        $subpackageKey = $request->getControllerSubpackageKey();
        $subpackagePath = ($subpackageKey !== null && $subpackageKey !== '')
            ? str_replace('\\', '/', $subpackageKey) . '/'
            : '';

        return 'Templates/' . $subpackagePath . $controllerName . '/' . $actionName . $extension;
    }

    private function resolveExplicitTemplatePath(): string
    {
        $path = $this->options['templatePathAndFilename'];

        if (str_starts_with($path, 'resource://')) {
            $path = $this->resolveResourceUri($path);
        }

        $privatePos = strpos($path, '/Private/');
        if ($privatePos !== false) {
            $basePath = substr($path, 0, $privatePos + 9);
            $templateName = substr($path, $privatePos + 9);
            $this->twigBasePaths = [$basePath];
            $this->twigEnvironment = null;
            return $templateName;
        }

        $this->twigBasePaths = [dirname($path) . '/'];
        $this->twigEnvironment = null;
        return basename($path);
    }

    protected function initializeTwigEnvironment(): TwigEnvironment
    {
        if ($this->twigEnvironment !== null) {
            return $this->twigEnvironment;
        }

        $loader = new FlowTemplateLoader($this->twigBasePaths);
        $settings = $this->getAdapterSettings();
        $isDevelopment = $this->isDevelopmentContext();

        $twigOptions = [
            'autoescape' => $settings['twigOptions']['autoescape'] ?? 'html',
            'strict_variables' => $settings['twigOptions']['strict_variables'] ?? true,
            'auto_reload' => $isDevelopment,
        ];

        $cache = $this->createCacheAdapter($settings);
        if ($cache !== null) {
            $twigOptions['cache'] = $cache;
        }

        $this->twigEnvironment = new TwigEnvironment($loader, $twigOptions);

        if ($isDevelopment) {
            $this->twigEnvironment->enableDebug();
            $this->twigEnvironment->addExtension(new DebugExtension());
        }

        $this->twigEnvironment->addExtension(new FlowExtension($this->controllerContext));

        $extensionClassNames = $settings['extensions'] ?? [];
        foreach ($extensionClassNames as $className) {
            if (class_exists($className) && Bootstrap::$staticObjectManager !== null) {
                $extension = Bootstrap::$staticObjectManager->get($className);
                if ($extension instanceof \Twig\Extension\ExtensionInterface) {
                    $this->twigEnvironment->addExtension($extension);
                }
            }
        }

        $this->twigEnvironment->addGlobal('app', $this->createAppVariable());

        return $this->twigEnvironment;
    }

    private function createCacheAdapter(array $settings): ?FlowCacheAdapter
    {
        if (!($settings['cache']['enabled'] ?? true)) {
            return null;
        }

        if (Bootstrap::$staticObjectManager === null) {
            return null;
        }

        try {
            /** @var CacheManager $cacheManager */
            $cacheManager = Bootstrap::$staticObjectManager->get(CacheManager::class);
            if (!$cacheManager->hasCache('Flownative_TwigAdapter_Templates')) {
                return null;
            }
            $cache = $cacheManager->getCache('Flownative_TwigAdapter_Templates');
            if (!$cache instanceof PhpFrontend) {
                return null;
            }
            return new FlowCacheAdapter($cache);
        } catch (\Exception) {
            return null;
        }
    }

    private function createAppVariable(): FlowAppVariable
    {
        $request = null;
        $contextString = 'Development';

        if ($this->controllerContext !== null) {
            $request = $this->controllerContext->getRequest();
            if (!$request instanceof ActionRequest) {
                $request = null;
            }
        }

        if (Bootstrap::$staticObjectManager !== null) {
            try {
                /** @var Environment $environment */
                $environment = Bootstrap::$staticObjectManager->get(Environment::class);
                $contextString = (string)$environment->getContext();
            } catch (\Exception) {
            }
        }

        return new FlowAppVariable(
            $request,
            $contextString,
            $this->isDevelopmentContext(),
        );
    }

    private function isDevelopmentContext(): bool
    {
        if (Bootstrap::$staticObjectManager === null) {
            return true;
        }

        try {
            /** @var Environment $environment */
            $environment = Bootstrap::$staticObjectManager->get(Environment::class);
            return $environment->getContext()->isDevelopment();
        } catch (\Exception) {
            return true;
        }
    }

    protected function getDefaultExtension(): string
    {
        return $this->getAdapterSettings()['defaultExtension'] ?? '.html.twig';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAdapterSettings(): array
    {
        if (Bootstrap::$staticObjectManager === null) {
            return [];
        }

        try {
            /** @var ConfigurationManager $configurationManager */
            $configurationManager = Bootstrap::$staticObjectManager->get(ConfigurationManager::class);
            return $configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'Flownative.TwigAdapter'
            ) ?: [];
        } catch (\Exception) {
            return [];
        }
    }

    protected function resolvePackageResourcesPath(string $packageKey): ?string
    {
        if (Bootstrap::$staticObjectManager === null) {
            return null;
        }

        try {
            /** @var PackageManager $packageManager */
            $packageManager = Bootstrap::$staticObjectManager->get(PackageManager::class);
            $package = $packageManager->getPackage($packageKey);
            if ($package instanceof FlowPackageInterface) {
                return $package->getResourcesPath();
            }
        } catch (\Exception) {
        }
        return null;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function resolveResourceUri(string $uri): string
    {
        if (!preg_match('#^resource://([^/]+)/(.+)$#', $uri, $matches)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid resource URI: "%s"', $uri),
                1745502011
            );
        }

        $resourcesPath = $this->resolvePackageResourcesPath($matches[1]);
        if ($resourcesPath === null) {
            throw new \RuntimeException(
                sprintf('Cannot resolve resource URI "%s": package "%s" not found', $uri, $matches[1]),
                1745502012
            );
        }

        return $resourcesPath . $matches[2];
    }

    protected function validateOptions(array $options): void
    {
        $unsupported = array_diff_key($options, $this->supportedOptions);
        if ($unsupported !== []) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported view options: "%s" (class: %s)', implode('", "', array_keys($unsupported)), static::class),
                1745502013
            );
        }
    }

    protected function setOptions(array $options): void
    {
        $defaults = array_map(static fn(array $spec) => $spec[0], $this->supportedOptions);
        $this->options = array_merge($defaults, $options);
    }
}
