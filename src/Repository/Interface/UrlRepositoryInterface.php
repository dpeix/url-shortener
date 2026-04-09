<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Entity\Url;

interface UrlRepositoryInterface
{
    public function findByShortCode(string $code): ?Url;

    /** @return Url[] */
    public function findExpiredUrls(): array;
}
