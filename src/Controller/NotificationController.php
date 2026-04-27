<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    public function badge(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();

        return $this->render('_partials/_notifications_badge.html.twig', [
            'unreadNotifications' => $user instanceof User ? $notificationRepository->countUnreadForUser($user) : 0,
        ]);
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function index(NotificationRepository $notificationRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $notificationRepository->findForUser($user);
        $notificationRepository->markAllReadForUser($user);
        $entityManager->flush();

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }
}
