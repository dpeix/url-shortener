<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateUrlRequest;
use App\Entity\Url;
use App\Repository\UrlRepository;
use App\Service\UrlService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/urls', name: 'api_urls_')]
final class UrlController extends AbstractController
{
    public function __construct(
        private readonly UrlService $urlService,
        private readonly UrlRepository $urlRepository,
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateUrlRequest $dto): JsonResponse
    {
        $url = $this->urlService->create($dto);

        return $this->json($this->serializeForCreate($url), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Url $url): JsonResponse
    {
        return $this->json($this->serializeForShow($url));
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $urls = $this->urlRepository->findAll();

        return $this->json(array_map($this->serializeForShow(...), $urls));
    }

    private function serializeForCreate(Url $url): array
    {
        return [
            'id' => $url->getId(),
            'originalUrl' => $url->getOriginalUrl(),
            'shortCode' => $url->getShortCode(),
            'createdAt' => $url->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $url->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeForShow(Url $url): array
    {
        return [
            'id' => $url->getId(),
            'originalUrl' => $url->getOriginalUrl(),
            'shortCode' => $url->getShortCode(),
            'clickCount' => $url->getClickCount(),
            'createdAt' => $url->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $url->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'isActive' => $url->isActive(),
        ];
    }
}
