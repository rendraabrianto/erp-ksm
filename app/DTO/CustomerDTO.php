<?php

namespace App\DTO;

class CustomerDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public float $creditLimit,
        public int $creditDays,
    ) {}
}