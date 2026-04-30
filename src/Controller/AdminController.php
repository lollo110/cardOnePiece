<?php

namespace App\Controller;

use App\Entity\BlogComment;
use App\Entity\BlogThread;
use App\Entity\Card;
use App\Entity\CardComment;
use App\Entity\Deck;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        $blockedComments = $entityManager->getRepository(CardComment::class)->count(['moderationStatus' => CardComment::STATUS_BLOCKED])
            + $entityManager->getRepository(BlogComment::class)->count(['moderationStatus' => BlogComment::STATUS_BLOCKED])
            + $entityManager->getRepository(BlogThread::class)->count(['moderationStatus' => BlogThread::STATUS_BLOCKED]);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'cards' => $entityManager->getRepository(Card::class)->count([]),
                'decks' => $entityManager->getRepository(Deck::class)->count([]),
                'users' => $entityManager->getRepository(User::class)->count([]),
                'blockedComments' => $blockedComments,
            ],
        ]);
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findForAdmin(),
        ]);
    }

    #[Route('/users/{id}/admin-role', name: 'admin_user_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateUserRole(User $targetUser, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_role_' . $targetUser->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.notice.csrf'));

            return $this->redirectToRoute('admin_users');
        }

        $action = (string) $request->request->get('action', '');
        $currentUser = $this->getUser();

        if ($action === 'remove_admin' && $currentUser instanceof User && $currentUser->getId() === $targetUser->getId()) {
            $this->addFlash('error', $this->translator->trans('admin.notice.self_demote'));

            return $this->redirectToRoute('admin_users');
        }

        if ($action === 'add_admin') {
            $targetUser->setRoles($this->withRole($targetUser, 'ROLE_ADMIN'));
            $this->addFlash('success', $this->translator->trans('admin.notice.admin_added', ['%username%' => $targetUser->getUsername()]));
        } elseif ($action === 'remove_admin') {
            $targetUser->setRoles($this->withoutRole($targetUser, 'ROLE_ADMIN'));
            $this->addFlash('success', $this->translator->trans('admin.notice.admin_removed', ['%username%' => $targetUser->getUsername()]));
        }

        $entityManager->flush();

        return $this->redirectToRoute('admin_users');
    }

    /**
     * @return list<string>
     */
    private function withRole(User $user, string $role): array
    {
        $roles = array_values(array_filter($user->getRoles(), static fn (string $existingRole): bool => $existingRole !== 'ROLE_USER'));
        $roles[] = $role;

        return array_values(array_unique($roles));
    }

    /**
     * @return list<string>
     */
    private function withoutRole(User $user, string $role): array
    {
        return array_values(array_filter(
            $user->getRoles(),
            static fn (string $existingRole): bool => !in_array($existingRole, [$role, 'ROLE_USER'], true)
        ));
    }
}
