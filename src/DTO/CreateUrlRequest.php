<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUrlRequest
{
    #[Assert\NotBlank]
    #[Assert\Url]
    public string $originalUrl;

    #[Assert\GreaterThan('now', message: 'La date d\'expiration doit être dans le futur.')]
    public ?\DateTimeImmutable $expiresAt = null;
}
