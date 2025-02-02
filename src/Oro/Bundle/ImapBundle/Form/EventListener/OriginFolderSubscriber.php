<?php

namespace Oro\Bundle\ImapBundle\Form\EventListener;

use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class OriginFolderSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT   => 'setOriginToFolders'
        ];
    }

    public function setOriginToFolders(FormEvent $event)
    {
        $data = $event->getData();
        if ($data !== null && $data instanceof UserEmailOrigin) {
            foreach ($data->getFolders() as $folder) {
                $folder->setOrigin($data);
            }
            $event->setData($data);
        }
    }
}
