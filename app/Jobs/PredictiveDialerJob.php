<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Agent;
use App\Models\Nasbah;
use App\Models\Call;
use App\Models\CallerId;
use App\Events\CallRouted;
use App\Services\AsteriskAMIService;
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
    protected $amiService;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function handle(): void
    {
        Log::info('🎯 Predictive Dialer started for campaign: ' . $this->campaign->campaign_name);

        $this->amiService = new AsteriskAMIService();
        
        if (!$this->amiService->connect()) {
            Log::error('❌ Failed to connect to Asterisk AMI');
            $this->campaign->update(['status' => 'stopped', 'is_active' => false]);
            return;
        }

        while ($this->campaign->fresh()->is_active) {
            $this->processDialing();
            sleep(5); // Wait 5 seconds before next iteration
        }

        $this->amiService->disconnect();
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
            $this->campaign->update(['status' => 'completed', 'is_active' => false]);
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

        // Initiate call through Asterisk AMI
        $this->makeAsteriskCall($call, $nasabah, $agent, $callerId);

        // Broadcast to agent
        event(new CallRouted($agent, $nasabah));

        Log::info("📞 Call initiated: Agent {$agent->name} -> {$nasabah->phone}");
    }

    private function makeAsteriskCall(Call $call, Nasbah $nasabah, Agent $agent, CallerId $callerId)
    {
        try {
            // First, originate call to customer
            $customerChannel = config('asterisk.channels.trunk_prefix') . $nasabah->phone;
            $agentExtension = $agent->extension;
            
            $variables = [
                'CALL_ID' => $call->id,
                'CAMPAIGN_ID' => $this->campaign->id,
                'CUSTOMER_NAME' => $nasabah->name,
                'CUSTOMER_PHONE' => $nasabah->phone,
                'AGENT_ID' => $agent->id,
                'CALLERID(num)' => $callerId->number,
            ];

            // Originate call to customer, when answered, connect to agent
            $success = $this->amiService->originateCall(
                $customerChannel,
                config('asterisk.contexts.predictive'),
                $agentExtension,
                '1',
                $variables
            );

            if ($success) {
                Log::info("📞 Asterisk call initiated successfully for Call ID: {$call->id}");
                
                // Monitor call status
                $this->monitorCall($call);
            } else {
                Log::error("❌ Failed to initiate Asterisk call for Call ID: {$call->id}");
                $this->handleCallFailure($call);
            }

        } catch (\Exception $e) {
            Log::error("❌ Asterisk call error: " . $e->getMessage());
            $this->handleCallFailure($call);
        }
    }

    private function monitorCall(Call $call)
    {
        // Schedule a job to monitor call status
        dispatch(function () use ($call) {
            sleep(30); // Wait 30 seconds then check status
            
            $this->checkCallStatus($call);
        })->delay(now()->addSeconds(30));
    }

    private function checkCallStatus(Call $call)
    {
        try {
            $channels = $this->amiService->getActiveChannels();
            $callFound = false;
            
            foreach ($channels as $channel) {
                if (isset($channel['Variable']) && strpos($channel['Variable'], "CALL_ID={$call->id}") !== false) {
                    $callFound = true;
                    break;
                }
            }
            
            if (!$callFound) {
                // Call has ended, update status
                $this->finalizeCall($call);
            } else {
                // Call still active, check again later
                dispatch(function () use ($call) {
                    $this->checkCallStatus($call);
                })->delay(now()->addSeconds(10));
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Error checking call status: " . $e->getMessage());
            $this->finalizeCall($call);
        }
    }

    private function finalizeCall(Call $call)
    {
        // Determine call outcome based on duration and other factors
        $duration = 0;
        $status = 'failed';
        $disposition = 'failed';
        
        if ($call->call_started_at) {
            $duration = now()->diffInSeconds($call->call_started_at);
            
            if ($duration > 10) {
                $status = 'answered';
                $disposition = 'answered';
            } elseif ($duration > 5) {
                $status = 'no_answer';
                $disposition = 'no_answer';
            } else {
                $status = 'busy';
                $disposition = 'busy';
            }
        }

        $call->update([
            'status' => $status,
            'disposition' => $disposition,
            'call_ended_at' => now(),
            'duration' => $duration,
        ]);

        // Free up the agent
        $call->agent->update(['status' => 'idle']);

        Log::info("📞 Call finalized: {$call->id} - Status: {$status} - Duration: {$duration}s");
    }

    private function handleCallFailure(Call $call)
    {
        $call->update([
            'status' => 'failed',
            'disposition' => 'failed',
            'call_ended_at' => now(),
            'duration' => 0,
        ]);

        // Free up the agent
        $call->agent->update(['status' => 'idle']);

        Log::info("📞 Call failed: {$call->id}");
    }
}