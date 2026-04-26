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
use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Encoder\DerEncoder;
use FreeDSx\Asn1\Encoders;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BmpStringType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use FreeDSx\Asn1\Type\GeneralStringType;
use FreeDSx\Asn1\Type\GraphicStringType;
use FreeDSx\Asn1\Type\IA5StringType;
use FreeDSx\Asn1\Type\NumericStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\PrintableStringType;
use FreeDSx\Asn1\Type\TeletexStringType;
use FreeDSx\Asn1\Type\UniversalStringType;
use FreeDSx\Asn1\Type\Utf8StringType;
use FreeDSx\Asn1\Type\VideotexStringType;
use FreeDSx\Asn1\Type\VisibleStringType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DerEncoderTest extends TestCase
{
    private DerEncoder $subject;

    protected function setUp(): void
    {
        $this->subject = new DerEncoder();
    }

    public function test_it_should_encode_a_bit_string(): void
    {
        self::assertSame(
            hex2bin('0304066e5dc0'),
            $this->subject->encode(new BitStringType('011011100101110111')),
        );
    }

    public function test_it_should_decode_a_bit_string(): void
    {
        self::assertEquals(
            new BitStringType('011011100101110111'),
            $this->subject->decode(hex2bin('0304066e5dc0')),
        );
    }

    public function test_it_should_only_allow_0_or_255_for_a_bool_when_decoding(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('010101'));
    }

    public function test_it_should_only_allow_0_or_255_for_a_bool_when_decoding_high_bits(): void
    {
        $this->expectException(EncoderException::class);

        $this->subject->decode(hex2bin('0101a0'));
    }

    public function test_it_should_encode_a_true_bool_to_255(): void
    {
        self::assertSame(
            hex2bin('0101ff'),
            $this->subject->encode(Asn1::boolean(true)),
        );
    }

    public function test_it_should_encode_a_false_bool_to_0(): void
    {
        self::assertSame(
            hex2bin('010100'),
            $this->subject->encode(Asn1::boolean(false)),
        );
    }

    public function test_it_should_encode_a_set_of_in_order(): void
    {
        self::assertSame(
            hex2bin('310d04017a04036261720403666f6f'),
            $this->subject->encode(Asn1::setOf(
                Asn1::octetString('foo'),
                Asn1::octetString('bar'),
                Asn1::octetString('z'),
            )),
        );

        self::assertSame(
            hex2bin('311502010502010502010502010a02010f0201150201fe'),
            $this->subject->encode(Asn1::setOf(
                Asn1::integer(21),
                Asn1::integer(15),
                Asn1::integer(5),
                Asn1::integer(-2),
                Asn1::integer(5),
                Asn1::integer(10),
                Asn1::integer(5),
            )),
        );
    }

    public function test_it_should_encode_a_set_in_canonical_order(): void
    {
        $set = Asn1::set(
            Asn1::private(2, Asn1::utf8String('foo')),
            Asn1::private(1, Asn1::utf8String('bar')),
            Asn1::utf8String('foo'),
            Asn1::octetString('bar'),
            Asn1::context(1, Asn1::utf8String('foo')),
            Asn1::context(3, Asn1::utf8String('foo')),
            Asn1::application(20, Asn1::null()),
            Asn1::application(18, Asn1::null()),
        );

        self::assertSame(
            Encoders::der()->encode(Asn1::set(
                Asn1::octetString('bar'),
                Asn1::utf8String('foo'),
                Asn1::application(18, Asn1::null()),
                Asn1::application(20, Asn1::null()),
                Asn1::context(1, Asn1::utf8String('foo')),
                Asn1::context(3, Asn1::utf8String('foo')),
                Asn1::private(1, Asn1::utf8String('bar')),
                Asn1::private(2, Asn1::utf8String('foo')),
            )),
            $this->subject->encode($set),
        );
    }

    public function test_it_should_encode_using_the_shortest_possible_definite_length_form(): void
    {
        self::assertSame(
            hex2bin('047f') . str_pad('', 127, '0'),
            $this->subject->encode(Asn1::octetString(str_pad('', 127, '0'))),
        );
        self::assertSame(
            hex2bin('048180') . str_pad('', 128, '0'),
            $this->subject->encode(Asn1::octetString(str_pad('', 128, '0'))),
        );
    }

    public function test_it_should_throw_an_exception_if_the_length_was_encoded_long_definite_but_could_have_been_encoded_short_definite(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('DER must be encoded using the shortest possible length form, but it is not.');

        $this->subject->decode(hex2bin('01810100'));
    }

    public function test_it_should_not_allow_indefinite_length(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Indefinite length encoding is not currently supported.');

        $this->subject->decode(hex2bin('0180010000'));
    }

    public function test_it_should_only_allow_primitive_encoding_for_bitstrings(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The bit string must be primitive. It cannot be constructed.');

        $this->subject->encode((new BitStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_decoding_for_bitstrings(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The bit string must be primitive. It cannot be constructed.');

        $this->subject->decode(hex2bin('2304030200ff'));
    }

    public function test_it_should_only_allow_primitive_encoding_for_octetstrings(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The octet string must be primitive. It cannot be constructed.');

        $this->subject->encode((new OctetStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_decoding_for_octetstrings(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The octet string must be primitive. It cannot be constructed.');

        $this->subject->decode(hex2bin('2403040101'));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_numeric(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new NumericStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_printable(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new PrintableStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_teletex(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new TeletexStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_videotex(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new VideotexStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_ia5(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new IA5StringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_graphic(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new GraphicStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_visible(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new VisibleStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_general(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new GeneralStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_bmp(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new BmpStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_universal(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new UniversalStringType(''))->setIsConstructed(true));
    }

    public function test_it_should_only_allow_primitive_encoding_for_restricted_character_strings_utf8(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->encode((new Utf8StringType(''))->setIsConstructed(true));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function constructedRestrictedCharacterStringDecodeProvider(): array
    {
        return [
            'numeric' => ['3203120101'],
            'printable' => ['3303130101'],
            'teletex' => ['3403140101'],
            'videotex' => ['3503150101'],
            'ia5' => ['3603160101'],
            'graphic' => ['3903190101'],
            'visible' => ['3a031a0101'],
            'general' => ['3b031b0101'],
            'bmp' => ['3e031e0101'],
            'universal' => ['3c031c0101'],
            'utf8' => ['2c030c0101'],
        ];
    }

    #[DataProvider('constructedRestrictedCharacterStringDecodeProvider')]
    public function test_it_should_only_allow_primitive_decoding_for_restricted_character_strings(string $hex): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Character restricted string types must be primitive.');

        $this->subject->decode(hex2bin($hex));
    }

    public function test_it_should_require_that_generalized_time_has_seconds_when_encoding_minutes(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must be specified to the seconds, but it is specified to "minutes".');

        $this->subject->encode(new GeneralizedTimeType(new DateTime(), GeneralizedTimeType::FORMAT_MINUTES));
    }

    public function test_it_should_require_that_generalized_time_has_seconds_when_encoding_hours(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must be specified to the seconds, but it is specified to "hours".');

        $this->subject->encode(new GeneralizedTimeType(new DateTime(), GeneralizedTimeType::FORMAT_HOURS));
    }

    public function test_it_should_require_that_generalized_time_has_seconds_when_decoding_hours(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must be specified to the seconds, but it is specified to "hours".');

        $this->subject->decode(hex2bin('180b') . '2018031801Z');
    }

    public function test_it_should_require_that_generalized_time_has_seconds_when_decoding_minutes(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must be specified to the seconds, but it is specified to "minutes".');

        $this->subject->decode(hex2bin('180d') . '201803180101Z');
    }

    public function test_it_should_enforce_generalized_time_ending_with_a_z_when_decoding_local(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must end in a Z, but it does not. It is set to "local".');

        $this->subject->decode(hex2bin('180e') . '20180318010101');
    }

    public function test_it_should_enforce_generalized_time_ending_with_a_z_when_decoding_diff(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must end in a Z, but it does not. It is set to "diff".');

        $this->subject->decode(hex2bin('1813') . '20180318010101+0500');
    }

    public function test_it_should_enforce_generalized_time_ending_with_a_z_when_encoding_local(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must end in a Z, but it does not. It is set to "local".');

        $this->subject->encode(new GeneralizedTimeType(
            new DateTime(),
            GeneralizedTimeType::FORMAT_FRACTIONS,
            GeneralizedTimeType::TZ_LOCAL,
        ));
    }

    public function test_it_should_enforce_generalized_time_ending_with_a_z_when_encoding_diff(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Time must end in a Z, but it does not. It is set to "diff".');

        $this->subject->encode(new GeneralizedTimeType(
            new DateTime(),
            GeneralizedTimeType::FORMAT_FRACTIONS,
            GeneralizedTimeType::TZ_DIFF,
        ));
    }

    public function test_it_should_omit_trailing_zeros_in_fractional_seconds_when_encoding(): void
    {
        $threeHundred = DateTime::createFromFormat('YmdHis.uT', '19851106210627.300Z');
        $zero = DateTime::createFromFormat('YmdHis.uT', '19851106210627.00Z');

        self::assertInstanceOf(DateTime::class, $threeHundred);
        self::assertInstanceOf(DateTime::class, $zero);
        self::assertSame(
            hex2bin('1811') . '19851106210627.3Z',
            $this->subject->encode(new GeneralizedTimeType($threeHundred)),
        );
        self::assertSame(
            hex2bin('180f') . '19851106210627Z',
            $this->subject->encode(new GeneralizedTimeType($zero)),
        );
    }

    public function test_it_should_not_allow_trailing_zeros_in_fractional_seconds_on_decoding(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Trailing zeros must be omitted from Generalized Time types, but it is not.');

        $this->subject->decode(hex2bin('1812') . '19851106210627.30Z');
    }

    public function test_it_should_not_allow_zero_fractional_seconds_on_decoding(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Trailing zeros must be omitted from Generalized Time types, but it is not.');

        $this->subject->decode(hex2bin('1812') . '19851106210627.00Z');
    }

    public function test_it_should_not_allow_24_as_a_representation_of_midnight_for_generalized_time(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('Midnight must only be specified by 00, but got 24.');

        $this->subject->decode(hex2bin('180f') . '20181106240627Z');
    }

    public function test_it_should_enforce_that_unused_bits_in_bit_strings_be_set_to_zero(): void
    {
        $this->expectException(EncoderException::class);
        $this->expectExceptionMessage('The last 2 unused bits of the bit string must be 0, but they are not.');

        $this->subject->decode(hex2bin('03020205'));
    }
}
