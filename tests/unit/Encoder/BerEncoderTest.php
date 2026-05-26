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

use DateTime;
use DateTimeZone;
use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Encoder\EncoderOptions;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Exception\PduLengthException;
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
use FreeDSx\Asn1\Type\IncompleteType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\NumericStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\OidType;
use FreeDSx\Asn1\Type\PrintableStringType;
use FreeDSx\Asn1\Type\RealType;
use FreeDSx\Asn1\Type\RelativeOidType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Asn1\Type\TeletexStringType;
use FreeDSx\Asn1\Type\UniversalStringType;
use FreeDSx\Asn1\Type\UtcTimeType;
use FreeDSx\Asn1\Type\Utf8StringType;
use FreeDSx\Asn1\Type\VideotexStringType;
use FreeDSx\Asn1\Type\VisibleStringType;
use PHPUnit\Framework\TestCase;

final class BerEncoderTest extends TestCase
{
    private BerEncoder $subject;

    protected function setUp(): void
    {
        $this->subject = new BerEncoder();
    }

    public function test_it_should_set_options(): void
    {
        $options = new EncoderOptions(bitstringPadding: '1');

        $this->subject->setOptions($options);

        self::assertSame(
            $options,
            $this->subject->getOptions(),
        );
    }

    public function test_it_should_get_options(): void
    {
        self::assertSame(
            '0',
            $this->subject->getOptions()->bitstringPadding,
        );
    }

    public function test_it_should_accept_options_via_the_constructor(): void
    {
        $subject = new BerEncoder(new EncoderOptions(bitstringPadding: '1'));

        self::assertSame(
            '1',
            $subject->getOptions()->bitstringPadding,
        );
    }

    public function test_it_should_reject_a_root_pdu_whose_declared_length_exceeds_the_max_length(): void
    {
        $subject = new BerEncoder(new EncoderOptions(maxLength: 100));

        $this->expectException(PduLengthException::class);

        $subject->decode(hex2bin('3084000000c8'));
    }

    public function test_it_should_decode_a_root_pdu_within_the_max_length(): void
    {
        $subject = new BerEncoder(new EncoderOptions(maxLength: 1000));
        $encoded = $subject->encode(new OctetStringType('foo'));

        self::assertEquals(
            new OctetStringType('foo'),
            $subject->decode($encoded),
        );
    }

    public function test_it_should_treat_an_oversized_declared_length_as_partial_when_no_max_length_is_set(): void
    {
        $this->expectException(PartialPduException::class);

        $this->subject->decode(hex2bin('3084000000c8'));
    }

    public function test_it_should_decode_long_definite_length_when_the_length_is_the_exact_size_of_the_payload(): void
    {
        $tagAndLength = hex2bin('3084000000350201');
        $value = hex2bin('1e63840000002c040864633d6c6f63616c0a01000a0100020100020100010100870b6f626a656374636c617373308400000000');
        $length = strlen($tagAndLength . $value);

        self::assertInstanceOf(SequenceType::class, $this->subject->decode($tagAndLength . $value));
        self::assertSame(
            $length,
            $this->subject->getLastPosition(),
        );
    }

    public function test_it_should_decode_long_definite_length(): void
    {
        $chars = str_pad('0', 131071, '0');

        self::assertEquals(
            new OctetStringType($chars),
            $this->subject->decode(hex2bin('048301ffff') . $chars),
        );
    }

    public function test_it_should_encode_long_definite_length(): void
    {
        $chars = str_pad('', 131071, '0');

        self::assertSame(
            hex2bin('048301ffff') . $chars,
            $this->subject->encode(new OctetStringType($chars)),
        );
    }

    public function test_it_should_not_allow_long_definite_length_greater_than_or_equal_to_127(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('04ff'));
    }

    public function test_it_should_throw_when_encoding_an_unsupported_type(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessageMatches('/is not currently supported/');

        $this->subject->encode(new IncompleteType("\x00"));
    }

    public function test_it_should_return_consistent_results_when_encoding_and_decoding_the_same_oid_twice(): void
    {
        $oid = '1.3.6.1.4.1.311.21.20';
        $first = $this->subject->encode(new OidType($oid));
        $second = $this->subject->encode(new OidType($oid));

        self::assertSame($first, $second);

        $firstDecoded = $this->subject->decode($first);
        $secondDecoded = $this->subject->decode($first);

        self::assertSame($oid, $firstDecoded->getValue());
        self::assertSame($oid, $secondDecoded->getValue());
    }

    public function test_it_should_return_consistent_results_when_encoding_and_decoding_the_same_relative_oid_twice(): void
    {
        $oid = '8571.3.2';
        $first = $this->subject->encode(new RelativeOidType($oid));
        $second = $this->subject->encode(new RelativeOidType($oid));

        self::assertSame($first, $second);

        $firstDecoded = $this->subject->decode($first);
        $secondDecoded = $this->subject->decode($first);

        self::assertSame($oid, $firstDecoded->getValue());
        self::assertSame($oid, $secondDecoded->getValue());
    }

    public function test_it_should_decode_a_boolean_true_type(): void
    {
        self::assertEquals(
            new BooleanType(true),
            $this->subject->decode(hex2bin('0101FF')),
        );
        self::assertEquals(
            new BooleanType(true),
            $this->subject->decode(hex2bin('0101F3')),
        );
    }

    public function test_it_should_decode_a_boolean_false_type(): void
    {
        self::assertEquals(
            new BooleanType(false),
            $this->subject->decode(hex2bin('010100')),
        );
    }

    public function test_it_should_encode_a_boolean_type(): void
    {
        self::assertSame(
            hex2bin('0101FF'),
            $this->subject->encode(new BooleanType(true)),
        );
        self::assertSame(
            hex2bin('010100'),
            $this->subject->encode(new BooleanType(false)),
        );
    }

    public function test_it_should_decode_a_null_type(): void
    {
        self::assertEquals(
            new NullType(),
            $this->subject->decode(hex2bin('0500')),
        );
    }

    public function test_it_should_encode_a_null_type(): void
    {
        self::assertSame(
            hex2bin('0500'),
            $this->subject->encode(new NullType()),
        );
    }

    public function test_it_should_decode_a_zero_integer_type(): void
    {
        self::assertEquals(
            new IntegerType(0),
            $this->subject->decode(hex2bin('020100')),
        );
    }

    public function test_it_should_encode_a_zero_integer_type(): void
    {
        self::assertSame(
            hex2bin('020100'),
            $this->subject->encode(new IntegerType(0)),
        );
    }

    public function test_it_should_decode_a_big_int_positive_integer_type(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertEquals(
            new IntegerType('18446744073709551615'),
            $this->subject->decode(hex2bin('020900ffffffffffffffff')),
        );
    }

    public function test_it_should_decode_a_positive_integer_type(): void
    {
        self::assertEquals(
            new IntegerType(9223372036854775807),
            $this->subject->decode(hex2bin('02087FFFFFFFFFFFFFFF')),
        );
        self::assertEquals(
            new IntegerType(4294967296),
            $this->subject->decode(hex2bin('02050100000000')),
        );
        self::assertEquals(
            new IntegerType(4294967295),
            $this->subject->decode(hex2bin('020500FFFFFFFF')),
        );
        self::assertEquals(
            new IntegerType(2147483648),
            $this->subject->decode(hex2bin('02050080000000')),
        );
        self::assertEquals(
            new IntegerType(2147483647),
            $this->subject->decode(hex2bin('02047FFFFFFF')),
        );
        self::assertEquals(
            new IntegerType(27066),
            $this->subject->decode(hex2bin('020269BA')),
        );
        self::assertEquals(
            new IntegerType(256),
            $this->subject->decode(hex2bin('02020100')),
        );
        self::assertEquals(
            new IntegerType(255),
            $this->subject->decode(hex2bin('020200FF')),
        );
        self::assertEquals(
            new IntegerType(127),
            $this->subject->decode(hex2bin('02017F')),
        );
        self::assertEquals(
            new IntegerType(128),
            $this->subject->decode(hex2bin('02020080')),
        );
    }

    public function test_it_should_encode_a_big_int_positive_integer_type(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('020900ffffffffffffffff'),
            $this->subject->encode(new IntegerType('18446744073709551615')),
        );
    }

    public function test_it_should_encode_a_positive_integer_type(): void
    {
        self::assertSame(
            hex2bin('02087FFFFFFFFFFFFFFF'),
            $this->subject->encode(new IntegerType('9223372036854775807')),
        );
        self::assertSame(
            hex2bin('02050100000000'),
            $this->subject->encode(new IntegerType('4294967296')),
        );
        self::assertSame(
            hex2bin('020500FFFFFFFF'),
            $this->subject->encode(new IntegerType('4294967295')),
        );
        self::assertSame(
            hex2bin('02050080000000'),
            $this->subject->encode(new IntegerType('2147483648')),
        );
        self::assertSame(
            hex2bin('02047FFFFFFF'),
            $this->subject->encode(new IntegerType('2147483647')),
        );
        self::assertSame(
            hex2bin('020269BA'),
            $this->subject->encode(new IntegerType(27066)),
        );
        self::assertSame(
            hex2bin('02020100'),
            $this->subject->encode(new IntegerType(256)),
        );
        self::assertSame(
            hex2bin('020200FF'),
            $this->subject->encode(new IntegerType(255)),
        );
        self::assertSame(
            hex2bin('02017F'),
            $this->subject->encode(new IntegerType(127)),
        );
        self::assertSame(
            hex2bin('02020080'),
            $this->subject->encode(new IntegerType(128)),
        );
    }

    public function test_it_should_decode_a_big_int_negative_integer_type(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertEquals(
            new IntegerType('-18446744073709551615'),
            $this->subject->decode(hex2bin('0209ff0000000000000001')),
        );
    }

    public function test_it_should_decode_a_negative_integer_type(): void
    {
        self::assertEquals(
            new IntegerType('-9223372036854775807'),
            $this->subject->decode(hex2bin('02088000000000000001')),
        );
        self::assertEquals(
            new IntegerType(-4294967296),
            $this->subject->decode(hex2bin('0205FF00000000')),
        );
        self::assertEquals(
            new IntegerType(-4294967295),
            $this->subject->decode(hex2bin('0205FF00000001')),
        );
        self::assertEquals(
            new IntegerType(-2147483648),
            $this->subject->decode(hex2bin('020480000000')),
        );
        self::assertEquals(
            new IntegerType(-2147483647),
            $this->subject->decode(hex2bin('020480000001')),
        );
        self::assertEquals(
            new IntegerType(-32768),
            $this->subject->decode(hex2bin('02028000')),
        );
        self::assertEquals(
            new IntegerType(-27066),
            $this->subject->decode(hex2bin('02029646')),
        );
        self::assertEquals(
            new IntegerType(-127),
            $this->subject->decode(hex2bin('020181')),
        );
        self::assertEquals(
            new IntegerType(-128),
            $this->subject->decode(hex2bin('020180')),
        );
        self::assertEquals(
            new IntegerType(-129),
            $this->subject->decode(hex2bin('0202FF7F')),
        );
        self::assertEquals(
            new IntegerType(-1),
            $this->subject->decode(hex2bin('0201FF')),
        );
    }

    public function test_it_should_encode_a_big_int_negative_integer_type(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('0209ff0000000000000001'),
            $this->subject->encode(new IntegerType('-18446744073709551615')),
        );
    }

    public function test_it_should_encode_a_negative_integer_type(): void
    {
        self::assertSame(
            hex2bin('02088000000000000001'),
            $this->subject->encode(new IntegerType('-9223372036854775807')),
        );
        self::assertSame(
            hex2bin('0205FF00000000'),
            $this->subject->encode(new IntegerType('-4294967296')),
        );
        self::assertSame(
            hex2bin('0205FF00000001'),
            $this->subject->encode(new IntegerType('-4294967295')),
        );
        self::assertSame(
            hex2bin('020480000000'),
            $this->subject->encode(new IntegerType('-2147483648')),
        );
        self::assertSame(
            hex2bin('020480000001'),
            $this->subject->encode(new IntegerType('-2147483647')),
        );
        self::assertSame(
            hex2bin('02029646'),
            $this->subject->encode(new IntegerType(-27066)),
        );
        self::assertSame(
            hex2bin('020181'),
            $this->subject->encode(new IntegerType(-127)),
        );
        self::assertSame(
            hex2bin('020180'),
            $this->subject->encode(new IntegerType(-128)),
        );
        self::assertSame(
            hex2bin('0202FF7F'),
            $this->subject->encode(new IntegerType(-129)),
        );
        self::assertSame(
            hex2bin('0201FF'),
            $this->subject->encode(new IntegerType(-1)),
        );
    }

    public function test_it_should_encode_a_real_type_special_cases(): void
    {
        self::assertSame(
            hex2bin('090140'),
            $this->subject->encode(new RealType(INF)),
        );
        self::assertSame(
            hex2bin('090141'),
            $this->subject->encode(new RealType(-INF)),
        );
        self::assertSame(
            hex2bin('0900'),
            $this->subject->encode(new RealType(0)),
        );
    }

    public function test_it_should_decode_a_real_type_special_cases(): void
    {
        self::assertEquals(
            new RealType(INF),
            $this->subject->decode(hex2bin('090140')),
        );
        self::assertEquals(
            new RealType(-INF),
            $this->subject->decode(hex2bin('090141')),
        );
        self::assertEquals(
            new RealType(0),
            $this->subject->decode(hex2bin('0900')),
        );
    }

    public function test_it_should_decode_an_octet_string_type(): void
    {
        self::assertEquals(
            new OctetStringType('1.3.6.1.4.1.1466.20037'),
            $this->subject->decode(hex2bin('0416312e332e362e312e342e312e313436362e3230303337')),
        );
    }

    public function test_it_should_encode_an_octet_string(): void
    {
        self::assertSame(
            hex2bin('0416312e332e362e312e342e312e313436362e3230303337'),
            $this->subject->encode(new OctetStringType('1.3.6.1.4.1.1466.20037')),
        );
    }

    public function test_it_should_decode_an_enumerated_type(): void
    {
        self::assertEquals(
            new EnumeratedType(1),
            $this->subject->decode(hex2bin('0A0101')),
        );
    }

    public function test_it_should_encode_an_enumerated_type(): void
    {
        self::assertSame(
            hex2bin('0A0101'),
            $this->subject->encode(new EnumeratedType(1)),
        );
    }

    public function test_it_should_decode_a_sequence_type(): void
    {
        self::assertEquals(
            new SequenceType(
                new IntegerType(1),
                new IntegerType(2),
                new BooleanType(true),
            ),
            $this->subject->decode(hex2bin('30090201010201020101ff')),
        );
    }

    public function test_it_should_encode_a_sequence_type(): void
    {
        self::assertSame(
            hex2bin('30090201010201020101ff'),
            $this->subject->encode(new SequenceType(
                new IntegerType(1),
                new IntegerType(2),
                new BooleanType(true),
            )),
        );
    }

    public function test_it_should_encode_a_bit_string(): void
    {
        self::assertSame(
            hex2bin('0304066e5dc0'),
            $this->subject->encode(new BitStringType('011011100101110111')),
        );
        self::assertSame(
            hex2bin('030200ff'),
            $this->subject->encode(new BitStringType('11111111')),
        );
        self::assertSame(
            hex2bin('03020700'),
            $this->subject->encode(new BitStringType('0')),
        );
        self::assertSame(
            hex2bin('03020400'),
            $this->subject->encode(new BitStringType('0000')),
        );
        self::assertSame(
            hex2bin('030100'),
            $this->subject->encode(new BitStringType('')),
        );
    }

    public function test_it_should_encode_a_bit_string_to_a_min_length_if_specified(): void
    {
        self::assertSame(
            hex2bin('03050001000000'),
            $this->subject->encode(BitStringType::fromInteger(1, 32)),
        );
    }

    public function test_it_should_not_allow_an_invalid_amount_of_unused_bits_in_a_bit_string_when_decoding(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The unused bits in a bit string must be between 0 and 7, got: 8');

        $this->subject->decode(hex2bin('03020801'));
    }

    public function test_it_should_decode_a_bit_string(): void
    {
        self::assertEquals(
            new BitStringType('011011100101110111'),
            $this->subject->decode(hex2bin('0304066e5dc0')),
        );
        self::assertEquals(
            new BitStringType('11111111'),
            $this->subject->decode(hex2bin('030200ff')),
        );
        self::assertEquals(
            new BitStringType('0'),
            $this->subject->decode(hex2bin('03020700')),
        );
        self::assertEquals(
            new BitStringType('0000'),
            $this->subject->decode(hex2bin('03020400')),
        );
        self::assertEquals(
            new BitStringType(''),
            $this->subject->decode(hex2bin('030100')),
        );
    }

    public function test_it_should_decode_an_oid_with_a_bigint(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertEquals(
            new OidType('1.2.840.18446744073709551615.1'),
            $this->subject->decode(hex2bin('060e2a864881ffffffffffffffff7f01')),
        );
    }

    public function test_it_should_decode_an_oid_with_a_bigint_in_the_second_component(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertEquals(
            new OidType('2.18446744073709551615'),
            $this->subject->decode(hex2bin('060A8280808080808080804F')),
        );
    }

    public function test_it_should_decode_an_oid(): void
    {
        self::assertEquals(
            new OidType('0.0'),
            $this->subject->decode(hex2bin('060100')),
        );
        self::assertEquals(
            new OidType('1.2'),
            $this->subject->decode(hex2bin('06012A')),
        );
        self::assertEquals(
            new OidType('2.255'),
            $this->subject->decode(hex2bin('0602824F')),
        );
        self::assertEquals(
            new OidType('2.999.3'),
            $this->subject->decode(hex2bin('0603883703')),
        );
        self::assertEquals(
            new OidType('1.3.6.1.4.1.311.21.20'),
            $this->subject->decode(hex2bin('06092b0601040182371514')),
        );
        self::assertEquals(
            new OidType('1.2.840.113549'),
            $this->subject->decode(hex2bin('06062a864886f70d')),
        );
        self::assertEquals(
            new OidType('1.2.127'),
            $this->subject->decode(hex2bin('06022a7f')),
        );
        self::assertEquals(
            new OidType('1.2.128'),
            $this->subject->decode(hex2bin('06032a8100')),
        );
        self::assertEquals(
            new OidType('1.2.8192'),
            $this->subject->decode(hex2bin('06032ac000')),
        );
        self::assertEquals(
            new OidType('1.2.16383'),
            $this->subject->decode(hex2bin('06032aff7f')),
        );
        self::assertEquals(
            new OidType('1.2.2097152'),
            $this->subject->decode(hex2bin('06052a81808000')),
        );
        self::assertEquals(
            new OidType('1.2.268435455'),
            $this->subject->decode(hex2bin('06052affffff7f')),
        );
    }

    public function test_it_should_encode_an_oid_with_a_bigint(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('060e2a864881ffffffffffffffff7f01'),
            $this->subject->encode(new OidType('1.2.840.18446744073709551615.1')),
        );
    }

    public function test_it_should_encode_an_oid_with_a_bigint_in_the_second_component(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('060A8280808080808080804F'),
            $this->subject->encode(new OidType('2.18446744073709551615')),
        );
    }

    public function test_it_should_encode_an_oid(): void
    {
        self::assertSame(
            hex2bin('060100'),
            $this->subject->encode(new OidType('0.0')),
        );
        self::assertSame(
            hex2bin('06012A'),
            $this->subject->encode(new OidType('1.2')),
        );
        self::assertSame(
            hex2bin('0602824F'),
            $this->subject->encode(new OidType('2.255')),
        );
        self::assertSame(
            hex2bin('0603883703'),
            $this->subject->encode(new OidType('2.999.3')),
        );
        self::assertSame(
            hex2bin('06092b0601040182371514'),
            $this->subject->encode(new OidType('1.3.6.1.4.1.311.21.20')),
        );
        self::assertSame(
            hex2bin('06062a864886f70d'),
            $this->subject->encode(new OidType('1.2.840.113549')),
        );
        self::assertSame(
            hex2bin('06022a7f'),
            $this->subject->encode(new OidType('1.2.127')),
        );
        self::assertSame(
            hex2bin('06032a8100'),
            $this->subject->encode(new OidType('1.2.128')),
        );
        self::assertSame(
            hex2bin('06032ac000'),
            $this->subject->encode(new OidType('1.2.8192')),
        );
        self::assertSame(
            hex2bin('06032aff7f'),
            $this->subject->encode(new OidType('1.2.16383')),
        );
        self::assertSame(
            hex2bin('06052a81808000'),
            $this->subject->encode(new OidType('1.2.2097152')),
        );
        self::assertSame(
            hex2bin('06052affffff7f'),
            $this->subject->encode(new OidType('1.2.268435455')),
        );
    }

    public function test_it_should_handle_a_near_max_int_on_64bit(): void
    {
        if (PHP_INT_SIZE !== 8) {
            self::markTestSkipped('This test is only valid for 64 bit architecture.');
        }

        self::assertSame(
            hex2bin('0609FFFFFFFFFFFFFFFF7F'),
            $this->subject->encode(new OidType('2.9223372036854775727')),
        );
    }

    public function test_it_should_handle_a_max_int_on_64bit(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('060A81808080808080808000'),
            $this->subject->encode(new OidType('2.9223372036854775728')),
        );
    }

    public function test_it_should_not_accept_an_oid_with_a_first_identifier_greater_than_2(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encode(new OidType('3.1'));
    }

    public function test_it_should_encode_a_generalized_time_string_non_utc_with_a_differential(): void
    {
        $datetime = new DateTime('20180318', new DateTimeZone('America/Chicago'));

        self::assertSame(
            hex2bin('1813') . '20180318000000-0500',
            $this->subject->encode(new GeneralizedTimeType(
                $datetime,
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_DIFF,
            )),
        );
    }

    public function test_it_should_encode_a_generalized_time_string_utc_with_an_ending_z(): void
    {
        $datetime = new DateTime('20180318', new DateTimeZone('UTC'));

        self::assertSame(
            hex2bin('180f') . '20180318000000Z',
            $this->subject->encode(new GeneralizedTimeType(
                $datetime,
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_UTC,
            )),
        );
    }

    public function test_it_should_encode_a_generalized_time_string_as_local_time_if_specified(): void
    {
        $time = new GeneralizedTimeType(
            new DateTime('20180318', new DateTimeZone(date_default_timezone_get())),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_LOCAL,
        );

        self::assertSame(
            hex2bin('180e') . '20180318000000',
            $this->subject->encode($time),
        );
    }

    public function test_it_should_encode_a_generalized_time_with_fractional_seconds_if_they_exist(): void
    {
        self::assertSame(
            hex2bin('1814') . '20180318100201.0123Z',
            $this->subject->encode(new GeneralizedTimeType(new DateTime('2018-03-18T10:02:01.012300Z'))),
        );
    }

    public function test_it_should_not_encode_a_generalized_time_with_fractional_seconds_if_specified(): void
    {
        $datetime = new GeneralizedTimeType(
            new DateTime('2018-03-18T10:02:01.0123Z'),
            GeneralizedTimeType::FORMAT_SECONDS,
        );

        self::assertSame(
            hex2bin('180f') . '20180318100201Z',
            $this->subject->encode($datetime),
        );
    }

    public function test_it_should_encode_a_generalized_time_string_to_hours(): void
    {
        self::assertSame(
            hex2bin('180a') . '2018031801',
            $this->subject->encode(new GeneralizedTimeType(
                new DateTime('2018-03-18 01:00', new DateTimeZone(date_default_timezone_get())),
                GeneralizedTimeType::FORMAT_HOURS,
                GeneralizedTimeType::TZ_LOCAL,
            )),
        );
    }

    public function test_it_should_encode_a_generalized_time_string_to_minutes(): void
    {
        self::assertSame(
            hex2bin('180d') . '201803180122Z',
            $this->subject->encode(new GeneralizedTimeType(
                new DateTime('2018-03-18 01:22', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_MINUTES,
                GeneralizedTimeType::TZ_UTC,
            )),
        );
    }

    public function test_it_should_throw_an_exception_if_the_hour_is_equal_to_24_when_decoding(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('180b') . '2018031824Z');
    }

    public function test_it_should_decode_a_generalized_time_string_with_hours_in_utc(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('2018-03-18 01:00', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_HOURS,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('180b') . '2018031801Z'),
        );
    }

    public function test_it_should_decode_a_generalized_time_string_in_local_time_form(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('2018-03-18 01:00', new DateTimeZone(date_default_timezone_get())),
                GeneralizedTimeType::FORMAT_HOURS,
                GeneralizedTimeType::TZ_LOCAL,
            ),
            $this->subject->decode(hex2bin('180a') . '2018031801'),
        );
    }

    public function test_it_should_decode_a_generalized_time_string_with_minutes(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('2018-03-18 01:22', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_MINUTES,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('180d') . '201803180122Z'),
        );
    }

    public function test_it_should_decode_a_generalized_time_string_with_seconds(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('2018-03-18 01:22:41', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('180f') . '20180318012241Z'),
        );
    }

    public function test_it_should_decode_a_generalized_time_string_with_fractions_of_a_second(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('1985-11-06 21:06:27.3', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_FRACTIONS,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('1811') . '19851106210627.3Z'),
        );
    }

    public function test_it_should_decode_a_generalized_time_string_with_a_time_differential(): void
    {
        self::assertEquals(
            new GeneralizedTimeType(
                new DateTime('1985-11-06 21:06:27.3', new DateTimeZone('-0500')),
                GeneralizedTimeType::FORMAT_FRACTIONS,
                GeneralizedTimeType::TZ_DIFF,
            ),
            $this->subject->decode(hex2bin('1815') . '19851106210627.3-0500'),
        );
    }

    public function test_it_should_decode_a_utc_time_with_seconds(): void
    {
        self::assertEquals(
            new UtcTimeType(
                new DateTime('18-03-18 01:22:41', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('170d') . '180318012241Z'),
        );
    }

    public function test_it_should_decode_a_utc_time_without_seconds(): void
    {
        self::assertEquals(
            new UtcTimeType(
                new DateTime('18-03-18 01:22', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_MINUTES,
                GeneralizedTimeType::TZ_UTC,
            ),
            $this->subject->decode(hex2bin('170b') . '1803180122Z'),
        );
    }

    public function test_it_should_decode_a_utc_time_with_a_differential_timezone(): void
    {
        self::assertEquals(
            new UtcTimeType(
                new DateTime('18-11-06 21:06:27', new DateTimeZone('-0500')),
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_DIFF,
            ),
            $this->subject->decode(hex2bin('1711') . '181106210627-0500'),
        );
    }

    public function test_it_should_not_accept_decoding_utc_time_with_no_timezone_modifier(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('170c') . '181106210627');
    }

    public function test_it_should_not_accept_decoding_utc_time_with_24_hour_midnight(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Midnight must only be specified by 00, but got 24.');

        $this->subject->decode(hex2bin('170d') . '181106240627Z');
    }

    public function test_it_should_encode_a_utc_time_with_seconds(): void
    {
        self::assertSame(
            hex2bin('170d') . '180318012241Z',
            $this->subject->encode(new UtcTimeType(
                new DateTime('18-03-18 01:22:41', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_UTC,
            )),
        );
    }

    public function test_it_should_encode_a_utc_time_without_seconds(): void
    {
        self::assertSame(
            hex2bin('170b') . '1803180122Z',
            $this->subject->encode(new UtcTimeType(
                new DateTime('18-03-18 01:22', new DateTimeZone('UTC')),
                GeneralizedTimeType::FORMAT_MINUTES,
                GeneralizedTimeType::TZ_UTC,
            )),
        );
    }

    public function test_it_should_encode_a_utc_time_with_a_differential_timezone(): void
    {
        self::assertSame(
            hex2bin('1711') . '181106210627-0500',
            $this->subject->encode(new UtcTimeType(
                new DateTime('18-11-06 21:06:27', new DateTimeZone('-0500')),
                GeneralizedTimeType::FORMAT_SECONDS,
                GeneralizedTimeType::TZ_DIFF,
            )),
        );
    }

    public function test_it_should_encode_a_bmp_string(): void
    {
        self::assertSame(
            hex2bin('1e03') . 'foo',
            $this->subject->encode(new BmpStringType('foo')),
        );
    }

    public function test_it_should_decode_a_bmp_string(): void
    {
        self::assertEquals(
            new BmpStringType('foo'),
            $this->subject->decode(hex2bin('1e03') . 'foo'),
        );
    }

    public function test_it_should_encode_a_character_string(): void
    {
        self::assertSame(
            hex2bin('1d03') . 'foo',
            $this->subject->encode(new CharacterStringType('foo')),
        );
    }

    public function test_it_should_decode_a_character_string(): void
    {
        self::assertEquals(
            new CharacterStringType('foo'),
            $this->subject->decode(hex2bin('1d03') . 'foo'),
        );
    }

    public function test_it_should_encode_a_general_string(): void
    {
        self::assertEquals(
            hex2bin('1b03') . 'foo',
            $this->subject->encode(new GeneralStringType('foo')),
        );
    }

    public function test_it_should_decode_a_general_string(): void
    {
        self::assertEquals(
            new GeneralStringType('foo'),
            $this->subject->decode(hex2bin('1b03') . 'foo'),
        );
    }

    public function test_it_should_encode_a_graphic_string(): void
    {
        self::assertEquals(
            hex2bin('1903') . 'foo',
            $this->subject->encode(new GraphicStringType('foo')),
        );
    }

    public function test_it_should_decode_a_graphic_string(): void
    {
        self::assertEquals(
            new GraphicStringType('foo'),
            $this->subject->decode(hex2bin('1903') . 'foo'),
        );
    }

    public function test_it_should_encode_an_ia5_string(): void
    {
        self::assertEquals(
            hex2bin('1603') . 'foo',
            $this->subject->encode(new IA5StringType('foo')),
        );
    }

    public function test_it_should_decode_an_ia5_string(): void
    {
        self::assertEquals(
            new IA5StringType('foo'),
            $this->subject->decode(hex2bin('1603') . 'foo'),
        );
    }

    public function test_it_should_encode_a_numeric_string(): void
    {
        self::assertEquals(
            hex2bin('1203') . '123',
            $this->subject->encode(new NumericStringType('123')),
        );
    }

    public function test_it_should_decode_a_numeric_string(): void
    {
        self::assertEquals(
            new NumericStringType('123'),
            $this->subject->decode(hex2bin('1203') . '123'),
        );
    }

    public function test_it_should_encode_a_printable_string(): void
    {
        self::assertEquals(
            hex2bin('1303') . 'foo',
            $this->subject->encode(new PrintableStringType('foo')),
        );
    }

    public function test_it_should_decode_a_printable_string(): void
    {
        self::assertEquals(
            new PrintableStringType('foo'),
            $this->subject->decode(hex2bin('1303') . 'foo'),
        );
    }

    public function test_it_should_encode_a_teletex_string(): void
    {
        self::assertEquals(
            hex2bin('1403') . 'foo',
            $this->subject->encode(new TeletexStringType('foo')),
        );
    }

    public function test_it_should_decode_a_teletex_string(): void
    {
        self::assertEquals(
            new TeletexStringType('foo'),
            $this->subject->decode(hex2bin('1403') . 'foo'),
        );
    }

    public function test_it_should_encode_a_universal_string(): void
    {
        self::assertEquals(
            hex2bin('1c03') . 'foo',
            $this->subject->encode(new UniversalStringType('foo')),
        );
    }

    public function test_it_should_decode_a_universal_string(): void
    {
        self::assertEquals(
            new UniversalStringType('foo'),
            $this->subject->decode(hex2bin('1c03') . 'foo'),
        );
    }

    public function test_it_should_encode_a_utf8_string(): void
    {
        self::assertEquals(
            hex2bin('0c03') . 'foo',
            $this->subject->encode(new Utf8StringType('foo')),
        );
    }

    public function test_it_should_decode_a_utf8_string(): void
    {
        self::assertEquals(
            new Utf8StringType('foo'),
            $this->subject->decode(hex2bin('0c03') . 'foo'),
        );
    }

    public function test_it_should_encode_a_videotex_string(): void
    {
        self::assertEquals(
            hex2bin('1503') . 'foo',
            $this->subject->encode(new VideotexStringType('foo')),
        );
    }

    public function test_it_should_decode_a_videotex_string(): void
    {
        self::assertEquals(
            new VideotexStringType('foo'),
            $this->subject->decode(hex2bin('1503') . 'foo'),
        );
    }

    public function test_it_should_encode_a_visible_string(): void
    {
        self::assertEquals(
            hex2bin('1a03') . 'foo',
            $this->subject->encode(new VisibleStringType('foo')),
        );
    }

    public function test_it_should_decode_a_visible_string(): void
    {
        self::assertEquals(
            new VisibleStringType('foo'),
            $this->subject->decode(hex2bin('1a03') . 'foo'),
        );
    }

    public function test_it_should_throw_an_encoder_exception_on_decoding_an_invalid_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0600'));
    }

    public function test_it_should_throw_an_encoder_exception_on_encoding_an_invalid_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encode(new OidType('1'));
    }

    public function test_it_should_encode_a_relative_oid(): void
    {
        self::assertSame(
            hex2bin('0d080601040182371514'),
            $this->subject->encode(new RelativeOidType('6.1.4.1.311.21.20')),
        );
        self::assertSame(
            hex2bin('0d04ffffff7f'),
            $this->subject->encode(new RelativeOidType('268435455')),
        );
    }

    public function test_it_should_encode_a_relative_oid_with_a_bigint_value(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertSame(
            hex2bin('0d0a81ffffffffffffffff7f'),
            $this->subject->encode(new RelativeOidType('18446744073709551615')),
        );
    }

    public function test_it_should_decode_a_relative_oid(): void
    {
        self::assertEquals(
            new RelativeOidType('6.1.4.1.311.21.20'),
            $this->subject->decode(hex2bin('0d080601040182371514')),
        );
        self::assertEquals(
            new RelativeOidType('268435455'),
            $this->subject->decode(hex2bin('0d04ffffff7f')),
        );
    }

    public function test_it_should_decode_a_relative_oid_with_a_bigint_value(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('The GMP extension must be loaded for bigint tests.');
        }

        self::assertEquals(
            new RelativeOidType('18446744073709551615'),
            $this->subject->decode(hex2bin('0d0a81ffffffffffffffff7f')),
        );
    }

    public function test_it_should_decode_an_unknown_type(): void
    {
        $incompleteType = new IncompleteType(
            hex2bin('01'),
            7,
            AbstractType::TAG_CLASS_PRIVATE,
            false,
        );

        self::assertEquals(
            $incompleteType,
            $this->subject->decode(hex2bin('c70101')),
        );
    }

    public function test_it_should_throw_an_error_when_decoding_incorrect_length(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The expected byte length was 2, but received 1.');

        $this->subject->decode(hex2bin('010201'));
    }

    public function test_it_should_throw_an_error_if_indefinite_length_encoding_is_used(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Indefinite length encoding is not currently supported.');

        $this->subject->decode(hex2bin('0180010000'));
    }

    public function test_it_should_return_null_for_the_last_ending_position_if_there_is_none_yet(): void
    {
        self::assertNull($this->subject->getLastPosition());
    }

    public function test_it_should_not_change_the_last_position_when_completing_a_type(): void
    {
        $this->subject->decode(hex2bin('0101FF00'));
        $this->subject->complete(
            (new IncompleteType(hex2bin('FF')))->setTagNumber(5),
            AbstractType::TAG_TYPE_BOOLEAN,
        );

        self::assertSame(
            3,
            $this->subject->getLastPosition(),
        );
    }

    public function test_it_should_get_the_last_ending_position(): void
    {
        self::assertEquals(
            new BooleanType(true),
            $this->subject->decode(hex2bin('0101FF00')),
        );
        self::assertSame(
            3,
            $this->subject->getLastPosition(),
        );
    }

    public function test_it_should_throw_a_partial_pdu_exception_with_only_a_byte_of_data(): void
    {
        $this->expectException(PartialPduException::class);

        $this->subject->decode(hex2bin('30'));
    }

    public function test_it_should_throw_a_partial_pdu_exception_without_enough_data_to_decode_length(): void
    {
        $this->expectException(PartialPduException::class);
        $this->expectExceptionMessage('Not enough data to decode the length.');

        $this->subject->decode(hex2bin('048301ff'));
    }

    public function test_it_should_not_throw_a_partial_pdu_exception_when_data_is_complete(): void
    {
        $result = $this->subject->decode(
            hex2bin('30840000003702010264840000002e0426434e3d436861642c434e3d55736572732c44433d6c646170746f6f6c732c44433d6c6f63616c308400000000'),
        );

        self::assertInstanceOf(SequenceType::class, $result);
    }

    public function test_it_should_detect_a_context_specific_tag_type_correctly(): void
    {
        self::assertSame(
            AbstractType::TAG_CLASS_CONTEXT_SPECIFIC,
            $this->subject->decode(hex2bin('800001'))->getTagClass(),
        );
    }

    public function test_it_should_detect_an_application_tag_correctly(): void
    {
        self::assertSame(
            AbstractType::TAG_CLASS_APPLICATION,
            $this->subject->decode(hex2bin('6000'))->getTagClass(),
        );
    }

    public function test_it_should_detect_a_private_tag_correctly(): void
    {
        self::assertSame(
            AbstractType::TAG_CLASS_PRIVATE,
            $this->subject->decode(hex2bin('c00001'))->getTagClass(),
        );
    }

    public function test_it_should_detect_a_universal_tag_correctly(): void
    {
        self::assertSame(
            AbstractType::TAG_CLASS_UNIVERSAL,
            $this->subject->decode(hex2bin('010101'))->getTagClass(),
        );
    }

    public function test_it_should_complete_an_incomplete_type(): void
    {
        self::assertEquals(
            (new BooleanType(true))->setTagNumber(5),
            $this->subject->complete(
                (new IncompleteType(hex2bin('FF')))->setTagNumber(5),
                AbstractType::TAG_TYPE_BOOLEAN,
            ),
        );
    }

    public function test_it_should_decode_a_high_tag_number_properly(): void
    {
        self::assertEquals(
            new IncompleteType(hex2bin('01'), 31, AbstractType::TAG_CLASS_APPLICATION, false),
            $this->subject->decode(hex2bin('5f1f0101')),
        );
        self::assertEquals(
            new IncompleteType(hex2bin('01'), 128, AbstractType::TAG_CLASS_APPLICATION, false),
            $this->subject->decode(hex2bin('5f81000101')),
        );
    }

    public function test_it_should_handle_decoding_a_high_big_int_tag_number(): void
    {
        self::assertEquals(
            new IncompleteType(
                hex2bin('01'),
                '18446744073709551615',
                AbstractType::TAG_CLASS_APPLICATION,
                false,
            ),
            $this->subject->decode(hex2bin('5f81ffffffffffffffff7f0101')),
        );
    }

    public function test_it_should_handle_encoding_a_high_big_int_tag_number(): void
    {
        self::assertSame(
            hex2bin('1f81ffffffffffffffff7f0101'),
            $this->subject->encode((new OctetStringType("\x01"))->setTagNumber('18446744073709551615')),
        );
    }

    public function test_it_should_throw_a_partial_pdu_exception_on_a_root_type_with_no_high_tag_ending(): void
    {
        $this->expectException(PartialPduException::class);

        $this->subject->decode(hex2bin('5f8080'));
    }

    public function test_it_should_throw_an_encoder_exception_on_a_non_root_type_with_no_high_tag_ending(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('30035f8080'));
    }

    public function test_it_should_encode_a_high_tag_number_properly(): void
    {
        self::assertSame(
            hex2bin('1f810001ff'),
            $this->subject->encode((new BooleanType(true))->setTagNumber(128)),
        );
    }

    public function test_it_should_throw_an_exception_on_zero_length_boolean(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0100'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_integer(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0200'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_enumerated(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0a00'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0600'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_relative_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0d00'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_generalized_time(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('1800'));
    }

    public function test_it_should_throw_an_exception_on_zero_length_utc_time(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('1700'));
    }

    public function test_it_should_throw_an_exception_if_a_bool_with_more_than_one_byte_of_length_is_encountered(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0102ffff'));
    }

    public function test_it_should_throw_an_exception_if_a_null_with_one_or_more_bytes_of_length_is_encountered(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('050101'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_boolean(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('210101'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_integer(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('220101'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_enumerated(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('2a0101'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('38022a7f'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_relative_oid(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('2d022a7f'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_real(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('290101'));
    }

    public function test_it_should_throw_an_exception_on_a_constructed_null(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('2500'));
    }

    public function test_it_should_throw_an_exception_on_a_primitive_sequence(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('10030101ff'));
    }

    public function test_it_should_throw_an_exception_on_a_primitive_set(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('11030101ff'));
    }

    public function test_it_should_throw_an_exception_if_the_integer_to_encode_is_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->encode(new IntegerType('18446744073709551615'));
    }

    public function test_it_should_throw_an_exception_if_the_integer_to_decode_is_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('020900ffffffffffffffff'));
    }

    public function test_it_should_throw_an_exception_if_the_relative_oid_to_encode_has_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->encode(new RelativeOidType('18446744073709551615'));
    }

    public function test_it_should_throw_an_exception_if_the_relative_oid_to_decode_has_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0d0a81ffffffffffffffff7f'));
    }

    public function test_it_should_throw_an_exception_if_the_oid_to_encode_has_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->encode(new OidType('1.2.840.18446744073709551615.1'));
    }

    public function test_it_should_throw_an_exception_if_the_oid_to_decode_has_a_big_int_and_gmp_is_not_available(): void
    {
        if (extension_loaded('gmp')) {
            self::markTestSkipped('Only valid when GMP is not loaded.');
        }

        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('060e2a864881ffffffffffffffff7f01'));
    }

    public function test_it_should_throw_an_error_when_encoding_the_tag_number_if_it_is_not_numeric(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encode((new IntegerType(1))->setTagNumber('foo'));
    }

    public function test_it_should_throw_an_error_when_encoding_an_integer_tag_if_it_is_not_numeric(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encode((new IntegerType(1))->setTagNumber('foo'));
    }

    public function test_it_should_throw_an_error_when_encoding_an_enumerated_type_if_it_is_not_numeric(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->encode((new IntegerType(1))->setTagNumber('foo'));
    }
}
