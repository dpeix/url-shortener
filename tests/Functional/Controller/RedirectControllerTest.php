<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Url;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RedirectControllerTest extends WebTestCase
{
    public function testRedirectReturns301(): void
    {
        $client = static::createClient();

        $url = new Url();
        $url->setOriginalUrl('https://example.com')
            ->setShortCode('redir1')
            ->setIsActive(true);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($url);
        $em->flush();

        $client->request('GET', '/redir1');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('https://example.com');
    }

    public function testExpiredShortCodeReturns404(): void
    {
        $client = static::createClient();

        $url = new Url();
        $url->setOriginalUrl('https://example.com')
            ->setShortCode('expir1')
            ->setIsActive(true)
            ->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($url);
        $em->flush();

        $client->request('GET', '/expir1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUnknownShortCodeReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/doesnotexist');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
