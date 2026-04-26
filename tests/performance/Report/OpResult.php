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

namespace Tests\Performance\FreeDSx\Asn1\Report;

use Tests\Performance\FreeDSx\Asn1\Support\Cast;

/**
 * Per-operation timing summary across N samples.
 */
final readonly class OpResult
{
    public function __construct(
        public int   $medianNs,
        public int   $minNs,
        public int   $maxNs,
        public float $opsPerSec,
        public float $nsPerPayload,
    ) {
    }

    /**
     * @param list<int> $samplesNs Per-sample wall-clock duration in nanoseconds.
     */
    public static function fromSamples(
        array $samplesNs,
        int $payloadCount
    ): self {
        sort($samplesNs);
        $count = count($samplesNs);
        $median = $samplesNs[intdiv($count, 2)];

        return new self(
            medianNs: $median,
            minNs: $samplesNs[0],
            maxNs: $samplesNs[$count - 1],
            opsPerSec: $median > 0 ? 1_000_000_000.0 / $median : 0.0,
            nsPerPayload: $payloadCount > 0 ? $median / $payloadCount : 0.0,
        );
    }

    /**
     * @return array{median_ns: int, min_ns: int, max_ns: int, ops_per_sec: float, ns_per_payload: float}
     */
    public function toArray(): array
    {
        return [
            'median_ns' => $this->medianNs,
            'min_ns' => $this->minNs,
            'max_ns' => $this->maxNs,
            'ops_per_sec' => $this->opsPerSec,
            'ns_per_payload' => $this->nsPerPayload,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            medianNs: Cast::toInt($raw['median_ns'] ?? 0),
            minNs: Cast::toInt($raw['min_ns'] ?? 0),
            maxNs: Cast::toInt($raw['max_ns'] ?? 0),
            opsPerSec: Cast::toFloat($raw['ops_per_sec'] ?? 0.0),
            nsPerPayload: Cast::toFloat($raw['ns_per_payload'] ?? 0.0),
        );
    }
}
