<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Uom;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [

            ['code'=>'KG','name'=>'Kilogram','symbol'=>'Kg'],

            ['code'=>'TON','name'=>'Ton','symbol'=>'Ton'],

            ['code'=>'SAK','name'=>'Sak','symbol'=>'Sak'],

            ['code'=>'BOX','name'=>'Box','symbol'=>'Box'],

            ['code'=>'BOTOL','name'=>'Botol','symbol'=>'Btl'],

            ['code'=>'TRAY','name'=>'Tray','symbol'=>'Tray'],

            ['code'=>'EKOR','name'=>'Ekor','symbol'=>'Ekor'],

            ['code'=>'PCS','name'=>'Pieces','symbol'=>'Pcs'],

        ];

        foreach($uoms as $uom)
        {
            Uom::firstOrCreate(
                ['code'=>$uom['code']],
                $uom
            );
        }
    }
}