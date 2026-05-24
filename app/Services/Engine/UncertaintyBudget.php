<?php
namespace App\Services\Engine;

class UncertaintyBudget
{
    public function __construct(
        private FormulaEvaluator $evaluator,
    ) {}

    public function compute(array $formulaNodes, array $context): array
    {
        $resolved     = $this->evaluator->evaluate($formulaNodes, $context);
        $components   = [];
        $sumOfSquares = 0.0;

        foreach ($formulaNodes as $node) {
            if ($node->uncertainty_type === 'none') continue;

            $u        = (float) ($resolved[$node->key] ?? 0.0);
            $ci       = (float) ($node->sensitivity_coeff ?? 1.0);
            $divisor  = $this->resolveDivisor($node->divisor_formula ?? '1');
            $std_u    = $divisor > 0 ? $u / $divisor : $u;
            $contrib  = ($ci * $std_u) ** 2;
            $sumOfSquares += $contrib;

            $components[$node->key] = [
                'label'        => $node->label,
                'symbol'       => $node->symbol,
                'type'         => $node->uncertainty_type,
                'variability'  => $u,
                'divisor'      => $divisor,
                'u'            => $std_u,
                'ci'           => $ci,
                'contribution' => $contrib,
            ];
        }

        $uCombined = sqrt($sumOfSquares);
        $uExpanded = 2 * $uCombined;

        return [
            'components'    => $components,
            'u_combined'    => $uCombined,
            'u_expanded'    => $uExpanded,
            'k'             => 2,
            'error'         => $resolved['error_of_indication'] ?? 0.0,
            'ref_mean'      => $resolved['ref_reading']         ?? 0.0,
            'uuc_mean'      => $resolved['uuc_mean']            ?? 0.0,
            'hysteresis'    => $resolved['hysteresis']          ?? 0.0,
            'repeatability' => $resolved['repeatability']       ?? 0.0,
        ];
    }

    private function resolveDivisor(string $formula): float
    {
        return match (trim($formula)) {
            'sqrt(3)'              => sqrt(3),
            '2*sqrt(3)', '2sqrt(3)' => 2 * sqrt(3),
            'sqrt(12)'             => sqrt(12),
            'sqrt(6)'              => sqrt(6),
            '2'                    => 2.0,
            '1', ''                => 1.0,
            default                => (float) $formula ?: 1.0,
        };
    }
}
