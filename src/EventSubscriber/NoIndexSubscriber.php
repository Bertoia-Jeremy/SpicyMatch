<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Seo\Attribute\NoIndex;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class NoIndexSubscriber implements EventSubscriberInterface
{
    public const string HEADER = 'X-Robots-Tag';

    public const string DIRECTIVE = 'noindex, nofollow';

    private const string REQUEST_ATTRIBUTE = '_noindex';

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        if ($event->getAttributes(NoIndex::class) !== []) {
            $event->getRequest()
                ->attributes->set(self::REQUEST_ATTRIBUTE, true);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->getRequest()->attributes->getBoolean(self::REQUEST_ATTRIBUTE)) {
            $event->getResponse()
                ->headers->set(self::HEADER, self::DIRECTIVE);
        }
    }
}
