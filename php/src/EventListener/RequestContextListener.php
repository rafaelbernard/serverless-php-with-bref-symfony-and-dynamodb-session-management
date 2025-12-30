<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
class RequestContextListener
{
    public function __construct(private RequestContext $requestContext) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Force HTTPS for Lambda URLs and set proper context
        if ($request->headers->get('host') && str_contains($request->headers->get('host'), 'lambda-url')) {
            $this->requestContext->setScheme('https');
            $this->requestContext->setHost($request->headers->get('host'));
            $this->requestContext->setHttpPort(80);
            $this->requestContext->setHttpsPort(443);
            
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', 443);
            $request->server->set('REQUEST_SCHEME', 'https');
        }
    }
}
