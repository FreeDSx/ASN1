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

namespace Tests\Unit\FreeDSx\Asn1;

use DateTime;
use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BmpStringType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\CharacterStringType;
use FreeDSx\Asn1\Type\EnumeratedType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use FreeDSx\Asn1\Type\GeneralStringType;
use FreeDSx\Asn1\Type\GraphicStringType;
use FreeDSx\Asn1\Type\IA5StringType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\NumericStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\OidType;
use FreeDSx\Asn1\Type\PrintableStringType;
use FreeDSx\Asn1\Type\RealType;
use FreeDSx\Asn1\Type\RelativeOidType;
use FreeDSx\Asn1\Type\SequenceOfType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Asn1\Type\SetOfType;
use FreeDSx\Asn1\Type\SetType;
use FreeDSx\Asn1\Type\TeletexStringType;
use FreeDSx\Asn1\Type\UniversalStringType;
use FreeDSx\Asn1\Type\UtcTimeType;
use FreeDSx\Asn1\Type\Utf8StringType;
use FreeDSx\Asn1\Type\VideotexStringType;
use FreeDSx\Asn1\Type\VisibleStringType;
use PHPUnit\Framework\TestCase;

final class Asn1Test extends TestCase
{
    public function test_it_should_construct_a_sequence_type(): void
    {
        self::assertEquals(
            new SequenceType(
                new IntegerType(1),
                new IntegerType(2),
            ),
            Asn1::sequence(
                new IntegerType(1),
                new IntegerType(2),
            ),
        );
    }

    public function test_it_should_construct_a_boolean_type(): void
    {
        self::assertEquals(
            new BooleanType(false),
            Asn1::boolean(false),
        );
    }

    public function test_it_should_construct_an_integer_type(): void
    {
        self::assertEquals(
            new IntegerType(1),
            Asn1::integer(1),
        );
    }

    public function test_it_should_construct_an_integer_type_from_a_numeric_string(): void
    {
        self::assertEquals(
            new IntegerType('99999999999999999'),
            Asn1::integer('99999999999999999'),
        );
    }

    public function test_it_should_construct_an_enumerated_type(): void
    {
        self::assertEquals(
            new EnumeratedType(1),
            Asn1::enumerated(1),
        );
    }

    public function test_it_should_construct_a_null_type(): void
    {
        self::assertEquals(
            new NullType(),
            Asn1::null(),
        );
    }

    public function test_it_should_construct_a_sequence_of_type(): void
    {
        self::assertEquals(
            new SequenceOfType(
                new IntegerType(1),
                new IntegerType(2),
            ),
            Asn1::sequenceOf(
                new IntegerType(1),
                new IntegerType(2),
            ),
        );
    }

    public function test_it_should_construct_a_set_type(): void
    {
        self::assertEquals(
            new SetType(
                new BooleanType(true),
                new BooleanType(false),
            ),
            Asn1::set(
                new BooleanType(true),
                new BooleanType(false),
            ),
        );
    }

    public function test_it_should_construct_a_set_of_type(): void
    {
        self::assertEquals(
            new SetOfType(
                new BooleanType(true),
                new BooleanType(false),
            ),
            Asn1::setOf(
                new BooleanType(true),
                new BooleanType(false),
            ),
        );
    }

    public function test_it_should_construct_an_octet_string_type(): void
    {
        self::assertEquals(
            new OctetStringType('foo'),
            Asn1::octetString('foo'),
        );
    }

    public function test_it_should_construct_a_bit_string(): void
    {
        self::assertEquals(
            new BitStringType('1000'),
            Asn1::bitString('1000'),
        );
    }

    public function test_it_should_construct_a_bit_string_from_an_integer(): void
    {
        self::assertEquals(
            new BitStringType('00001000'),
            Asn1::bitStringFromInteger(8),
        );
    }

    public function test_it_should_construct_a_bit_string_from_binary(): void
    {
        self::assertEquals(
            new BitStringType('00001000'),
            Asn1::bitStringFromBinary(hex2bin('08')),
        );
    }

    public function test_it_should_construct_an_oid(): void
    {
        self::assertEquals(
            new OidType('1.2.3'),
            Asn1::oid('1.2.3'),
        );
    }

    public function test_it_should_construct_a_relative_oid(): void
    {
        self::assertEquals(
            new RelativeOidType('3.100'),
            Asn1::relativeOid('3.100'),
        );
    }

    public function test_it_should_construct_a_bmp_string(): void
    {
        self::assertEquals(
            new BmpStringType('foo'),
            Asn1::bmpString('foo'),
        );
    }

    public function test_it_should_construct_a_character_string(): void
    {
        self::assertEquals(
            new CharacterStringType('foo'),
            Asn1::charString('foo'),
        );
    }

    public function test_it_should_construct_a_generalized_time_string(): void
    {
        $date = new DateTime();

        self::assertEquals(
            new GeneralizedTimeType($date),
            Asn1::generalizedTime($date),
        );
    }

    public function test_it_should_construct_a_generalized_time_string_with_no_argument(): void
    {
        self::assertEqualsWithDelta(
            new GeneralizedTimeType(new DateTime()),
            Asn1::generalizedTime(),
            5.0,
        );
    }

    public function test_it_should_construct_a_utc_time_string(): void
    {
        $date = new DateTime();

        self::assertEquals(
            new UtcTimeType($date),
            Asn1::utcTime($date),
        );
    }

    public function test_it_should_construct_a_utc_time_string_with_no_argument(): void
    {
        self::assertEqualsWithDelta(
            new UtcTimeType(new DateTime()),
            Asn1::utcTime(),
            5.0,
        );
    }

    public function test_it_should_construct_a_general_string(): void
    {
        self::assertEquals(
            new GeneralStringType('foo'),
            Asn1::generalString('foo'),
        );
    }

    public function test_it_should_construct_a_graphic_string(): void
    {
        self::assertEquals(
            new GraphicStringType('foo'),
            Asn1::graphicString('foo'),
        );
    }

    public function test_it_should_construct_an_ia5_string(): void
    {
        self::assertEquals(
            new IA5StringType('foo'),
            Asn1::ia5String('foo'),
        );
    }

    public function test_it_should_construct_a_numeric_string(): void
    {
        self::assertEquals(
            new NumericStringType('123'),
            Asn1::numericString('123'),
        );
    }

    public function test_it_should_construct_a_printable_string(): void
    {
        self::assertEquals(
            new PrintableStringType('foo'),
            Asn1::printableString('foo'),
        );
    }

    public function test_it_should_construct_a_teletex_string(): void
    {
        self::assertEquals(
            new TeletexStringType('foo'),
            Asn1::teletexString('foo'),
        );
    }

    public function test_it_should_construct_a_universal_string(): void
    {
        self::assertEquals(
            new UniversalStringType('foo'),
            Asn1::universalString('foo'),
        );
    }

    public function test_it_should_construct_a_utf8_string(): void
    {
        self::assertEquals(
            new Utf8StringType('foo'),
            Asn1::utf8String('foo'),
        );
    }

    public function test_it_should_construct_a_videotex_string(): void
    {
        self::assertEquals(
            new VideotexStringType('foo'),
            Asn1::videotexString('foo'),
        );
    }

    public function test_it_should_construct_a_visible_string(): void
    {
        self::assertEquals(
            new VisibleStringType('foo'),
            Asn1::visibleString('foo'),
        );
    }

    public function test_it_should_construct_a_real_type(): void
    {
        self::assertEquals(
            new RealType(0),
            Asn1::real(0),
        );
    }

    public function test_it_should_tag_a_type_as_context_specific(): void
    {
        self::assertEquals(
            (new BooleanType(true))
                ->setTagNumber(5)
                ->setTagClass(AbstractType::TAG_CLASS_CONTEXT_SPECIFIC),
            Asn1::context(5, new BooleanType(true)),
        );
    }

    public function test_it_should_tag_a_type_as_universal(): void
    {
        self::assertEquals(
            (new BooleanType(true))
                ->setTagNumber(6)
                ->setTagClass(AbstractType::TAG_CLASS_UNIVERSAL),
            Asn1::universal(6, new BooleanType(true)),
        );
    }

    public function test_it_should_tag_a_type_as_private(): void
    {
        self::assertEquals(
            (new BooleanType(true))
                ->setTagNumber(5)
                ->setTagClass(AbstractType::TAG_CLASS_PRIVATE),
            Asn1::private(5, new BooleanType(true)),
        );
    }
}
