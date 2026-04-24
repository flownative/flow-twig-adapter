<?php
declare(strict_types=1);
namespace Flownative\TwigAdapter\Twig;

/*
 * This file is part of the Flownative.TwigAdapter package.
 *
 * (c) Flownative GmbH - www.flownative.com
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Security\Context as SecurityContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing Flow-specific template functions.
 *
 *     {{ uri_action('show', 'Products', arguments={id: product.id}) }}
 *     {{ uri_resource('JavaScript/app.js', 'Flownative.Beach') }}
 *     {{ csrf_token() }}
 *     {{ csrf_field() }}
 *     {{ translate('members.title', {count: 5}, 'Main', 'Flownative.Beach') }}
 */
class FlowExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?ControllerContext $controllerContext = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('uri_action', $this->uriAction(...)),
            new TwigFunction('uri_resource', $this->uriResource(...)),
            new TwigFunction('csrf_token', $this->csrfToken(...)),
            new TwigFunction('csrf_field', $this->csrfField(...), ['is_safe' => ['html']]),
            new TwigFunction('translate', $this->translate(...)),
        ];
    }

    /**
     * Generate a URL to a controller action.
     *
     *     {{ uri_action('members', 'Organizations', arguments={orgId: org.id}) }}
     *     {{ uri_action('index') }}
     *     {{ uri_action('show', 'Products', 'Vendor.Shop', {id: 42}, absolute=true) }}
     */
    /**
     * @throws \RuntimeException
     */
    public function uriAction(
        string $action,
        ?string $controller = null,
        ?string $package = null,
        array $arguments = [],
        ?string $subpackage = null,
        string $section = '',
        string $format = '',
        array $additionalParams = [],
        bool $absolute = false,
    ): string {
        if ($this->controllerContext === null) {
            throw new \RuntimeException('Cannot generate action URI without a controller context', 1745502001);
        }

        $uriBuilder = $this->controllerContext->getUriBuilder();
        $uriBuilder->reset()
            ->setSection($section)
            ->setCreateAbsoluteUri($absolute)
            ->setArguments($additionalParams)
            ->setFormat($format);

        try {
            return $uriBuilder->uriFor($action, $arguments, $controller, $package, $subpackage);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('Failed to generate URI for action "%s": %s', $action, $e->getMessage()),
                1745502003,
                $e
            );
        }
    }

    /**
     * Generate a URL to a static package resource.
     *
     *     {{ uri_resource('JavaScript/app.js', 'Flownative.Beach') }}
     *     {{ uri_resource('Styles/main.css') }}
     */
    public function uriResource(string $path, ?string $package = null): string
    {
        if ($package === null && $this->controllerContext !== null) {
            $package = $this->controllerContext->getRequest()->getControllerPackageKey();
        }
        if ($package === null) {
            throw new \RuntimeException('Cannot resolve resource URI: no package specified and no controller context available', 1745502002);
        }

        /** @var ResourceManager $resourceManager */
        $resourceManager = Bootstrap::$staticObjectManager->get(ResourceManager::class);
        return $resourceManager->getPublicPackageResourceUri($package, $path);
    }

    /**
     * Return the current CSRF protection token.
     *
     *     <input type="hidden" name="__csrfToken" value="{{ csrf_token() }}" />
     */
    public function csrfToken(): string
    {
        /** @var SecurityContext $securityContext */
        $securityContext = Bootstrap::$staticObjectManager->get(SecurityContext::class);
        return $securityContext->getCsrfProtectionToken();
    }

    /**
     * Return a hidden input field with the CSRF token (convenience wrapper).
     *
     *     <form method="post">
     *         {{ csrf_field() }}
     *     </form>
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="__csrfToken" value="' . htmlspecialchars($this->csrfToken(), ENT_QUOTES, 'UTF-8') . '" />';
    }

    /**
     * Translate a label by ID using Flow's I18n Translator.
     *
     *     {{ translate('members.title') }}
     *     {{ translate('members.count', {count: members|length}) }}
     *     {{ translate('label.id', {}, 'Main', 'Flownative.Beach') }}
     *
     * Falls back to the ID itself if no translation is found.
     */
    public function translate(
        string $id,
        array $arguments = [],
        string $source = 'Main',
        ?string $package = null,
        ?int $quantity = null,
    ): string {
        if ($package === null && $this->controllerContext !== null) {
            $package = $this->controllerContext->getRequest()->getControllerPackageKey();
        }

        try {
            /** @var Translator $translator */
            $translator = Bootstrap::$staticObjectManager->get(Translator::class);
            return $translator->translateById($id, $arguments, $quantity, null, $source, $package ?? 'Neos.Flow') ?? $id;
        } catch (\Exception) {
            return $id;
        }
    }
}
