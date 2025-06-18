<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Agent;
use App\Models\Nasbah;
use App\Models\Call;
use App\Models\CallerId;
use App\Events\CallRouted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PredictiveDialerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function handle(): void
    {
        Log::info('🎯 Predictive Dialer started for campaign: ' . $this->campaign->campaign_name);

        while ($this->campaign->fresh()->is_active) {
            $this->processDialing();
            sleep(5); // Wait 5 seconds before next iteration
        }

        Log::info('🛑 Predictive Dialer stopped for campaign: ' . $this->campaign->campaign_name);
    }

    private function processDialing()
    {
        // Get available agents
        $availableAgents = Agent::where('status', 'idle')->get();
        
        if ($availableAgents->isEmpty()) {
            Log::info('⏳ No available agents for campaign: ' . $this->campaign->campaign_name);
            return;
        }

        // Get uncalled nasabah
        $uncalledNasabah = Nasbah::where('campaign_id', $this->campaign->id)
            ->where('is_called', false)
            ->limit($availableAgents->count() * 2) // Predictive ratio 2:1
            ->get();

        if ($uncalledNasabah->isEmpty()) {
            Log::info('📞 No more numbers to call for campaign: ' . $this->campaign->campaign_name);
            $this->campaign->update(['is_active' => false]);
            return;
        }

        foreach ($uncalledNasabah as $nasabah) {
            $agent = $availableAgents->where('status', 'idle')->first();
            
            if (!$agent) {
                break; // No more available agents
            }

            $this->initiateCall($nasabah, $agent);
            $availableAgents = $availableAgents->reject(function ($a) use ($agent) {
                return $a->id === $agent->id;
            });
        }
    }

    private function initiateCall(Nasbah $nasabah, Agent $agent)
    {
        $callerId = CallerId::where('is_active', true)->inRandomOrder()->first();

        if (!$callerId) {
            Log::error('❌ No active caller ID available');
            return;
        }

        $call = Call::create([
            'campaign_id' => $this->campaign->id,
            'nasbah_id' => $nasabah->id,
            'agent_id' => $agent->id,
            'caller_id' => $callerId->id,
            'status' => 'ringing',
            'call_started_at' => now(),
        ]);

        // Update status
        $nasabah->update(['is_called' => true]);
        $agent->update(['status' => 'busy']);

        // Simulate call connection (in real implementation, this would integrate with telephony system)
        $this->simulateCallOutcome($call);

        // Broadcast to agent
        event(new CallRouted($agent, $nasabah));

        Log::info("📞 Call initiated: Agent {$agent->name} -> {$nasabah->phone}");
    }

    private function simulateCallOutcome(Call $call)
    {
        // Simulate different call outcomes
        $outcomes = ['answered', 'failed', 'busy', 'no_answer'];
        $outcome = $outcomes[array_rand($outcomes)];

        // Simulate call duration (5-30 seconds for failed calls, 30-300 seconds for answered)
        $duration = $outcome === 'answered' ? rand(30, 300) : rand(5, 30);

        dispatch(function () use ($call, $outcome, $duration) {
            sleep($duration);
            
            $call->update([
                'status' => $outcome,
                'call_ended_at' => now(),
            ]);

            // Free up the agent
            $call->agent->update(['status' => 'idle']);

            Log::info("📞 Call ended: {$call->id} - Status: {$outcome}");
        })->delay(now()->addSeconds($duration));
    }
}