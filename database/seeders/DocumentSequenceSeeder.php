<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentSequence;

class DocumentSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $data = [

            [
                'document_type' => 'JV',
                'prefix' => 'JV',
                'description' => 'Journal Voucher',
            ],

            [
                'document_type' => 'PO',
                'prefix' => 'PO',
                'description' => 'Purchase Order',
            ],

            [
                'document_type' => 'GR',
                'prefix' => 'GR',
                'description' => 'Goods Receipt',
            ],

            [
                'document_type' => 'PR',
                'prefix' => 'PR',
                'description' => 'Purchase Request',
            ],

            [
                'document_type' => 'SO',
                'prefix' => 'SO',
                'description' => 'Sales Order',
            ],

            [
                'document_type' => 'DO',
                'prefix' => 'DO',
                'description' => 'Delivery Order',
            ],

            [
                'document_type' => 'INV',
                'prefix' => 'INV',
                'description' => 'Invoice',
            ],

        ];

        foreach ($data as $row) {

            DocumentSequence::updateOrCreate(
                [
                    'document_type' => $row['document_type']
                ],
                $row
            );
        }
    }
}