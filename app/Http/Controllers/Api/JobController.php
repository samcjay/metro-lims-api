<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibrationJob;
use App\Models\CalibrationJobInstrument;
use App\Models\CalibrationResult;
use App\Models\MasterInstrument;
use App\Models\WorksheetTemplate;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $jobs = CalibrationResult::with(['template', 'calibrationJob'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('uuc_description', 'like', "%{$request->search}%")
                  ->orWhere('uuc_serial_no', 'like', "%{$request->search}%")
                  ->orWhere('certificate_no', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn($job) => [
                'id'              => $job->id,
                'certificate_no'  => $job->certificate_no,
                'uuc_description' => $job->uuc_description,
                'uuc_serial_no'   => $job->uuc_serial_no,
                'template_name'   => $job->template?->name,
                'customer_name'   => $job->calibrationJob?->customer_name,
                'status'          => $job->status,
                'created_at'      => $job->created_at->toISOString(),
            ]);

        return response()->json($jobs);
    }

    public function create()
    {
        return response()->json([
            'templates'        => WorksheetTemplate::where('is_active', true)->get(['id', 'name', 'instrument_type']),
            'instruments'      => MasterInstrument::where('is_active', true)->orderBy('lab_id')
                                     ->get(['id', 'lab_id', 'description', 'make', 'model', 'instrument_type']),
            'instrument_roles' => ['reference', 'dry_well', 'secondary_reference', 'bath', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'         => 'required|string|max:255',
            'customer_ref'          => 'nullable|string|max:100',
            'date_received'         => 'required|date',
            'worksheet_template_id' => 'required|exists:worksheet_templates,id',
            'uuc_description'       => 'required|string',
            'uuc_make_model'        => 'nullable|string',
            'uuc_serial_no'         => 'required|string',
            'uuc_instrument_id'     => 'nullable|string',
            'uuc_range'             => 'nullable|string',
            'uuc_unit'              => 'nullable|string',
            'job_instruments'                       => 'nullable|array',
            'job_instruments.*.master_instrument_id'=> 'required|exists:master_instruments,id',
            'job_instruments.*.role'                => 'required|string|max:50',
            'job_instruments.*.notes'               => 'nullable|string|max:255',
        ]);

        $calibrationJob = CalibrationJob::create([
            'job_no'        => CalibrationJob::nextJobNo(),
            'customer_name' => $validated['customer_name'],
            'customer_ref'  => $validated['customer_ref'] ?? null,
            'date_received' => $validated['date_received'],
            'status'        => 'pending',
        ]);

        $job = CalibrationResult::create([
            'calibration_job_id'    => $calibrationJob->id,
            'worksheet_template_id' => $validated['worksheet_template_id'],
            'master_instrument_id'  => null,
            'uuc_description'       => $validated['uuc_description'],
            'uuc_make_model'        => $validated['uuc_make_model'] ?? null,
            'uuc_serial_no'         => $validated['uuc_serial_no'],
            'uuc_instrument_id'     => $validated['uuc_instrument_id'] ?? null,
            'uuc_range'             => $validated['uuc_range'] ?? null,
            'uuc_unit'              => $validated['uuc_unit'] ?? null,
            'status'                => 'draft',
            'field_values'          => [],
        ]);

        foreach ($validated['job_instruments'] ?? [] as $ji) {
            CalibrationJobInstrument::create([
                'calibration_result_id' => $job->id,
                'master_instrument_id'  => $ji['master_instrument_id'],
                'role'                  => $ji['role'],
                'notes'                 => $ji['notes'] ?? null,
            ]);
        }

        return response()->json([
            'message'        => 'Job created successfully.',
            'calibration_id' => $job->id,
            'job_no'         => $calibrationJob->job_no,
        ], 201);
    }

    public function show($id)
    {
        $job = CalibrationResult::with([
            'masterInstrument',
            'template',
            'dataPoints',
            'calibrationJob',
            'jobInstruments.instrument',
        ])->findOrFail($id);

        return response()->json($job);
    }

    public function update(Request $request, $id)
    {
        $job = CalibrationResult::findOrFail($id);

        $validated = $request->validate([
            'uuc_description'   => 'sometimes|required|string',
            'uuc_make_model'    => 'nullable|string',
            'uuc_serial_no'     => 'sometimes|required|string',
            'uuc_instrument_id' => 'nullable|string',
            'uuc_range'         => 'nullable|string',
            'uuc_unit'          => 'nullable|string',
        ]);

        $job->update($validated);

        return response()->json(['message' => 'Job updated.', 'job' => $job]);
    }

    public function destroy($id)
    {
        $job = CalibrationResult::findOrFail($id);

        if ($job->status !== 'draft') {
            return response()->json(['message' => 'Only draft jobs can be deleted.'], 422);
        }

        $calibrationJobId = $job->calibration_job_id;
        $job->delete();

        // Clean up the parent CalibrationJob if it has no remaining results
        if ($calibrationJobId && !CalibrationResult::where('calibration_job_id', $calibrationJobId)->exists()) {
            CalibrationJob::find($calibrationJobId)?->delete();
        }

        return response()->json(['message' => 'Job deleted.']);
    }

    public function finalize($id)
    {
        $job = CalibrationResult::findOrFail($id);

        if (!in_array($job->status, ['authorized', 'metrologist_signed'])) {
            return response()->json(['message' => 'Job cannot be finalized from its current status.'], 422);
        }

        $job->update(['status' => 'finalized']);

        return response()->json(['message' => 'Job finalized.', 'status' => $job->status]);
    }

    public function syncInstruments(Request $request, $id)
    {
        $job = CalibrationResult::findOrFail($id);

        $validated = $request->validate([
            'job_instruments'                       => 'required|array',
            'job_instruments.*.master_instrument_id'=> 'required|exists:master_instruments,id',
            'job_instruments.*.role'                => 'required|string|max:50',
            'job_instruments.*.notes'               => 'nullable|string|max:255',
        ]);

        $job->jobInstruments()->delete();

        foreach ($validated['job_instruments'] as $ji) {
            CalibrationJobInstrument::create([
                'calibration_result_id' => $job->id,
                'master_instrument_id'  => $ji['master_instrument_id'],
                'role'                  => $ji['role'],
                'notes'                 => $ji['notes'] ?? null,
            ]);
        }

        $job->load('jobInstruments.instrument');

        return response()->json(['message' => 'Instruments updated.', 'job_instruments' => $job->jobInstruments]);
    }
}
