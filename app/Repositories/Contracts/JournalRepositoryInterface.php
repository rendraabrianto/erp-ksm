<?php

namespace App\Repositories\Contracts;

use App\Models\Journal;

interface JournalRepositoryInterface
{
    public function create(array $data): Journal;

    public function find(int $id): ?Journal;
}