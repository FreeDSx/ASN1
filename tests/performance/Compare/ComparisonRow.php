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

use Tests\Performance\FreeDSx\Asn1\Report\WorkloadResult;

/**
 * One workload's baseline-vs-head numbers, with helpers for per-op deltas.
 */
final readonly class ComparisonRow
{
    public function __construct(
        public string          $workload,
        public ?WorkloadResult $baseline,
        public ?WorkloadResult $head,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->baseline !== null && $this->head !== null;
    }

    public function deltaPctFor(string $op): float
    {
        if (!$this->isComplete()) {
            return 0.0;
        }
        \assert($this->baseline !== null && $this->head !== null);
        $base = $this->baseline->op($op)->nsPerPayload;
        $head = $this->head->op($op)->nsPerPayload;
        if ($base <= 0.0) {
            return 0.0;
        }

        return (($head - $base) / $base) * 100.0;
    }

    public function baselineNsFor(string $op): float
    {
        return $this->baseline?->op($op)->nsPerPayload ?? 0.0;
    }

    public function headNsFor(string $op): float
    {
        return $this->head?->op($op)->nsPerPayload ?? 0.0;
    }
}
