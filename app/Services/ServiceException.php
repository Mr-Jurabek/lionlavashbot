<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Biznes qoidasi buzilganda tashlanadi. $reason — tarjima kaliti uchun qisqa kod
 * (masalan, "cart_empty" → lang faylida "error.cart_empty").
 */
final class ServiceException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
