<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UrlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly UrlRepository $urlRepository,
    ) {}

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('url/index.html.twig', [
            'urls' => $this->urlRepository->findBy([], ['id' => 'DESC']),
        ]);
    }
}
