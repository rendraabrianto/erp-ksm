<?php

namespace App\Repositories\Eloquent;

use App\Models\Journal;
use App\Repositories\Contracts\JournalRepositoryInterface;

class JournalRepository implements JournalRepositoryInterface
{
    public function create(array $data): Journal
    {
        return Journal::create($data);
    }

    public function find(int $id): ?Journal
    {
        return Journal::with([
            'details.account',
            'creator',
        ])->find($id);
    }
}