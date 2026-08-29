<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use Illuminate\Database\Seeder;

class InventoryTransferSetupSeeder extends Seeder
{
    public function run(): void
    {
        DocumentSequence::updateOrCreate(
            [
                'document_type' => 'TRF',
            ],
            [
                'prefix' => 'TRF',
                'description' => 'Inventory Transfer',
                'padding' => 5,
                'is_active' => true,
            ]
        );
    }
}