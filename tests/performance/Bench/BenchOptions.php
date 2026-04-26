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

namespace Tests\Performance\FreeDSx\Asn1\Bench;

/**
 * Immutable knobs controlling a bench run.
 */
final readonly class BenchOptions
{
    public function __construct(
        public string  $encoder = 'ber',
        public ?string $workload = null,
        public int     $warmup = 2,
        public int     $samples = 7,
        public int     $revs = 3,
    ) {
    }
}
