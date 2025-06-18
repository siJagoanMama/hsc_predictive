<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CallReport;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class CallReportController extends Controller
{
    public function index(Request $request)
    {
        $query = CallReport::with(['campaign', 'agent'])
            ->orderBy('date', 'desc');

        if ($request->campaign_id) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->agent_id) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        $reports = $query->paginate(15);

        $campaigns = Campaign::select('id', 'campaign_name')->get();

        return Inertia::render('reports/call-reports', [
            'reports' => $reports,
            'campaigns' => $campaigns,
            'filters' => $request->only(['campaign_id', 'agent_id', 'date_from', 'date_to']),
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'date' => 'required|date',
        ]);

        $campaign = Campaign::findOrFail($request->campaign_id);
        $date = Carbon::parse($request->date);

        // Get all calls for the campaign on the specified date
        $calls = Call::where('campaign_id', $campaign->id)
            ->whereDate('call_started_at', $date)
            ->get();

        // Group by agent
        $agentStats = $calls->groupBy('agent_id')->map(function ($agentCalls) {
            $totalCalls = $agentCalls->count();
            $answeredCalls = $agentCalls->where('status', 'answered')->count();
            $failedCalls = $agentCalls->where('status', 'failed')->count();
            $busyCalls = $agentCalls->where('status', 'busy')->count();
            $noAnswerCalls = $agentCalls->where('status', 'no_answer')->count();

            $totalTalkTime = $agentCalls->where('status', 'answered')->sum('duration') ?? 0;
            $averageTalkTime = $answeredCalls > 0 ? $totalTalkTime / $answeredCalls : 0;

            return [
                'total_calls' => $totalCalls,
                'answered_calls' => $answeredCalls,
                'failed_calls' => $failedCalls,
                'busy_calls' => $busyCalls,
                'no_answer_calls' => $noAnswerCalls,
                'total_talk_time' => $totalTalkTime,
                'average_talk_time' => $averageTalkTime,
            ];
        });

        // Save or update reports
        foreach ($agentStats as $agentId => $stats) {
            CallReport::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'agent_id' => $agentId,
                    'date' => $date->toDateString(),
                ],
                $stats
            );
        }

        return response()->json(['message' => 'Reports generated successfully']);
    }

    public function dashboard()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        $todayStats = $this->getStatsForPeriod($today, $today);
        $weekStats = $this->getStatsForPeriod($thisWeek, $today);
        $monthStats = $this->getStatsForPeriod($thisMonth, $today);

        $recentReports = CallReport::with(['campaign', 'agent'])
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('reports/dashboard', [
            'todayStats' => $todayStats,
            'weekStats' => $weekStats,
            'monthStats' => $monthStats,
            'recentReports' => $recentReports,
        ]);
    }

    private function getStatsForPeriod($startDate, $endDate)
    {
        return CallReport::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                SUM(total_calls) as total_calls,
                SUM(answered_calls) as answered_calls,
                SUM(failed_calls) as failed_calls,
                SUM(busy_calls) as busy_calls,
                SUM(no_answer_calls) as no_answer_calls,
                SUM(total_talk_time) as total_talk_time,
                AVG(average_talk_time) as average_talk_time
            ')
            ->first();
    }
}