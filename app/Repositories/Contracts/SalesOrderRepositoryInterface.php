<?php

namespace App\Repositories\Contracts;

interface SalesOrderRepositoryInterface
{
    public function create(
        array $data
    );

    public function find(
        int $id
    );
}