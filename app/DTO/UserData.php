<?php

namespace App\DTO;

class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?int $company_id,
        public ?int $branch_id,
        public bool $is_active = true,
    ) {}
}