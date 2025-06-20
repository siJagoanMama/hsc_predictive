<?php

namespace Database\Seeders;

use App\Models\CallerId;
use Illuminate\Database\Seeder;

class CallerIdSeeder extends Seeder
{
    public function run(): void
    {
        $callerIds = [
            '6281234567890',
            '6281234567891', 
            '6281234567892',
            '6281234567893',
            '6281234567894',
        ];

        foreach ($callerIds as $number) {
            CallerId::updateOrCreate(
                ['number' => $number],
                ['is_active' => true]
            );
        }
    }
}