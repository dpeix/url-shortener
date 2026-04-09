<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UrlService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RedirectController extends AbstractController
{
    public function __construct(
        private readonly UrlService $urlService,
    ) {}

    #[Route('/{shortCode}', name: 'app_redirect', methods: ['GET'])]
    public function resolveShortUrl(string $shortCode): RedirectResponse|JsonResponse
    {
        try {
            $url = $this->urlService->resolve($shortCode);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new RedirectResponse($url->getOriginalUrl(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
