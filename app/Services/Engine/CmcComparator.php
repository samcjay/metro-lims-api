<?php
namespace App\Services\Engine;

class CmcComparator
{
    public function compare(float $nominalValue, float $uExpanded, $cmcEntries, string $medium = 'pneumatic'): array
    {
        $cmcValue = $this->lookupCmc($nominalValue, $uExpanded, $cmcEntries, $medium);

        if ($cmcValue === null) {
            return ['u_final' => $uExpanded, 'source' => 'expanded', 'cmc_value' => null];
        }

        if ($uExpanded >= $cmcValue) {
            return ['u_final' => $uExpanded, 'source' => 'expanded', 'cmc_value' => $cmcValue];
        }

        return ['u_final' => $cmcValue, 'source' => 'cmc', 'cmc_value' => $cmcValue];
    }

    private function lookupCmc(float $value, float $uExpanded, $cmcEntries, string $medium): ?float
    {
        foreach ($cmcEntries as $entry) {
            if ($entry->medium !== $medium) continue;
            if ($value >= $entry->range_from && $value <= $entry->range_to) {
                if ($entry->cmc_formula) {
                    $x = $value;
                    return (float) eval("return {$entry->cmc_formula};");
                }
                return (float) $entry->cmc_value;
            }
        }
        return null;
    }
}
