<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Url;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UrlControllerTest extends WebTestCase
{
    public function testCreateReturns201(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/urls',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['originalUrl' => 'https://example.com']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('shortCode', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('https://example.com', $data['originalUrl']);
    }

    public function testCreateWithInvalidUrlReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/urls',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['originalUrl' => 'not-a-valid-url']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testShowReturns200(): void
    {
        $client = static::createClient();

        $url = new Url();
        $url->setOriginalUrl('https://example.com')
            ->setShortCode('test01')
            ->setIsActive(true);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($url);
        $em->flush();

        $client->request('GET', '/api/urls/' . $url->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('https://example.com', $data['originalUrl']);
        $this->assertSame('test01', $data['shortCode']);
    }
}
