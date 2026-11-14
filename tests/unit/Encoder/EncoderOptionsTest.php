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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncoderOptionsTest extends TestCase
{
    public function test_it_should_reject_a_max_depth_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The max depth must be at least 1, but received 0.');

        new EncoderOptions(maxDepth: 0);
    }

    public function test_it_should_reject_negative_max_elements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The max elements cannot be negative, but received -1.');

        new EncoderOptions(maxElements: -1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidMaxTagNumberProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'below 30' => [29];
    }

    #[DataProvider('invalidMaxTagNumberProvider')]
    public function test_it_should_reject_a_max_tag_number_other_than_zero_or_at_least_30(int $maxTagNumber): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The max tag number must be 0 or at least 30, but received {$maxTagNumber}.");

        new EncoderOptions(maxTagNumber: $maxTagNumber);
    }

    public function test_a_copy_with_other_bitstring_padding_keeps_every_other_option(): void
    {
        $options = new EncoderOptions(
            bitstringPadding: '1',
            maxLength: 100,
            maxDepth: 4,
            maxElements: 50,
            maxTagNumber: 200,
        );

        self::assertEquals(
            new EncoderOptions(
                bitstringPadding: '0',
                maxLength: 100,
                maxDepth: 4,
                maxElements: 50,
                maxTagNumber: 200,
            ),
            $options->withBitstringPadding('0'),
        );
    }
}
