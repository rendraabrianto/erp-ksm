<?php

namespace App\Repositories\Contracts;

interface CustomerReceiptRepositoryInterface
{
    public function create(
        array $data
    );
}