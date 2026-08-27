<?php

namespace App\Repositories\Eloquent;

use App\Models\AccountPayable;
use App\Repositories\Contracts\AccountPayableRepositoryInterface;

class AccountPayableRepository
implements AccountPayableRepositoryInterface
{
    public function create(
        array $data
    ) {
        return AccountPayable::create(
            $data
        );
    }

    public function find(
        int $id
    ) {
        return AccountPayable::findOrFail(
            $id
        );
    }

    public function update(
        int $id,
        array $data
    ) {
        $ap = $this->find($id);

        $ap->update($data);

        return $ap;
    }

    public function paginate(
        int $perPage = 15
    ) {
        return AccountPayable::paginate(
            $perPage
        );
    }
}