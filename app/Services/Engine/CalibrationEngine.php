<?php
namespace App\Services\Engine;

use App\Models\CalibrationResult;
use Illuminate\Support\Facades\DB;

class CalibrationEngine
{
    public const ENGINE_VERSION = '2.0';

    public function __construct(
        private UncertaintyBudget  $budget,
        private CmcComparator      $cmc,
        private FormulaEvaluator   $evaluator,
    ) {}

    public function calculate(CalibrationResult $result): CalibrationResult
    {
        if ($result->isLocked()) {
            throw new \LogicException('Cannot recalculate a signed calibration result.');
        }

        $result->load(['template.formulaNodes', 'template.cmcEntries', 'template.fields', 'jobInstruments.instrument']);

        $template    = $result->template;
        $schema      = $template->reading_schema ?? [];
        if (is_string($schema)) $schema = json_decode($schema, true) ?? [];
        $fields      = $result->field_values ?? [];
        $sortedNodes = $this->evaluator->topologicalSort($template->formulaNodes);

        // Build instrument context from all job instruments by role
        $instrumentContext = $this->buildInstrumentContext($result->jobInstruments);

        $nominals   = $this->extractNominals($fields, $template);
        $testPoints = [];

        foreach ($nominals as $index => $nominal) {
            $context      = $this->buildContext($fields, $index, $schema, $template, $instrumentContext);
            $budgetResult = $this->budget->compute($sortedNodes, $context);
            $cmcResult    = $this->cmc->compare(
                $nominal,
                $budgetResult['u_expanded'],
                $template->cmcEntries,
                $context['pressure_medium'] ?? ''
            );

            $testPoints[] = [
                'index'             => $index,
                'nominal'           => $nominal,
                'unit'              => $template->unit,
                'uuc_mean'          => $budgetResult['uuc_mean'],
                'ref_mean'          => $budgetResult['ref_mean'],
                'error'             => $budgetResult['error'],
                'hysteresis'        => $budgetResult['hysteresis'],
                'repeatability'     => $budgetResult['repeatability'],
                'u_combined'        => $budgetResult['u_combined'],
                'u_expanded'        => $budgetResult['u_expanded'],
                'k'                 => $budgetResult['k'],
                'cmc_value'         => $cmcResult['cmc_value'],
                'u_final'           => $cmcResult['u_final'],
                'u_source'          => $cmcResult['source'],
                'within_mpe'        => abs($budgetResult['error']) <= ($context['mpe'] ?? PHP_INT_MAX),
                'budget_components' => $budgetResult['components'],
            ];
        }

        $snapshot = [
            'test_points'    => $testPoints,
            'computed_at'    => now()->toIso8601String(),
            'engine_version' => self::ENGINE_VERSION,
            'schema'         => $schema,
        ];

        DB::transaction(function () use ($result, $snapshot, $testPoints) {
            $result->update([
                'result_snapshot' => $snapshot,
                'engine_version'  => self::ENGINE_VERSION,
                'calculated_at'   => now(),
                'status'          => 'calculated',
            ]);

            $result->dataPoints()->delete();

            foreach ($testPoints as $tp) {
                $result->dataPoints()->create([
                    'test_point_index'    => $tp['index'],
                    'nominal_value'       => $tp['nominal'],
                    'uuc_mean'            => $tp['uuc_mean'],
                    'ref_mean'            => $tp['ref_mean'],
                    'error_of_indication' => $tp['error'],
                    'hysteresis'          => $tp['hysteresis'],
                    'repeatability'       => $tp['repeatability'],
                    'u_combined'          => $tp['u_combined'],
                    'u_expanded'          => $tp['u_expanded'],
                    'cmc_value'           => $tp['cmc_value'],
                    'u_final'             => $tp['u_final'],
                    'u_source'            => $tp['u_source'],
                    'within_mpe'          => $tp['within_mpe'],
                    'budget_components'   => $tp['budget_components'],
                ]);
            }
        });

        return $result->fresh();
    }

    // ── Context building ──────────────────────────────────────────────

    private function buildContext(array $fields, int $i, array $schema, $template, array $instrumentContext): array
    {
        // Start with all setup field values so formulas can reference them directly
        $context = array_merge($fields, $instrumentContext);

        // Collect raw readings based on schema pattern
        $refReadings = $this->collectReadings($fields, $i, $schema['ref_pattern'] ?? '', $schema, $template);
        $uucReadings = $this->collectReadings($fields, $i, $schema['uuc_pattern'] ?? '', $schema, $template);

        $refRawMean = $this->mean($refReadings);
        $uucMean    = $this->mean($uucReadings);

        // Polynomial correction on ref mean (uses reference instrument coefficients)
        $polyCoeffs  = $instrumentContext['poly_coefficients'] ?? [];
        $correctedRef = app(PolynomialCorrector::class)->correct($refRawMean, $polyCoeffs);

        // Head correction (pressure only)
        $hc = 0.0;
        if ($schema['head_correction'] ?? false) {
            $medium   = $fields['pressure_medium'] ?? 'air';
            $rhoFluid = match ($medium) { 'oil' => 912.0, 'water' => 1000.0, default => 1.2 };
            $g        = 9.795227688;
            $deltaH   = ((float) ($fields['h_ref'] ?? 0) - (float) ($fields['h_uuc'] ?? 0)) / 1000;
            $hc       = ($rhoFluid - 1.2) * $g * $deltaH / 100000;
        }

        $refMean = $correctedRef + $hc;

        // Repeatability — standard deviation of ref readings
        $refRepeatability = $this->stdDev($refReadings);
        $uucRepeatability = $this->stdDev($uucReadings);

        // Hysteresis (pressure only — split up/down passes)
        $hysteresis = 0.0;
        if ($schema['hysteresis'] ?? false) {
            $passes     = $schema['passes'] ?? [];
            $upPasses   = array_filter($passes, fn($p) => str_starts_with($p, 'up'));
            $dnPasses   = array_filter($passes, fn($p) => str_starts_with($p, 'dn'));
            $upReadings = $this->collectPassReadings($fields, $i, $schema['ref_pattern'], array_values($upPasses), $template);
            $dnReadings = $this->collectPassReadings($fields, $i, $schema['ref_pattern'], array_values($dnPasses), $template);
            $hysteresis = abs($this->mean($upReadings) - $this->mean($dnReadings));
        }

        // Zero error (pressure only)
        $zeroError = 0.0;
        if ($schema['zero_error'] ?? false) {
            $zeroError = abs((float) ($fields['zero_error_c1'] ?? 0));
        }

        $nominal = (float) ($fields["nominal_tp_{$i}"] ?? 0);

        // Uncertainty polynomial evaluated at raw reference reading (same x as correction poly)
        $polyUncertCoeffs         = $instrumentContext['poly_uncertainty_coefficients'] ?? [];
        $polyUncertaintyAtNominal = app(PolynomialCorrector::class)->correct($refRawMean, $polyUncertCoeffs);

        // Resolution of reference: temperature-dependent if breakpoint stored in instrument_params,
        // otherwise sign-based (pressure convention)
        if (isset($instrumentContext['resolution_t_breakpoint'])) {
            $resolutionRef = $correctedRef < $instrumentContext['resolution_t_breakpoint']
                ? ($instrumentContext['resolution_below'] ?? $instrumentContext['resolution_positive'] ?? 0)
                : ($instrumentContext['resolution_above'] ?? $instrumentContext['resolution_positive'] ?? 0);
        } else {
            $resolutionRef = $nominal >= 0
                ? ($instrumentContext['resolution_positive'] ?? 0)
                : ($instrumentContext['resolution_negative'] ?? 0);
        }

        return array_merge($context, [
            'nominal'                    => $nominal,
            'uuc_mean'                   => $uucMean,
            'ref_reading_raw'            => $refRawMean,
            'ref_reading'                => $refMean,
            'repeatability'              => $refRepeatability,
            'repeatability_ref'          => $refRepeatability,
            'repeatability_uuc'          => $uucRepeatability,
            'hysteresis'                 => $hysteresis,
            'zero_error'                 => $zeroError,
            'hc_bar'                     => $hc,
            'error_of_indication'        => $uucMean - $refMean,
            'resolution_ref'             => $resolutionRef,
            'poly_uncertainty_at_reading' => $polyUncertaintyAtNominal,
        ]);
    }

    // ── Instrument context from job instruments ───────────────────────

    private function buildInstrumentContext($jobInstruments): array
    {
        $ctx = [];

        foreach ($jobInstruments as $ji) {
            $inst = $ji->instrument;
            if (!$inst) continue;

            if ($ji->role === 'reference') {
                // Reference instrument fields used by formula nodes
                $ctx['mpe']                      = $inst->mpe;
                $ctx['drift']                    = $inst->accountable_drift;
                $ctx['resolution_positive']      = $inst->resolution_positive;
                $ctx['resolution_negative']      = $inst->resolution_negative;
                $ctx['poly_coefficients']             = $inst->poly_coefficients ?? [];
                $ctx['poly_uncertainty_coefficients'] = $inst->poly_uncertainty_coefficients ?? [];
                $ctx['poly_residual_cor']             = $inst->max_correction_residual;
                $ctx['poly_residual_un']              = $inst->max_uncertainty_residual;
            }

            // Merge all instrument_params into context regardless of role
            // Keys like well_axial, bath_uniformity etc are referenced directly by formula nodes
            foreach ($inst->instrument_params ?? [] as $key => $value) {
                $ctx[$key] = $value;
            }
        }

        return $ctx;
    }

    // ── Reading collection ────────────────────────────────────────────

    /**
     * Expand a pattern like "ref_tp_{i}_r{n}" or "ref_c{cycle}_{pass}_tp_{i}"
     * and collect all non-empty numeric values from $fields.
     */
    private function collectReadings(array $fields, int $i, string $pattern, array $schema, $template): array
    {
        if (str_contains($pattern, '{n}')) {
            // Simple numbered readings: ref_tp_{i}_r1 … r{reading_count}
            $count = $schema['reading_count'] ?? 6;
            $keys  = array_map(fn($n) => str_replace(['{i}', '{n}'], [$i, $n], $pattern), range(1, $count));
        } elseif (str_contains($pattern, '{pass}')) {
            // Cycle × pass pattern
            $passes = $schema['passes'] ?? ['up1', 'dn1', 'up2', 'dn2'];
            $cycles = $template->cycles ?? 1;
            $keys   = [];
            foreach (range(1, $cycles) as $cycle) {
                foreach ($passes as $pass) {
                    $keys[] = str_replace(['{i}', '{cycle}', '{pass}'], [$i, $cycle, $pass], $pattern);
                }
            }
        } else {
            return [];
        }

        return array_values(array_filter(
            array_map(fn($k) => isset($fields[$k]) && $fields[$k] !== '' ? (float) $fields[$k] : null, $keys),
            fn($val) => $val !== null
        ));
    }

    private function collectPassReadings(array $fields, int $i, string $pattern, array $passes, $template): array
    {
        $cycles = $template->cycles ?? 1;
        $keys   = [];
        foreach (range(1, $cycles) as $cycle) {
            foreach ($passes as $pass) {
                $keys[] = str_replace(['{i}', '{cycle}', '{pass}'], [$i, $cycle, $pass], $pattern);
            }
        }
        return array_values(array_filter(
            array_map(fn($k) => isset($fields[$k]) && $fields[$k] !== '' ? (float) $fields[$k] : null, $keys),
            fn($val) => $val !== null
        ));
    }

    // ── Nominals ─────────────────────────────────────────────────────

    private function extractNominals(array $fields, $template): array
    {
        $nominals = [];
        for ($i = 0; $i < $template->max_test_points; $i++) {
            $key = "nominal_tp_{$i}";
            if (isset($fields[$key]) && $fields[$key] !== '' && $fields[$key] !== null) {
                $nominals[$i] = (float) $fields[$key];
            }
        }
        return $nominals;
    }

    // ── Math helpers ──────────────────────────────────────────────────

    private function mean(array $values): float
    {
        return count($values) ? array_sum($values) / count($values) : 0.0;
    }

    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) return 0.0;
        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / ($n - 1);
        return sqrt($variance);
    }
}
