<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateUrlRequest;
use App\Entity\Url;
use App\Repository\Interface\UrlRepositoryInterface;
use App\Service\UrlService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UrlServiceTest extends TestCase
{
    public function testCreateReturnsUrl(): void
    {
        $repository = $this->createStub(UrlRepositoryInterface::class);
        $repository->method('findByShortCode')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new UrlService($repository, $entityManager);

        $dto = new CreateUrlRequest();
        $dto->originalUrl = 'https://example.com';

        $url = $service->create($dto);

        $this->assertSame('https://example.com', $url->getOriginalUrl());
        $this->assertSame(6, strlen($url->getShortCode()));
    }

    public function testResolveIncrementsClickCount(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(true);

        $repository = $this->createStub(UrlRepositoryInterface::class);
        $repository->method('findByShortCode')->willReturn($url);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $service = new UrlService($repository, $entityManager);

        $resolved = $service->resolve('abc123');

        $this->assertSame(1, $resolved->getClickCount());
    }

    public function testResolveThrowsWhenNotFound(): void
    {
        $repository = $this->createStub(UrlRepositoryInterface::class);
        $repository->method('findByShortCode')->willReturn(null);

        $service = new UrlService($repository, $this->createStub(EntityManagerInterface::class));

        $this->expectException(NotFoundHttpException::class);

        $service->resolve('unknown');
    }

    public function testResolveThrowsWhenExpired(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(true)
            ->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $repository = $this->createStub(UrlRepositoryInterface::class);
        $repository->method('findByShortCode')->willReturn($url);

        $service = new UrlService($repository, $this->createStub(EntityManagerInterface::class));

        $this->expectException(NotFoundHttpException::class);

        $service->resolve('abc123');
    }

    public function testResolveThrowsWhenInactive(): void
    {
        $url = (new Url())
            ->setOriginalUrl('https://example.com')
            ->setShortCode('abc123')
            ->setIsActive(false);

        $repository = $this->createStub(UrlRepositoryInterface::class);
        $repository->method('findByShortCode')->willReturn($url);

        $service = new UrlService($repository, $this->createStub(EntityManagerInterface::class));

        $this->expectException(NotFoundHttpException::class);

        $service->resolve('abc123');
    }
}
