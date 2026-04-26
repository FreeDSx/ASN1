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

namespace FreeDSx\Asn1\Encoder;

/**
 * Options shared by ASN.1 encoders.
 */
final readonly class EncoderOptions
{
    public function __construct(
        public string $bitstringPadding = '0',
    ) {
    }
}
