<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateUrlRequest;
use App\Entity\Url;
use App\Repository\UrlRepository;
use App\Service\UrlService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UrlServiceTest extends TestCase
{
    private UrlService $service;
    private UrlRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UrlRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new UrlService($this->repository, $this->entityManager);
    }

    public function testCreateReturnsUrl(): void
    {
        $this->repository->method('findByShortCode')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $dto = new CreateUrlRequest();
        $dto->originalUrl = 'https://example.com';

        $url = $this->service->create($dto);

        $this->assertSame('https://example.com', $url->getOriginalUrl());
        $this->assertSame(6, strlen($url->getShortCode()));
    }

    public function testResolveIncrementsClickCount(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(true);

        $this->repository->method('findByShortCode')->willReturn($url);
        $this->entityManager->expects($this->once())->method('flush');

        $resolved = $this->service->resolve('abc123');

        $this->assertSame(1, $resolved->getClickCount());
    }

    public function testResolveThrowsWhenNotFound(): void
    {
        $this->repository->method('findByShortCode')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->resolve('unknown');
    }

    public function testResolveThrowsWhenExpired(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(true)
            ->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->repository->method('findByShortCode')->willReturn($url);

        $this->expectException(NotFoundHttpException::class);

        $this->service->resolve('abc123');
    }

    public function testResolveThrowsWhenInactive(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(false);

        $this->repository->method('findByShortCode')->willReturn($url);

        $this->expectException(NotFoundHttpException::class);

        $this->service->resolve('abc123');
    }
}
