<?php

namespace App\Jobs;

use App\Imports\AkulakuImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\CampaignImportFinished;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ProcessAkulakuImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $filePath;
    public $campaignId; 

    public function __construct($filePath,$campaignId)
    {
        $this->filePath = $filePath;
        $this->campaignId = $campaignId;   
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
        $fullPath = Storage::path($this->filePath);

        Log::info('🎯 Import running', [
            'campaign_id' => $this->campaignId,
            'file_path' => $this->filePath,
        ]);
        
        Log::info('📂 Full file path', ['path' => $fullPath]);

        Excel::import(new AkulakuImport($this->campaignId), $fullPath);
        event(new CampaignImportFinished($this->campaignId));

            Log::info('✅ Import selesai');
        } catch (\Exception $e) {
            Log::error('❌ Gagal import campaign', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
