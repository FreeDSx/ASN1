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

/**
 * One workload/op's aggregated stats across N paired runs.
 */
final readonly class AggregatedRow
{
    public function __construct(
        public string $workload,
        public string $op,
        public float  $medianDeltaPct,
        public float  $minDeltaPct,
        public float  $maxDeltaPct,
        public int    $sameDirectionCount,
        public int    $totalPairs,
    ) {
    }

    public function consistencyPct(): float
    {
        if ($this->totalPairs === 0) {
            return 0.0;
        }

        return $this->sameDirectionCount / $this->totalPairs * 100.0;
    }

    public function isSignificant(
        float $thresholdPct,
        float $consistencyPct
    ): bool {
        return abs($this->medianDeltaPct) >= $thresholdPct
            && $this->consistencyPct() >= $consistencyPct;
    }

    public function isRegression(
        float $thresholdPct,
        float $consistencyPct
    ): bool {
        return $this->isSignificant($thresholdPct, $consistencyPct) && $this->medianDeltaPct > 0;
    }

    public function isImprovement(
        float $thresholdPct,
        float $consistencyPct
    ): bool {
        return $this->isSignificant($thresholdPct, $consistencyPct) && $this->medianDeltaPct < 0;
    }
}
