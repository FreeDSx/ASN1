<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Performance\FreeDSx\Asn1\Aggregate;

use InvalidArgumentException;
use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Report\WorkloadResult;

/**
 * Aggregates N paired baseline/head bench reports into per-workload/op
 * median delta + range + sign-consistency metrics.
 */
final class Aggregator
{
    /**
     * @param list<Report> $baselineReports
     * @param list<Report> $headReports
     *
     * @return list<AggregatedRow>
     */
    public function aggregate(
        array $baselineReports,
        array $headReports,
    ): array {
        if ($baselineReports === [] || $headReports === [] || count($baselineReports) !== count($headReports)) {
            throw new InvalidArgumentException(
                'Aggregator needs the same non-zero number of baseline and head reports.',
            );
        }
        $pairs = count($baselineReports);
        $names = $this->collectWorkloadNames($baselineReports, $headReports);

        $rows = [];
        foreach ($names as $name) {
            foreach (WorkloadResult::operations() as $op) {
                $deltas = $this->perPairDeltas($baselineReports, $headReports, $name, $op, $pairs);
                if ($deltas === []) {
                    continue;
                }
                sort($deltas);
                $count = count($deltas);
                $median = $deltas[intdiv($count, 2)];
                $sign = $median >= 0 ? 1 : -1;
                $sameDir = count(array_filter(
                    $deltas,
                    static fn (float $d): bool => $sign === 1 ? $d >= 0 : $d <= 0,
                ));

                $rows[] = new AggregatedRow(
                    workload: $name,
                    op: $op,
                    medianDeltaPct: $median,
                    minDeltaPct: $deltas[0],
                    maxDeltaPct: $deltas[$count - 1],
                    sameDirectionCount: $sameDir,
                    totalPairs: $count,
                );
            }
        }

        return $rows;
    }

    /**
     * @param list<Report> $baselineReports
     * @param list<Report> $headReports
     *
     * @return list<float>
     */
    private function perPairDeltas(
        array $baselineReports,
        array $headReports,
        string $name,
        string $op,
        int $pairs,
    ): array {
        $deltas = [];
        for ($i = 0; $i < $pairs; $i++) {
            $base = $baselineReports[$i]->workloads[$name] ?? null;
            $head = $headReports[$i]->workloads[$name] ?? null;
            if ($base === null || $head === null) {
                continue;
            }
            $b = $base->op($op)->nsPerPayload;
            $h = $head->op($op)->nsPerPayload;
            if ($b <= 0.0) {
                continue;
            }
            $deltas[] = ($h - $b) / $b * 100.0;
        }

        return $deltas;
    }

    /**
     * @param list<Report> $base
     * @param list<Report> $head
     *
     * @return list<string>
     */
    private function collectWorkloadNames(array $base, array $head): array
    {
        $names = [];
        foreach ([...$base, ...$head] as $report) {
            foreach (array_keys($report->workloads) as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }
}
