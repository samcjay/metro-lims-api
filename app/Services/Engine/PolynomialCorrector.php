<?php
namespace App\Services\Engine;

class PolynomialCorrector
{
   /**
     * Apply polynomial correction to a reference gauge reading.
     * Coefficients stored as ['a'=>..,'b'=>..,'c'=>..,'d'=>..] (cubic)
     * or up to ['g'] for 7th-degree (PG-002, PG-007, PG-010, PG-011).
     * Uses Horner's method for numerical stability.
     */
    public function correct(float $reading, array $coefficients): float
    {
        // Build coefficient list from highest degree (g) down to constant (d)
        $keys = ['g', 'f', 'e', 'd', 'c', 'b', 'a'];
        $coefs = [];
        foreach (array_reverse($keys) as $k) {
            if (isset($coefficients[$k]) && $coefficients[$k] !== null) {
                $coefs[] = (float) $coefficients[$k];
            }
        }

        if (empty($coefs) || array_sum(array_map('abs', $coefs)) === 0.0) {
            return $reading; // no correction
        }

        // Horner's method: result = (...((a*x + b)*x + c)*x + d)...
        $result = 0.0;
        foreach ($coefs as $coef) {
            $result = $result * $reading + $coef;
        }

        return $result;
    }

    public function residualUncertainty(float $maxResidual): float
    {
        return $maxResidual;
    }
}
