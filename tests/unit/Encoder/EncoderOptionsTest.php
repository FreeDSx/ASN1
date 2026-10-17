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

namespace Tests\Unit\FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Encoder\EncoderOptions;
use FreeDSx\Asn1\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EncoderOptionsTest extends TestCase
{
    public function test_it_should_reject_a_max_depth_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The max depth must be at least 1, but received 0.');

        new EncoderOptions(maxDepth: 0);
    }
}
