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

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Psr\Http\Message\StreamInterface;

/**
 * Standalone Twig view for rendering outside of the MVC context.
 *
 * Use this for e-mails, CLI output, PDF generation, or any rendering that does
 * not go through an ActionController:
 *
 *     $view = new StandaloneTwigView();
 *     $view->setTemplatePathAndFilename('resource://My.Package/Private/Templates/Email/Welcome.html.twig');
 *     $view->assign('user', $user);
 *     $html = (string)$view->render();
 *
 * @phpstan-consistent-constructor
 */
class StandaloneTwigView extends TwigView
{
    #[Flow\Inject]
    protected Bootstrap $bootstrap;

    private ?ActionRequest $request;

    public static function createWithOptions(array $options): self
    {
        return new static(null, $options);
    }

    public function __construct(?ActionRequest $request = null, array $options = [])
    {
        $this->request = $request;
        parent::__construct($options);
    }

    public function initializeObject(): void
    {
        if ($this->request === null) {
            $requestHandler = $this->bootstrap->getActiveRequestHandler();
            if ($requestHandler instanceof HttpRequestHandlerInterface) {
                $this->request = ActionRequest::fromHttpRequest($requestHandler->getHttpRequest());
            } else {
                $this->request = ActionRequest::fromHttpRequest(ServerRequest::fromGlobals());
            }
        }

        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($this->request);

        $this->setControllerContext(new ControllerContext(
            $this->request,
            new ActionResponse(),
            new Arguments([]),
            $uriBuilder
        ));
    }

    public function setTemplatePathAndFilename(string $pathAndFilename): void
    {
        $this->options['templatePathAndFilename'] = $pathAndFilename;
    }
}
