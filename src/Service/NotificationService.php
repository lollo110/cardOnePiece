<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function notifyReply(
        ?User $recipient,
        ?User $actorUser,
        string $actorName,
        string $sourceType,
        string $targetTitle,
        string $targetUrl,
    ): void {
        if (!$recipient) {
            return;
        }

        if ($actorUser && $actorUser->getId() === $recipient->getId()) {
            return;
        }

        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setActorUser($actorUser)
            ->setActorName($actorName !== '' ? $actorName : 'Someone')
            ->setType(Notification::TYPE_REPLY)
            ->setSourceType($sourceType)
            ->setTargetTitle($targetTitle)
            ->setTargetUrl($targetUrl);

        $this->entityManager->persist($notification);
    }
}
