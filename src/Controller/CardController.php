<?php

namespace App\Controller;

use App\Entity\BlogComment;
use App\Entity\BlogThread;
use App\Entity\BlogTopic;
use App\Entity\Card;
use App\Entity\CardComment;
use App\Entity\CardLanguagePrice;
use App\Entity\User;
use App\Repository\BlogCommentRepository;
use App\Repository\BlogThreadRepository;
use App\Repository\BlogTopicRepository;
use App\Repository\CardCommentRepository;
use App\Service\CardService;
use App\Service\CommentModerationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CardController extends AbstractController
{
    private const BLOG_TOPICS = [
        [
            'slug' => 'trading',
            'title_key' => 'blog.topic.trading.title',
            'description_key' => 'blog.topic.trading.description',
            'button_key' => 'blog.topic.trading.button',
        ],
        [
            'slug' => 'game',
            'title_key' => 'blog.topic.game.title',
            'description_key' => 'blog.topic.game.description',
            'button_key' => 'blog.topic.game.button',
        ],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function home(
        CardService $service,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        CardCommentRepository $cardCommentRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        return $this->render('home/index.html.twig', [
            'recentCards' => $service->recentCards(12),
            'valuableCards' => $service->mostValuableCards(16),
            'topCommentedCard' => $cardCommentRepository->findTopCommentedCard(),
            'topTradingThread' => $threadRepository->findTopCommentedForTopicSlug('trading'),
            'topGameThread' => $threadRepository->findTopCommentedForTopicSlug('game'),
            'topics' => $this->localizedTopics($this->syncBlogTopics($topicRepository, $entityManager)),
        ]);
    }

    #[Route('/market', name: 'market')]
    public function market(): Response
    {
        return $this->redirectToRoute('blog');
    }

    #[Route('/blog', name: 'blog')]
    public function blog(BlogTopicRepository $topicRepository, EntityManagerInterface $entityManager): Response
    {
        return $this->render('blog/index.html.twig', [
            'topics' => $this->localizedTopics($this->syncBlogTopics($topicRepository, $entityManager)),
        ]);
    }

    #[Route('/blog/{slug}', name: 'blog_topic', requirements: ['slug' => 'trading|game|trading-prices'], methods: ['GET', 'POST'])]
    public function blogTopic(
        string $slug,
        Request $request,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): Response {
        if ($slug === 'trading-prices') {
            return $this->redirectToRoute('blog_topic', ['slug' => 'trading'], Response::HTTP_MOVED_PERMANENTLY);
        }

        $topics = $this->syncBlogTopics($topicRepository, $entityManager);
        $topic = $topicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            throw $this->createNotFoundException('Blog topic not found.');
        }

        if ($request->isMethod('POST')) {
            $this->handleBlogThreadSubmission($request, $topic, $entityManager, $validator, $commentModerationService);

            return $this->redirect($this->generateUrl('blog_topic', ['slug' => $slug]) . '#topics');
        }

        return $this->render('blog/topic.html.twig', [
            'topic' => $topic,
            'topicView' => $this->localizedTopic($topic),
            'topics' => $this->localizedTopics($topics),
            'threads' => $threadRepository->findForRoom($topic),
            'commentAuthorName' => $this->resolveCommentAuthorName($request),
        ]);
    }

    #[Route('/blog/{slug}/topics/{threadId}', name: 'blog_thread', requirements: ['slug' => 'trading|game', 'threadId' => '\d+'], methods: ['GET', 'POST'])]
    public function blogThread(
        string $slug,
        int $threadId,
        Request $request,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
        NotificationService $notificationService,
    ): Response {
        $this->syncBlogTopics($topicRepository, $entityManager);
        $topic = $topicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            throw $this->createNotFoundException('Blog topic not found.');
        }

        $thread = $threadRepository->findOnePublishedForRoom($threadId, $topic);

        if (!$thread) {
            throw $this->createNotFoundException('Discussion topic not found.');
        }

        if ($request->isMethod('POST')) {
            $this->handleBlogReplySubmission($request, $topic, $thread, $commentRepository, $entityManager, $validator, $commentModerationService, $notificationService);

            return $this->redirect($this->generateUrl('blog_thread', ['slug' => $slug, 'threadId' => $threadId]) . '#replies');
        }

        return $this->render('blog/thread.html.twig', [
            'topic' => $topic,
            'topicView' => $this->localizedTopic($topic),
            'thread' => $thread,
            'comments' => $this->buildBlogCommentTree($request, $commentRepository->findForThread($thread)),
            'commentAuthorName' => $this->resolveCommentAuthorName($request),
        ]);
    }

    #[Route('/cards', name: 'cards')]
    public function index(Request $request, CardService $service): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $sort = (string) $request->query->get('sort', 'relevance');
        $rarity = trim((string) $request->query->get('rarity', ''));
        $collection = trim((string) $request->query->get('collection', ''));
        $response = $service->searchCards(
            $query !== '' ? $query : null,
            $page,
            $sort,
            $rarity !== '' ? $rarity : null,
            $collection !== '' ? $collection : null,
        );
        $paging = $response['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20];
        $results = $response['data'] ?? [];
        $rarityOptions = $service->rarityOptions($query !== '' ? $query : null);
        $collectionOptions = $service->collectionOptions($query !== '' ? $query : null);

        return $this->render('card/index.html.twig', [
            'results' => $results,
            'query' => $query,
            'sort' => $sort,
            'rarity' => $rarity,
            'collection' => $collection,
            'paging' => [
                'current' => (int) ($paging['current'] ?? 1),
                'total' => (int) ($paging['total'] ?? 1),
                'per_page' => (int) ($paging['per_page'] ?? 20),
            ],
            'totalResults' => (int) ($response['results'] ?? 0),
            'error' => $response['error'] ?? null,
            'rarityOptions' => $rarityOptions,
            'collectionOptions' => $collectionOptions,
        ]);
    }

    #[Route('/cards/filter', name: 'cards_filter', methods: ['GET'])]
    public function filter(Request $request, CardService $service): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'relevance');
        $rarity = trim((string) $request->query->get('rarity', ''));
        $collection = trim((string) $request->query->get('collection', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $response = $service->searchCards(
            $query !== '' ? $query : null,
            $page,
            $sort,
            $rarity !== '' ? $rarity : null,
            $collection !== '' ? $collection : null,
        );
        $paging = $response['paging'] ?? ['current' => 1, 'total' => 1, 'per_page' => 20];

        return $this->json([
            'html' => $this->renderView('card/_results.html.twig', [
                'results' => $response['data'] ?? [],
                'query' => $query,
                'sort' => $sort,
                'rarity' => $rarity,
                'collection' => $collection,
                'paging' => [
                    'current' => (int) ($paging['current'] ?? 1),
                    'total' => (int) ($paging['total'] ?? 1),
                    'per_page' => (int) ($paging['per_page'] ?? 20),
                ],
                'totalResults' => (int) ($response['results'] ?? 0),
                'error' => $response['error'] ?? null,
                'rarityOptions' => $service->rarityOptions($query !== '' ? $query : null),
                'collectionOptions' => $service->collectionOptions($query !== '' ? $query : null),
            ]),
        ]);
    }

    #[Route('/cards/suggestions', name: 'cards_suggestions')]
    public function suggestions(Request $request, CardService $service): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(
            fn (array $suggestion): array => [
                'label' => $suggestion['label'],
                'url' => $this->generateUrl('card_show', [
                    'id' => $suggestion['id'],
                    'name' => $suggestion['name'],
                ]),
            ],
            $service->suggestCards($query)
        ));
    }

    #[Route('/cards/{id}', name: 'card_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        Request $request,
        CardService $service,
        CardCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $card = $service->findCard($id, $request->query->get('name'));

        if (!$card) {
            throw $this->createNotFoundException('Card not found.');
        }

        $cardEntity = $entityManager->getRepository(\App\Entity\Card::class)->findOneBy(['apiId' => $id]);
        $commentLanguages = $cardEntity
            ? $this->cardCommentLanguagesFromEntity($cardEntity, $entityManager, $card)
            : $this->cardCommentLanguages($card);
        $defaultLanguage = $commentLanguages[0]['key'] ?? 'english';

        return $this->render('card/show.html.twig', [
            'card' => $card,
            'cardCommentOptions' => [
                'discussionTypes' => $this->cardDiscussionTypes(),
                'languages' => $commentLanguages,
                'defaultDiscussionType' => CardComment::DISCUSSION_TRADING,
                'defaultLanguage' => $defaultLanguage,
            ],
            'initialComments' => $cardEntity ? $this->serializeCardComments(
                $request,
                $commentRepository->findForCardDiscussion($cardEntity, CardComment::DISCUSSION_TRADING, $defaultLanguage)
            ) : [],
            'commentAuthorName' => $this->resolveCommentAuthorName($request),
        ]);
    }

    #[Route('/cards/{id}/price-history', name: 'card_price_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function priceHistory(int $id, Request $request, CardService $service): JsonResponse
    {
        $payload = $service->priceHistoryForCard($id, (string) $request->query->get('range', 'all'));

        if ($payload === null) {
            return $this->json(['error' => 'Card not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($payload);
    }

    #[Route('/cards/{id}/comments', name: 'card_comments', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cardComments(
        int $id,
        Request $request,
        CardCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
        NotificationService $notificationService,
    ): JsonResponse {
        $cardEntity = $entityManager->getRepository(Card::class)->findOneBy(['apiId' => $id]);

        if (!$cardEntity) {
            return $this->json(['error' => $this->translator->trans('card.comments.error.not_found')], Response::HTTP_NOT_FOUND);
        }

        $languageMap = $this->cardCommentLanguageMapFromEntity($cardEntity, $entityManager);
        $discussionTypes = $this->cardDiscussionTypes();

        if ($request->isMethod('POST')) {
            $discussionType = (string) $request->request->get('discussion_type', CardComment::DISCUSSION_TRADING);
            $language = (string) $request->request->get('language', array_key_first($languageMap) ?: 'english');
            $action = (string) $request->request->get('action', 'create');

            if (!isset($discussionTypes[$discussionType])) {
                return $this->json(['error' => $this->translator->trans('card.comments.error.invalid_discussion')], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($languageMap[$language])) {
                return $this->json(['error' => $this->translator->trans('card.comments.error.invalid_language')], Response::HTTP_BAD_REQUEST);
            }

            if (!$this->isCsrfTokenValid('card_comment_' . $id, (string) $request->request->get('_token'))) {
                return $this->json(['error' => $this->translator->trans('comments.error.csrf')], Response::HTTP_FORBIDDEN);
            }

            if ($action === 'edit' || $action === 'delete') {
                $commentId = $this->positiveRequestInt($request, 'comment_id');
                $comment = $commentId > 0
                    ? $commentRepository->findOnePublishedForDiscussion($cardEntity, $commentId, $discussionType, $language)
                    : null;

                if (!$comment) {
                    return $this->json(['error' => $this->translator->trans('comments.error.not_found')], Response::HTTP_NOT_FOUND);
                }

                if (!$this->canManageComment($request, $comment)) {
                    return $this->json(['error' => $this->translator->trans('comments.error.forbidden')], Response::HTTP_FORBIDDEN);
                }

                if ($action === 'delete') {
                    $entityManager->remove($comment);
                    $entityManager->flush();

                    return $this->json($this->cardCommentPayload($request, $cardEntity, $discussionType, $language, $discussionTypes, $languageMap, $commentRepository));
                }

                $content = trim((string) $request->request->get('content', ''));

                if ($content === '') {
                    return $this->json(['error' => $this->translator->trans('comments.error.empty')], Response::HTTP_BAD_REQUEST);
                }

                $comment->setContent($content);
                $validationError = $this->firstValidationError($validator->validate($comment));

                if ($validationError !== null) {
                    return $this->json(['error' => $validationError], Response::HTTP_BAD_REQUEST);
                }

                $moderationResult = $commentModerationService->moderate($comment->getContent());

                if (!$moderationResult->isApproved()) {
                    $comment
                        ->setModerationStatus(CardComment::STATUS_BLOCKED)
                        ->setModerationReason($moderationResult->getReason());
                }

                $entityManager->flush();

                $payload = $this->cardCommentPayload($request, $cardEntity, $discussionType, $language, $discussionTypes, $languageMap, $commentRepository);

                if (!$moderationResult->isApproved()) {
                    $payload['notice'] = $this->translator->trans('comments.notice.hidden');
                }

                return $this->json($payload, $moderationResult->isApproved() ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
            }

            $content = trim((string) $request->request->get('content', ''));
            $parentId = $this->positiveRequestInt($request, 'parent_id');
            $parentComment = $parentId > 0
                ? $commentRepository->findOnePublishedForDiscussion($cardEntity, $parentId, $discussionType, $language)
                : null;

            if ($content === '') {
                return $this->json(['error' => $this->translator->trans('comments.error.empty')], Response::HTTP_BAD_REQUEST);
            }

            if ($parentId > 0 && $parentComment === null) {
                return $this->json(['error' => $this->translator->trans('card.comments.error.invalid_parent')], Response::HTTP_BAD_REQUEST);
            }

            $comment = (new CardComment())
                ->setCard($cardEntity)
                ->setParentComment($parentComment)
                ->setAuthorUser($this->currentUser())
                ->setAuthorName($this->resolveCommentAuthorName($request))
                ->setDiscussionType($discussionType)
                ->setLanguage($language)
                ->setContent($content);

            $validationError = $this->firstValidationError($validator->validate($comment));

            if ($validationError !== null) {
                return $this->json(['error' => $validationError], Response::HTTP_BAD_REQUEST);
            }

            $moderationResult = $commentModerationService->moderate($comment->getContent());

            if (!$moderationResult->isApproved()) {
                $comment
                    ->setModerationStatus(CardComment::STATUS_BLOCKED)
                    ->setModerationReason($moderationResult->getReason());
            }

            $entityManager->persist($comment);
            if ($parentComment && $moderationResult->isApproved()) {
                $notificationService->notifyReply(
                    $parentComment->getAuthorUser(),
                    $this->currentUser(),
                    $comment->getAuthorName(),
                    \App\Entity\Notification::SOURCE_CARD,
                    $cardEntity->getNameNumbered() ?? $cardEntity->getName(),
                    $this->generateUrl('card_show', ['id' => $cardEntity->getApiId()]) . '#comments'
                );
            }
            $entityManager->flush();

            $payload = $this->cardCommentPayload($request, $cardEntity, $discussionType, $language, $discussionTypes, $languageMap, $commentRepository);

            if (!$moderationResult->isApproved()) {
                $payload['notice'] = $this->translator->trans('comments.notice.hidden');
            }

            return $this->json($payload, $moderationResult->isApproved() ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
        }

        $discussionType = (string) $request->query->get('discussion', CardComment::DISCUSSION_TRADING);
        $language = (string) $request->query->get('language', array_key_first($languageMap) ?: 'english');

        if (!isset($discussionTypes[$discussionType])) {
            $discussionType = CardComment::DISCUSSION_TRADING;
        }

        if (!isset($languageMap[$language])) {
            $language = array_key_first($languageMap) ?: 'english';
        }

        return $this->json([
            'discussionType' => $discussionType,
            'discussionLabel' => $discussionTypes[$discussionType] ?? 'Trading',
            'language' => $language,
            'languageLabel' => $languageMap[$language] ?? ucfirst($language),
            'comments' => $this->serializeCardComments(
                $request,
                $commentRepository->findForCardDiscussion($cardEntity, $discussionType, $language)
            ),
        ]);
    }

    private function syncBlogTopics(BlogTopicRepository $topicRepository, EntityManagerInterface $entityManager): array
    {
        $stored = [];

        foreach ($topicRepository->findOrdered() as $topic) {
            $stored[$topic->getSlug()] = $topic;
        }

        foreach (self::BLOG_TOPICS as $definition) {
            $topic = $stored[$definition['slug']]
                ?? ($definition['slug'] === 'trading' ? ($stored['trading-prices'] ?? null) : null)
                ?? new BlogTopic();
            $topic
                ->setTitle($this->translator->trans($definition['title_key'], [], null, 'en'))
                ->setSlug($definition['slug'])
                ->setDescription($this->translator->trans($definition['description_key'], [], null, 'en'));
            $entityManager->persist($topic);
            $stored[$definition['slug']] = $topic;
        }

        $entityManager->flush();

        return array_values(array_map(
            fn (array $definition): BlogTopic => $stored[$definition['slug']],
            self::BLOG_TOPICS
        ));
    }

    private function handleBlogThreadSubmission(
        Request $request,
        BlogTopic $topic,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): void
    {
        if (!$this->isCsrfTokenValid('blog_thread_' . $topic->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('comments.error.csrf'));

            return;
        }

        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

        $thread = (new BlogThread())
            ->setTopic($topic)
            ->setAuthorUser($this->currentUser())
            ->setAuthorName($this->resolveCommentAuthorName($request))
            ->setTitle($title)
            ->setContent($content);

        $validationError = $this->firstValidationError($validator->validate($thread));

        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return;
        }

        $moderationResult = $commentModerationService->moderate($thread->getTitle() . "\n" . $thread->getContent());

        if (!$moderationResult->isApproved()) {
            $thread
                ->setModerationStatus(BlogThread::STATUS_BLOCKED)
                ->setModerationReason($moderationResult->getReason());
        }

        $entityManager->persist($thread);
        $entityManager->flush();

        if ($moderationResult->isApproved()) {
            $this->addFlash('success', $this->translator->trans('blog.thread.notice.topic_live'));

            return;
        }

        $this->addFlash('warning', $this->translator->trans('comments.notice.hidden'));
    }

    private function handleBlogReplySubmission(
        Request $request,
        BlogTopic $topic,
        BlogThread $thread,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
        NotificationService $notificationService,
    ): void
    {
        if (!$this->isCsrfTokenValid('blog_reply_' . $thread->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('comments.error.csrf'));

            return;
        }

        $content = trim((string) $request->request->get('content', ''));
        $parentId = $this->positiveRequestInt($request, 'parent_id');
        $parentComment = $parentId > 0 ? $commentRepository->findOnePublishedForThread($parentId, $thread) : null;

        if ($parentId > 0 && $parentComment === null) {
            $this->addFlash('error', $this->translator->trans('blog.thread.invalid_parent'));

            return;
        }

        $comment = (new BlogComment())
            ->setTopic($topic)
            ->setThread($thread)
            ->setParentComment($parentComment)
            ->setAuthorUser($this->currentUser())
            ->setAuthorName($this->resolveCommentAuthorName($request))
            ->setContent($content);

        $validationError = $this->firstValidationError($validator->validate($comment));

        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return;
        }

        $moderationResult = $commentModerationService->moderate($comment->getContent());

        if (!$moderationResult->isApproved()) {
            $comment
                ->setModerationStatus(BlogComment::STATUS_BLOCKED)
                ->setModerationReason($moderationResult->getReason());
        } else {
            $thread->setLastActivityAt($comment->getCreatedAt());
        }

        $entityManager->persist($comment);
        $entityManager->flush();

        if ($parentComment && $moderationResult->isApproved()) {
            $notificationService->notifyReply(
                $parentComment->getAuthorUser(),
                $this->currentUser(),
                $comment->getAuthorName(),
                \App\Entity\Notification::SOURCE_BLOG,
                $thread->getTitle(),
                $this->generateUrl('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]) . '#comment-' . $comment->getId()
            );
            $entityManager->flush();
        }

        if ($moderationResult->isApproved()) {
            $this->addFlash('success', $this->translator->trans('blog.thread.notice.reply_live'));

            return;
        }

        $this->addFlash('warning', $this->translator->trans('comments.notice.hidden'));
    }

    #[Route('/blog/{slug}/topics/{threadId}/comments/{commentId}/edit', name: 'blog_comment_edit', requirements: ['slug' => 'trading|game', 'threadId' => '\d+', 'commentId' => '\d+'], methods: ['POST'])]
    public function editBlogComment(
        string $slug,
        int $threadId,
        int $commentId,
        Request $request,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        CommentModerationService $commentModerationService,
    ): Response {
        [$topic, $thread, $comment] = $this->resolveBlogCommentAction($slug, $threadId, $commentId, $topicRepository, $threadRepository, $commentRepository, $entityManager);

        if (!$this->isCsrfTokenValid('blog_comment_manage_' . $comment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('comments.error.csrf'));

            return $this->redirectToRoute('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]);
        }

        if (!$this->canManageComment($request, $comment)) {
            $this->addFlash('error', $this->translator->trans('comments.error.forbidden'));

            return $this->redirectToRoute('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]);
        }

        $comment->setContent((string) $request->request->get('content', ''));
        $validationError = $this->firstValidationError($validator->validate($comment));

        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return $this->redirect($this->generateUrl('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]) . '#comment-' . $comment->getId());
        }

        $moderationResult = $commentModerationService->moderate($comment->getContent());

        if (!$moderationResult->isApproved()) {
            $comment
                ->setModerationStatus(BlogComment::STATUS_BLOCKED)
                ->setModerationReason($moderationResult->getReason());
        }

        $entityManager->flush();
        $this->addFlash($moderationResult->isApproved() ? 'success' : 'warning', $this->translator->trans($moderationResult->isApproved() ? 'comments.notice.updated' : 'comments.notice.hidden'));

        return $this->redirect($this->generateUrl('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]) . '#replies');
    }

    #[Route('/blog/{slug}/topics/{threadId}/comments/{commentId}/delete', name: 'blog_comment_delete', requirements: ['slug' => 'trading|game', 'threadId' => '\d+', 'commentId' => '\d+'], methods: ['POST'])]
    public function deleteBlogComment(
        string $slug,
        int $threadId,
        int $commentId,
        Request $request,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        [$topic, $thread, $comment] = $this->resolveBlogCommentAction($slug, $threadId, $commentId, $topicRepository, $threadRepository, $commentRepository, $entityManager);

        if (!$this->isCsrfTokenValid('blog_comment_manage_' . $comment->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('comments.error.csrf'));

            return $this->redirectToRoute('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]);
        }

        if (!$this->canManageComment($request, $comment)) {
            $this->addFlash('error', $this->translator->trans('comments.error.forbidden'));

            return $this->redirectToRoute('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]);
        }

        $entityManager->remove($comment);
        $entityManager->flush();
        $this->addFlash('success', $this->translator->trans('comments.notice.deleted'));

        return $this->redirect($this->generateUrl('blog_thread', ['slug' => $topic->getSlug(), 'threadId' => $thread->getId()]) . '#replies');
    }

    private function resolveCommentAuthorName(Request $request): string
    {
        return $this->currentUser()?->getUsername() ?? $this->guestCommentAlias($request);
    }

    private function canManageComment(Request $request, BlogComment|CardComment $comment): bool
    {
        $currentUser = $this->currentUser();

        if ($comment->getAuthorUser() !== null) {
            return $currentUser !== null && $comment->getAuthorUser()->getId() === $currentUser->getId();
        }

        return $comment->getAuthorName() === $this->guestCommentAlias($request);
    }

    /**
     * @return array{0: BlogTopic, 1: BlogThread, 2: BlogComment}
     */
    private function resolveBlogCommentAction(
        string $slug,
        int $threadId,
        int $commentId,
        BlogTopicRepository $topicRepository,
        BlogThreadRepository $threadRepository,
        BlogCommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): array {
        $this->syncBlogTopics($topicRepository, $entityManager);
        $topic = $topicRepository->findOneBy(['slug' => $slug]);

        if (!$topic) {
            throw $this->createNotFoundException('Blog topic not found.');
        }

        $thread = $threadRepository->findOnePublishedForRoom($threadId, $topic);

        if (!$thread) {
            throw $this->createNotFoundException('Discussion topic not found.');
        }

        $comment = $commentRepository->findOnePublishedForThread($commentId, $thread);

        if (!$comment) {
            throw $this->createNotFoundException('Comment not found.');
        }

        return [$topic, $thread, $comment];
    }

    private function cardCommentPayload(
        Request $request,
        Card $card,
        string $discussionType,
        string $language,
        array $discussionTypes,
        array $languageMap,
        CardCommentRepository $commentRepository,
    ): array {
        return [
            'discussionType' => $discussionType,
            'discussionLabel' => $discussionTypes[$discussionType] ?? 'Trading',
            'language' => $language,
            'languageLabel' => $languageMap[$language] ?? ucfirst($language),
            'comments' => $this->serializeCardComments(
                $request,
                $commentRepository->findForCardDiscussion($card, $discussionType, $language)
            ),
        ];
    }

    private function cardDiscussionTypes(): array
    {
        return [
            CardComment::DISCUSSION_TRADING => $this->translator->trans('discussion.topic.trading'),
            CardComment::DISCUSSION_GAME => $this->translator->trans('discussion.topic.game'),
        ];
    }

    private function cardCommentLanguages(array $card): array
    {
        $labels = $this->normalizeLanguageLabels($card['languages'] ?? []);

        if ($labels === []) {
            $labels = ['English'];
        }

        return $this->languageOptionsFromLabels($labels);
    }

    private function cardCommentLanguagesFromEntity(Card $card, EntityManagerInterface $entityManager, ?array $cardView = null): array
    {
        $map = $this->cardCommentLanguageMapFromEntity($card, $entityManager, $cardView);
        $options = [];

        foreach ($map as $key => $label) {
            $options[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        if ($options === []) {
            return [['key' => 'english', 'label' => 'English']];
        }

        return $options;
    }

    private function cardCommentLanguageMapFromEntity(Card $card, EntityManagerInterface $entityManager, ?array $cardView = null): array
    {
        $labels = $this->normalizeLanguageLabels(($card->getRawData() ?? [])['languages'] ?? []);

        foreach (($cardView['language_prices'] ?? []) as $price) {
            if (is_array($price) && isset($price['language_label'])) {
                $labels[] = trim((string) $price['language_label']);
            }
        }

        foreach ($entityManager->getRepository(CardLanguagePrice::class)->findForCard($card) as $price) {
            if ($price instanceof CardLanguagePrice) {
                $labels[] = $price->getLanguageLabel();
            }
        }

        $labels = $this->normalizeLanguageLabels($labels);

        if ($labels === []) {
            $labels = ['English'];
        }

        $map = [];

        foreach ($labels as $label) {
            $map[$this->normalizeCardCommentLanguage($label)] = $label;
        }

        return $map;
    }

    /**
     * @param array<int, mixed> $languages
     * @return list<string>
     */
    private function normalizeLanguageLabels(array $languages): array
    {
        $labels = array_values(array_filter(
            array_map(static fn ($language): string => trim((string) $language), $languages),
            static fn (string $language): bool => $language !== ''
        ));

        return array_values(array_unique($labels));
    }

    /**
     * @param list<string> $labels
     * @return list<array{key: string, label: string}>
     */
    private function languageOptionsFromLabels(array $labels): array
    {
        return array_map(
            fn (string $label): array => [
                'key' => $this->normalizeCardCommentLanguage($label),
                'label' => $label,
            ],
            $labels
        );
    }

    private function normalizeCardCommentLanguage(string $label): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($label))) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'english';
    }

    private function serializeCardComments(Request $request, array $comments): array
    {
        $byParent = [];

        foreach ($comments as $comment) {
            $parentId = $comment->getParentComment()?->getId() ?? 0;
            $byParent[$parentId][] = $comment;
        }

        return $this->serializeCardCommentBranch($request, $byParent, 0);
    }

    /**
     * @param array<int, list<CardComment>> $byParent
     */
    private function serializeCardCommentBranch(Request $request, array $byParent, int $parentId): array
    {
        return array_map(fn (CardComment $comment): array => [
            'id' => (int) $comment->getId(),
            'authorName' => $comment->getAuthorName(),
            'parentAuthorName' => $comment->getParentComment()?->getAuthorName(),
            'canManage' => $this->canManageComment($request, $comment),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i'),
            'discussionType' => $comment->getDiscussionType(),
            'language' => $comment->getLanguage(),
            'children' => $this->serializeCardCommentBranch($request, $byParent, (int) $comment->getId()),
        ], $byParent[$parentId] ?? []);
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function guestCommentAlias(Request $request): string
    {
        $session = $request->getSession();
        $alias = (string) $session->get('guest_comment_alias', '');

        if ($alias !== '') {
            return $alias;
        }

        $alias = $this->translator->trans('comments.author.guest_prefix') . ' ' . random_int(1000, 999999);
        $session->set('guest_comment_alias', $alias);

        return $alias;
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     */
    private function firstValidationError(iterable $violations): ?string
    {
        foreach ($violations as $violation) {
            return $violation->getMessage();
        }

        return null;
    }

    private function positiveRequestInt(Request $request, string $key): int
    {
        $value = trim((string) $request->request->get($key, ''));

        if ($value === '') {
            return 0;
        }

        return ctype_digit($value) ? max(0, (int) $value) : 0;
    }

    /**
     * @param list<BlogComment> $comments
     * @return list<array{id: int, authorName: string, content: string, createdAt: string, children: list<array{id: int, authorName: string, content: string, createdAt: string, children: list<mixed>}>}>
     */
    private function buildBlogCommentTree(Request $request, array $comments): array
    {
        $byParent = [];

        foreach ($comments as $comment) {
            $parentId = $comment->getParentComment()?->getId() ?? 0;
            $byParent[$parentId][] = $comment;
        }

        return $this->commentBranch($request, $byParent, 0);
    }

    /**
     * @param array<int, list<BlogComment>> $byParent
     * @return list<array{id: int, authorName: string, content: string, createdAt: string, children: list<mixed>}>
     */
    private function commentBranch(Request $request, array $byParent, int $parentId): array
    {
        $branch = [];

        foreach ($byParent[$parentId] ?? [] as $comment) {
            $branch[] = [
                'id' => (int) $comment->getId(),
                'authorName' => $comment->getAuthorName(),
                'parentAuthorName' => $comment->getParentComment()?->getAuthorName(),
                'canManage' => $this->canManageComment($request, $comment),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i'),
                'children' => $this->commentBranch($request, $byParent, (int) $comment->getId()),
            ];
        }

        return $branch;
    }

    /**
     * @param array<int, BlogTopic> $topics
     * @return list<array{slug: string, title: string, description: string, button: string}>
     */
    private function localizedTopics(array $topics): array
    {
        return array_map(fn (BlogTopic $topic): array => $this->localizedTopic($topic), $topics);
    }

    /**
     * @return array{slug: string, title: string, description: string, button: string}
     */
    private function localizedTopic(BlogTopic $topic): array
    {
        $definition = $this->topicDefinition($topic->getSlug());

        if ($definition === null) {
            return [
                'slug' => $topic->getSlug(),
                'title' => $topic->getTitle(),
                'description' => $topic->getDescription(),
                'button' => $this->translator->trans('blog.topic.default.button'),
            ];
        }

        return [
            'slug' => $topic->getSlug(),
            'title' => $this->translator->trans($definition['title_key']),
            'description' => $this->translator->trans($definition['description_key']),
            'button' => $this->translator->trans($definition['button_key']),
        ];
    }

    private function topicDefinition(string $slug): ?array
    {
        foreach (self::BLOG_TOPICS as $definition) {
            if ($definition['slug'] === $slug) {
                return $definition;
            }
        }

        return null;
    }
}
