<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Jobs\PredictiveDialerJob;
use App\Events\CampaignStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PredictiveDialerController extends Controller
{
    public function start(Campaign $campaign): JsonResponse
    {
        if ($campaign->status === 'running') {
            return response()->json(['message' => 'Campaign is already running'], 400);
        }

        $campaign->update([
            'status' => 'running',
            'is_active' => true,
            'started_at' => now(),
            'stopped_at' => null,
        ]);

        // Dispatch the predictive dialer job
        PredictiveDialerJob::dispatch($campaign);

        // Broadcast status change
        event(new CampaignStatusChanged($campaign));

        Log::info("🚀 Predictive dialer started for campaign: {$campaign->campaign_name}");

        return response()->json([
            'message' => 'Predictive dialer started successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function stop(Campaign $campaign): JsonResponse
    {
        $campaign->update([
            'status' => 'stopped',
            'is_active' => false,
            'stopped_at' => now(),
        ]);

        // Broadcast status change
        event(new CampaignStatusChanged($campaign));

        Log::info("🛑 Predictive dialer stopped for campaign: {$campaign->campaign_name}");

        return response()->json([
            'message' => 'Predictive dialer stopped successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function pause(Campaign $campaign): JsonResponse
    {
        $campaign->update([
            'status' => 'paused',
            'is_active' => false,
        ]);

        // Broadcast status change
        event(new CampaignStatusChanged($campaign));

        Log::info("⏸️ Predictive dialer paused for campaign: {$campaign->campaign_name}");

        return response()->json([
            'message' => 'Predictive dialer paused successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function resume(Campaign $campaign): JsonResponse
    {
        if ($campaign->status !== 'paused') {
            return response()->json(['message' => 'Campaign is not paused'], 400);
        }

        $campaign->update([
            'status' => 'running',
            'is_active' => true,
        ]);

        // Restart the predictive dialer job
        PredictiveDialerJob::dispatch($campaign);

        // Broadcast status change
        event(new CampaignStatusChanged($campaign));

        Log::info("▶️ Predictive dialer resumed for campaign: {$campaign->campaign_name}");

        return response()->json([
            'message' => 'Predictive dialer resumed successfully',
            'campaign' => $campaign->fresh(),
        ]);
    }

    public function status(Campaign $campaign): JsonResponse
    {
        $stats = [
            'total_numbers' => $campaign->nasbahs()->count(),
            'called_numbers' => $campaign->nasbahs()->where('is_called', true)->count(),
            'remaining_numbers' => $campaign->nasbahs()->where('is_called', false)->count(),
            'total_calls' => $campaign->calls()->count(),
            'answered_calls' => $campaign->calls()->where('status', 'answered')->count(),
            'failed_calls' => $campaign->calls()->where('status', 'failed')->count(),
            'busy_calls' => $campaign->calls()->where('status', 'busy')->count(),
            'no_answer_calls' => $campaign->calls()->where('status', 'no_answer')->count(),
        ];

        return response()->json([
            'campaign' => $campaign,
            'stats' => $stats,
        ]);
    }
}