<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Imports\AkulakuImport;
use App\Jobs\ProcessAkulakuImport;
use App\Models\Campaign;
use App\Models\Nasbah;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::withCount('nasbahs')->paginate(10);

        return Inertia::render('campaign/index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function showUploadForm()
    {
        return Inertia::render('campaign/upload');
    }

    public function upload(Request $request)
    {
    $request->validate([
        'campaign_name' => 'required|string',
        'product_type' => 'required|string',
        'file' => 'required|file',
    ]);

    $path = $request->file('file')->store('campaign_files');

    $campaign = Campaign::create([
        'campaign_name' => $request->campaign_name,
        'product_type' => $request->product_type,
        'dialing_type' => 'predictive',
        'created_by' => auth()->user()->name,
        'file_path' => $path,
    ]);

    

     Log::info('Upload Debug', [
    'mime' => $request->file('file')->getMimeType(),
    'ext' => $request->file('file')->getClientOriginalExtension(),
]);

    Log::info('✅ Campaign file stored', ['path' => $path]);

    // Pastikan file-nya benar-benar tersimpan
    if (!Storage::exists($path)) {
        Log::error('❌ FILE BELUM ADA DI STORAGE!', ['path' => $path]);
    }


    switch ($request->product_type) {
        case 'akulaku':  
            ProcessAkulakuImport::dispatch($path, $campaign->id);
            break;
        case 'shopee':
            Excel::import(new ShopeeImport($campaign->id), $request->file('file'));
            break;
        default:
            return redirect()->back()->withErrors(['product_type' => 'Tipe produk tidak dikenali.']);
    }

        return back()->with('success', 'Campaign uploaded successfully.');
}



    public function routeToAgent()
    {
        $nasabah = Nasbah::where('is_called', false)->first();

        if (!$nasabah) {
             return response()->json(['message' => 'Tidak ada nasabah tersedia'], 404);
            }

        $agent = Agent::where('status', 'idle')->inRandomOrder()->first();

        if (!$agent) {
            return response()->json(['message' => 'Tidak ada agent tersedia'], 404);
        }

        $callerId = CallerId::where('is_active', true)->inRandomOrder()->first();

        $call = Call::create([
            'nasabah_id' => $nasabah->id,
            'agent_id' => $agent->id,
            'caller_id' => $callerId->id,
            'campaign_id' => $nasabah->campaign_id,
            'status' => 'ringing',
        ]);

        $nasabah->update(['is_called' => true]);
        $agent->update(['status' => 'busy']);

        event(new CallRouted($agent, $nasabah)); // step 5 nanti

        return response()->json(['message' => 'Call routed', 'call_id' => $call->id]);
    }
}
