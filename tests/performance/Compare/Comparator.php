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

namespace Tests\Performance\FreeDSx\Asn1\Compare;

use Tests\Performance\FreeDSx\Asn1\Report\Report;
use Tests\Performance\FreeDSx\Asn1\Report\WorkloadResult;

/**
 * Computes per-workload/op deltas between two single-pair reports.
 */
final class Comparator
{
    /**
     * @return list<ComparisonRow>
     */
    public function compare(
        Report $baseline,
        Report $head
    ): array {
        $names = array_unique(array_merge(
            array_keys($baseline->workloads),
            array_keys($head->workloads),
        ));
        $rows = [];
        foreach ($names as $name) {
            $rows[] = new ComparisonRow(
                workload: $name,
                baseline: $baseline->workloads[$name] ?? null,
                head: $head->workloads[$name] ?? null,
            );
        }

        return $rows;
    }

    /**
     * @param list<ComparisonRow> $rows
     */
    public function worstAbsoluteDelta(array $rows): float
    {
        $worst = 0.0;
        foreach ($rows as $row) {
            foreach (WorkloadResult::operations() as $op) {
                $worst = max($worst, abs($row->deltaPctFor($op)));
            }
        }

        return $worst;
    }

    /**
     * @param list<ComparisonRow> $rows
     *
     * @return list<string>
     */
    public function regressions(
        array $rows,
        float $thresholdPct
    ): array {
        $out = [];

        foreach ($rows as $row) {
            foreach (WorkloadResult::operations() as $op) {
                $delta = $row->deltaPctFor($op);
                if ($delta > $thresholdPct) {
                    $out[] = sprintf('%s/%s: +%s%%', $row->workload, $op, number_format($delta, 1));
                }
            }
        }

        return $out;
    }
}
