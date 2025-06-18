<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Log;

class AkulakuImport implements ToCollection
{
    /**
    * @param Collection $collection
    */

    public function __construct($campaignId)
    {
        Log::info('📦 Constructor OK', ['id' => $campaignId]);
        $this->campaignId = $campaignId;
    }

    public function collection(Collection $collection)
    {
        Log::info('📊 Rows count', ['rows' => $rows->count()]);
        foreach ($rows as $row) {
            Log::info('➡️ Row', $row->toArray());
        }
    }
}
