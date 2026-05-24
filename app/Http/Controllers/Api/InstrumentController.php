<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterInstrument;
use Illuminate\Http\Request;

class InstrumentController extends Controller
{
    public function index()
    {
        $instruments = MasterInstrument::orderBy('lab_id')->paginate(20);
        return response()->json($instruments);
    }

    public function show($id)
    {
        return response()->json(MasterInstrument::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lab_id'              => 'required|unique:master_instruments',
            'description'         => 'required|string',
            'make'                => 'required|string',
            'model'               => 'required|string',
            'serial_no'           => 'required|string',
            'instrument_type'     => 'required|string',
            'range_from'          => 'required|numeric',
            'range_to'            => 'required|numeric',
            'unit'                => 'required|string',
            'accuracy_pct_fs'     => 'required|numeric',
            'mpe'                 => 'required|numeric',
            'resolution_positive' => 'required|numeric',
            'resolution_negative' => 'required|numeric',
            'accountable_drift'   => 'required|numeric',
            'last_calibrated_at'  => 'required|date',
            'next_due_at'         => 'required|date',
            'poly_coefficients'             => 'nullable|array',
            'poly_uncertainty_coefficients' => 'nullable|array',
            'max_correction_residual'       => 'nullable|numeric',
            'max_uncertainty_residual'      => 'nullable|numeric',
        ]);

        $instrument = MasterInstrument::create($data);
        return response()->json($instrument, 201);
    }

    public function update(Request $request, $id)
    {
        $instrument = MasterInstrument::findOrFail($id);
        $instrument->update($request->all());
        return response()->json($instrument);
    }

    public function destroy($id)
    {
        MasterInstrument::findOrFail($id)->delete();
        return response()->json(['message' => 'Instrument deleted.']);
    }

    public function dueSoon()
    {
        $instruments = MasterInstrument::whereDate('next_due_at', '<=', now()->addDays(60))
            ->orderBy('next_due_at')
            ->get();
        return response()->json($instruments);
    }
}
