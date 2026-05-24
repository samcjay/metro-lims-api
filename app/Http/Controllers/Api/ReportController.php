<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibrationResult;
use App\Models\MasterInstrument;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function jobs(Request $request)
    {
        $from = $request->get('from', now()->subMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $results = CalibrationResult::with(['template', 'masterInstrument', 'calibrationJob'])
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'certificate_no'  => $r->certificate_no,
                'uuc_description' => $r->uuc_description,
                'uuc_serial_no'   => $r->uuc_serial_no,
                'customer_name'   => $r->calibrationJob?->customer_name,
                'template_name'   => $r->template?->name,
                'status'          => $r->status,
                'created_at'      => $r->created_at->toDateString(),
            ]);

        return response()->json(['data' => $results, 'from' => $from, 'to' => $to]);
    }

    public function instruments()
    {
        $instruments = MasterInstrument::orderBy('lab_id')->get();
        return response()->json($instruments);
    }

    public function dueDates()
    {
        $instruments = MasterInstrument::orderBy('next_due_at')->get(['id', 'lab_id', 'description', 'make', 'model', 'next_due_at', 'last_calibrated_at', 'cal_frequency_years']);
        return response()->json($instruments);
    }
}
