<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function show(
        Journal $journal
    ): View {

        $journal->load([
            'details.account',
        ]);

        return view(
            'erp.accounting.journals.show',
            compact('journal')
        );
    }
}