<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateUrlRequest;
use App\Entity\Url;
use App\Repository\UrlRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UrlService
{
    public function __construct(
        private readonly UrlRepository $urlRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function create(CreateUrlRequest $dto): Url
    {
        $url = new Url();
        $url->setOriginalUrl($dto->originalUrl)
            ->setShortCode($this->generateUniqueShortCode())
            ->setExpiresAt($dto->expiresAt);

        $this->entityManager->persist($url);
        $this->entityManager->flush();

        return $url;
    }

    public function resolve(string $shortCode): Url
    {
        $url = $this->urlRepository->findByShortCode($shortCode);

        if ($url === null) {
            throw new NotFoundHttpException(sprintf('Short code "%s" not found.', $shortCode));
        }

        if (!$url->isActive()) {
            throw new NotFoundHttpException(sprintf('Short code "%s" is inactive.', $shortCode));
        }

        if ($url->getExpiresAt() !== null && $url->getExpiresAt() < new \DateTimeImmutable()) {
            throw new NotFoundHttpException(sprintf('Short code "%s" has expired.', $shortCode));
        }

        $url->setClickCount($url->getClickCount() + 1);
        $this->entityManager->flush();

        return $url;
    }

    private function generateUniqueShortCode(): string
    {
        do {
            $code = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 6);
        } while ($this->urlRepository->findByShortCode($code) !== null);

        return $code;
    }
}
