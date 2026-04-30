<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/about', name: 'site_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->renderPage(
            'site.about.title',
            'site.about.intro',
            [
                [
                    'title' => 'site.about.section_1.title',
                    'content' => ['site.about.section_1.paragraph_1', 'site.about.section_1.paragraph_2'],
                ],
                [
                    'title' => 'site.about.section_2.title',
                    'content' => ['site.about.section_2.paragraph_1', 'site.about.section_2.paragraph_2'],
                ],
                [
                    'title' => 'site.about.section_3.title',
                    'content' => ['site.about.section_3.paragraph_1', 'site.about.section_3.paragraph_2'],
                ],
                [
                    'title' => 'site.about.section_4.title',
                    'content' => ['site.about.section_4.paragraph_1', 'site.about.section_4.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/contact', name: 'site_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->renderPage(
            'site.contact.title',
            'site.contact.intro',
            [
                [
                    'title' => 'site.contact.section_1.title',
                    'content' => ['site.contact.section_1.paragraph_1', 'site.contact.section_1.paragraph_2'],
                ],
                [
                    'title' => 'site.contact.section_2.title',
                    'content' => ['site.contact.section_2.paragraph_1', 'site.contact.section_2.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/legal-notice', name: 'site_legal_notice', methods: ['GET'])]
    public function legalNotice(): Response
    {
        return $this->renderPage(
            'site.legal.title',
            'site.legal.intro',
            [
                [
                    'title' => 'site.legal.section_1.title',
                    'content' => ['site.legal.section_1.paragraph_1', 'site.legal.section_1.paragraph_2', 'site.legal.section_1.paragraph_3'],
                ],
                [
                    'title' => 'site.legal.section_2.title',
                    'content' => ['site.legal.section_2.paragraph_1', 'site.legal.section_2.paragraph_2'],
                ],
                [
                    'title' => 'site.legal.section_3.title',
                    'content' => ['site.legal.section_3.paragraph_1', 'site.legal.section_3.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/privacy-policy', name: 'site_privacy_policy', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->renderPage(
            'site.privacy.title',
            'site.privacy.intro',
            [
                [
                    'title' => 'site.privacy.section_1.title',
                    'content' => ['site.privacy.section_1.paragraph_1', 'site.privacy.section_1.paragraph_2'],
                ],
                [
                    'title' => 'site.privacy.section_2.title',
                    'content' => ['site.privacy.section_2.paragraph_1', 'site.privacy.section_2.paragraph_2'],
                ],
                [
                    'title' => 'site.privacy.section_3.title',
                    'content' => ['site.privacy.section_3.paragraph_1', 'site.privacy.section_3.paragraph_2'],
                ],
                [
                    'title' => 'site.privacy.section_4.title',
                    'content' => ['site.privacy.section_4.paragraph_1', 'site.privacy.section_4.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/terms-of-use', name: 'site_terms_of_use', methods: ['GET'])]
    public function termsOfUse(): Response
    {
        return $this->renderPage(
            'site.terms.title',
            'site.terms.intro',
            [
                [
                    'title' => 'site.terms.section_1.title',
                    'content' => ['site.terms.section_1.paragraph_1', 'site.terms.section_1.paragraph_2'],
                ],
                [
                    'title' => 'site.terms.section_2.title',
                    'content' => ['site.terms.section_2.paragraph_1', 'site.terms.section_2.paragraph_2'],
                ],
                [
                    'title' => 'site.terms.section_3.title',
                    'content' => ['site.terms.section_3.paragraph_1', 'site.terms.section_3.paragraph_2'],
                ],
                [
                    'title' => 'site.terms.section_4.title',
                    'content' => ['site.terms.section_4.paragraph_1', 'site.terms.section_4.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/cookies', name: 'site_cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->renderPage(
            'site.cookies.title',
            'site.cookies.intro',
            [
                [
                    'title' => 'site.cookies.section_1.title',
                    'content' => ['site.cookies.section_1.paragraph_1', 'site.cookies.section_1.paragraph_2'],
                ],
                [
                    'title' => 'site.cookies.section_2.title',
                    'content' => ['site.cookies.section_2.paragraph_1', 'site.cookies.section_2.paragraph_2'],
                ],
                [
                    'title' => 'site.cookies.section_3.title',
                    'content' => ['site.cookies.section_3.paragraph_1', 'site.cookies.section_3.paragraph_2'],
                ],
            ],
        );
    }

    #[Route('/language/{locale}', name: 'site_switch_locale', requirements: ['locale' => 'en|fr'], methods: ['GET'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);

        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }

    private function renderPage(string $titleKey, string $introKey, array $sections): Response
    {
        return $this->render('site/page.html.twig', [
            'pageTitle' => $this->translator->trans($titleKey),
            'pageIntro' => $this->translator->trans($introKey),
            'sections' => array_map(function (array $section): array {
                return [
                    'title' => $this->translator->trans($section['title']),
                    'content' => array_map(
                        fn (string $paragraphKey): string => $this->translator->trans($paragraphKey),
                        $section['content']
                    ),
                ];
            }, $sections),
        ]);
    }
}
