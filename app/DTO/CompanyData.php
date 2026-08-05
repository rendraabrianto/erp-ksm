<?php

namespace App\DTO;

class CompanyData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public bool $is_active,
    ) {}
}