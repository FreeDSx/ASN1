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

namespace Tests\Unit\FreeDSx\Asn1\Type;

use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use PHPUnit\Framework\TestCase;

final class BitStringTypeTest extends TestCase
{
    private BitStringType $subject;

    protected function setUp(): void
    {
        $this->subject = new BitStringType('011011100101110111000000');
    }

    public function test_it_should_get_the_integer_value(): void
    {
        $subject = new BitStringType('11000000');

        self::assertSame(
            192,
            $subject->toInteger(),
        );
    }

    public function test_it_should_get_the_integer_value_of_a_bit_string_with_trailing_zeroes(): void
    {
        $subject = new BitStringType('11000000000000000000000000000000');

        self::assertSame(
            192,
            $subject->toInteger(),
        );
    }

    public function test_it_should_get_the_packed_binary_representation(): void
    {
        self::assertSame(
            hex2bin('6e5dc0'),
            $this->subject->toBinary(),
        );
    }

    public function test_it_should_get_the_bit_string_from_binary(): void
    {
        $subject = BitStringType::fromBinary(hex2bin('6e5dc0'));

        self::assertEquals(
            '011011100101110111000000',
            $subject->getValue(),
        );
    }

    public function test_it_should_get_the_bit_string_from_an_integer(): void
    {
        self::assertEquals(
            new BitStringType('01000000'),
            BitStringType::fromInteger(64),
        );
        self::assertEquals(
            new BitStringType('1111101011010100'),
            BitStringType::fromInteger(64212),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_BIT_STRING,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_adhere_to_a_min_length_on_integer_and_binary_if_specified(): void
    {
        self::assertEquals(
            new BitStringType('00000001000000000000000000000000'),
            BitStringType::fromInteger(1, 32),
        );
        self::assertEquals(
            new BitStringType('0000001000000000'),
            BitStringType::fromBinary("\x02", 16),
        );
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new BitStringType('1.2.3.4'))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setValue('1.2.3.4'),
            BitStringType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, '1.2.3.4'),
        );
    }
}
