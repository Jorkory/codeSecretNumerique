<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

final class ExceptionListener
{
    public function __construct(private RequestStack $requestStack, private RouterInterface $router)
    {

    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (str_starts_with($exception->getMessage(), 'No route')) {
            $this->requestStack->getSession()->getFlashBag()->add('info', 'La page que vous avez demandée n\'existe pas');
        } else if (str_starts_with($exception->getMessage(), 'Error rendering "GameDisplay" component: Vous avez été exclu')) {
                $this->requestStack->getSession()->getFlashBag()->add('info', 'Vous avez été exclu pour avoir dépassé le temps limite deux fois de suite.');
        }else {
            $this->requestStack->getSession()->getFlashBag()->add('info', $exception->getMessage());
        }


        $response = new RedirectResponse($this->router->generate('app_main'));
        $event->setResponse($response);
    }
}
