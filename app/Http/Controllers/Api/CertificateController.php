<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibrationResult;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index()
    {
        $results = CalibrationResult::where('status', 'released')
            ->with(['template', 'masterInstrument', 'calibrationJob'])
            ->latest()
            ->paginate(20)
            ->through(fn($r) => [
                'id'              => $r->id,
                'certificate_no'  => $r->certificate_no,
                'uuc_description' => $r->uuc_description,
                'uuc_serial_no'   => $r->uuc_serial_no,
                'customer_name'   => $r->calibrationJob?->customer_name,
                'template_name'   => $r->template?->name,
                'authorized_at'   => $r->authorized_at?->toISOString(),
            ]);

        return response()->json($results);
    }

    public function show($id)
    {
        $result = CalibrationResult::with(['template', 'masterInstrument', 'dataPoints', 'calibrationJob'])
            ->findOrFail($id);

        return response()->json($result);
    }

    public function send(Request $request, $id)
    {
        // TODO: email PDF to customer
        return response()->json(['message' => 'Certificate sent to customer.']);
    }
}
