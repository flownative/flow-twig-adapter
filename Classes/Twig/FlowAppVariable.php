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
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context as SecurityContext;

/**
 * Provides the `app` global variable in Twig templates.
 *
 *     {{ app.request }}       → ActionRequest
 *     {{ app.user }}          → Account|null (lazy-loaded from SecurityContext)
 *     {{ app.environment }}   → "Development", "Production", etc.
 *     {{ app.debug }}         → true in Development
 */
final class FlowAppVariable
{
    private bool $userResolved = false;
    private ?Account $resolvedUser = null;

    public function __construct(
        public readonly ?ActionRequest $request,
        public readonly string $environment,
        public readonly bool $debug,
    ) {
    }

    public function getUser(): ?Account
    {
        if (!$this->userResolved) {
            $this->userResolved = true;
            $this->resolvedUser = $this->resolveUser();
        }
        return $this->resolvedUser;
    }

    private function resolveUser(): ?Account
    {
        if (Bootstrap::$staticObjectManager === null) {
            return null;
        }

        try {
            /** @var SecurityContext $securityContext */
            $securityContext = Bootstrap::$staticObjectManager->get(SecurityContext::class);
            if (!$securityContext->isInitialized()) {
                return null;
            }
            return $securityContext->getAccount();
        } catch (\Exception) {
            return null;
        }
    }
}
