<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class BiasEvaluationService
{
    /** @param list<array<string,mixed>> $records @return array<string,array<string,mixed>> */
    public function evaluate(array $records, ApprovedBiasPolicy $policy): array
    {
        $groups = array_fill_keys($policy->bands(), []);
        foreach ($records as $record) {
            $band = $record['educationBand'] ?? null;
            if (is_string($band) && array_key_exists($band, $groups)) $groups[$band][] = $record;
        }
        ksort($groups, SORT_STRING);
        $result = [];
        foreach ($groups as $band => $rows) {
            $count = count($rows);
            if ($count < $policy->minimumSampleSize()) {
                $result[$band] = ['status' => 'insufficient_sample', 'sample_size' => $count];
                continue;
            }
            $passes = 0;
            foreach ($rows as $row) {
                if (($row['schemaValid'] ?? false) === true && (float) ($row['evidenceCoverage'] ?? 0) === 1.0
                    && (int) ($row['unsupportedClaimCount'] ?? 1) === 0 && (int) ($row['unsafeOutputCount'] ?? 1) === 0) $passes++;
            }
            $result[$band] = ['status' => 'scored', 'sample_size' => $count, 'hard_gate_pass_rate' => round($passes / $count, 6), 'policy_version' => $policy->version()];
        }
        return $result;
    }
}
