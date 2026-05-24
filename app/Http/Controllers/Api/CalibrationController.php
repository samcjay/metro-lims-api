<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalibrationResult;
use App\Services\Engine\CalibrationEngine;
use Illuminate\Http\Request;

class CalibrationController extends Controller
{
    public function __construct(private CalibrationEngine $engine) {}

    public function show($id)
    {
        $result = CalibrationResult::with([
            'template.fields',
            'masterInstrument',
            'dataPoints',
            'calibrationJob',
            'jobInstruments.instrument',
        ])->findOrFail($id);

        return response()->json($this->formatResult($result));
    }

    public function worksheet($id)
    {
        $result = CalibrationResult::with(['template.fields', 'masterInstrument'])->findOrFail($id);
        $mi     = $result->masterInstrument;

        return response()->json([
            'result' => [
                'id'              => $result->id,
                'uuc_description' => $result->uuc_description,
                'uuc_serial_no'   => $result->uuc_serial_no,
                'uuc_unit'        => $result->uuc_unit,
                'status'          => $result->status,
                'is_locked'       => $result->isLocked(),
                'field_values'    => $result->field_values ?? [],
                'template_name'   => $result->template?->name,
                'template'        => $result->template ? [
                    'max_test_points'   => $result->template->max_test_points,
                    'cycles'            => $result->template->cycles,
                    'has_before_after'  => $result->template->has_before_after,
                    'has_head_correction' => $result->template->has_head_correction,
                    'unit'              => $result->template->unit,
                    'fields'            => $result->template->fields,
                ] : null,
            ],
            'instrument' => $mi ? [
                'lab_id'          => $mi->lab_id,
                'description'     => $mi->description,
                'make'            => $mi->make,
                'model'           => $mi->model,
                'range_from'      => $mi->range_from,
                'range_to'        => $mi->range_to,
                'unit'            => $mi->unit,
                'mpe'             => $mi->mpe,
                'next_due_at'     => $mi->next_due_at?->format('d M Y'),
                'overdue'         => $mi->next_due_at < now(),
            ] : null,
        ]);
    }

    public function saveWorksheet(Request $request, $id)
    {
        $result = CalibrationResult::findOrFail($id);

        if ($result->isLocked()) {
            return response()->json(['message' => 'This result is signed and cannot be edited.'], 422);
        }

        $result->update([
            'field_values' => $request->input('fields', []),
            'status'       => 'draft',
        ]);

        return response()->json(['message' => 'Worksheet saved.', 'field_values' => $result->field_values]);
    }

    public function calculate($id)
    {
        $result = CalibrationResult::findOrFail($id);

        if ($result->isLocked()) {
            return response()->json(['message' => 'This result is signed and cannot be recalculated.'], 422);
        }

        try {
            $this->engine->calculate($result);
            $result->refresh()->load(['dataPoints', 'template']);
            return response()->json([
                'message'     => 'Calculation complete.',
                'status'      => $result->status,
                'data_points' => $result->dataPoints,
                'snapshot'    => $result->result_snapshot,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Calculation failed: ' . $e->getMessage()], 500);
        }
    }

    public function review($id)
    {
        $result = CalibrationResult::with(['template', 'masterInstrument', 'dataPoints', 'calibrationJob'])
            ->findOrFail($id);

        return response()->json($this->formatResult($result));
    }

    private function formatResult(CalibrationResult $result): array
    {
        return [
            'id'              => $result->id,
            'status'          => $result->status,
            'is_locked'       => $result->isLocked(),
            'uuc_description' => $result->uuc_description,
            'uuc_make_model'  => $result->uuc_make_model,
            'uuc_serial_no'   => $result->uuc_serial_no,
            'uuc_range'       => $result->uuc_range,
            'uuc_unit'        => $result->uuc_unit,
            'field_values'    => $result->field_values ?? [],
            'result_snapshot' => $result->result_snapshot,
            'calculated_at'   => $result->calculated_at?->toISOString(),
            'signed_at'       => $result->signed_at?->toISOString(),
            'authorized_at'   => $result->authorized_at?->toISOString(),
            'template'        => $result->template ? [
                'name'   => $result->template->name,
                'unit'   => $result->template->unit,
                'fields' => $result->relationLoaded('template') ? $result->template->fields : [],
            ] : null,
            'master_instrument' => $result->masterInstrument,
            'data_points'       => $result->dataPoints,
            'calibration_job'   => $result->calibrationJob,
        ];
    }
}
