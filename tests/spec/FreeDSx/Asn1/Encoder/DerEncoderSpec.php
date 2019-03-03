<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Encoder;

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
use PhpSpec\ObjectBehavior;

class DerEncoderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(DerEncoder::class);
    }

    function it_should_encode_a_bit_string()
    {
        $this->encode(new BitStringType('011011100101110111'))->shouldBeEqualTo(hex2bin('0304066e5dc0'));
    }

    function it_should_decode_a_bit_string()
    {
        $this->decode(hex2bin('0304066e5dc0'))->shouldBeLike(new BitStringType('011011100101110111'));
    }

    function it_should_only_allow_0_or_255_for_a_bool_when_decoding()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('010101')]);
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0101a0')]);
    }

    function it_should_encode_a_true_bool_to_255()
    {
        $this->encode(Asn1::boolean(true))->shouldBeEqualTo(hex2bin('0101ff'));
    }

    function it_should_encode_a_false_bool_to_0()
    {
        $this->encode(Asn1::boolean(false))->shouldBeEqualTo(hex2bin('010100'));
    }

    function it_should_encode_a_set_of_in_order()
    {
        $this->encode(
            Asn1::setOf(
                Asn1::octetString('foo'),
                Asn1::octetString('bar'),
                Asn1::octetString('z')
            )
        )->shouldBeEqualTo(hex2bin('310d04017a04036261720403666f6f'));

        $this->encode(
            Asn1::setOf(
                Asn1::integer(21),
                Asn1::integer(15),
                Asn1::integer(5),
                Asn1::integer(-2),
                Asn1::integer(5),
                Asn1::integer(10),
                Asn1::integer(5)
            )
        )->shouldBeEqualTo(hex2bin('311502010502010502010502010a02010f0201150201fe'));
    }

    function it_should_encode_a_set_in_canonical_order()
    {
        $set = Asn1::set(
            Asn1::private(2, Asn1::utf8String('foo')),
            Asn1::private(1, Asn1::utf8String('bar')),
            Asn1::utf8String('foo'),
            Asn1::octetString('bar'),
            Asn1::context(1, Asn1::utf8String('foo')),
            Asn1::context(3, Asn1::utf8String('foo')),
            Asn1::application(20, Asn1::null()),
            Asn1::application(18, Asn1::null())
        );

        $this->encode($set)->shouldBeEqualTo(Encoders::der()->encode(Asn1::set(
            Asn1::octetString('bar'),
            Asn1::utf8String('foo'),
            Asn1::application(18, Asn1::null()),
            Asn1::application(20, Asn1::null()),
            Asn1::context(1, Asn1::utf8String('foo')),
            Asn1::context(3, Asn1::utf8String('foo')),
            Asn1::private(1, Asn1::utf8String('bar')),
            Asn1::private(2, Asn1::utf8String('foo'))
        )));
    }

    function it_should_encode_using_the_shortest_possible_definite_length_form()
    {
        $this->encode(Asn1::octetString(str_pad('', 127, '0')))->shouldBeEqualTo(hex2bin('047f').str_pad('', 127, '0'));
        $this->encode(Asn1::octetString(str_pad('', 128, '0')))->shouldBeEqualTo(hex2bin('048180').str_pad('', 128, '0'));
    }

    function it_should_throw_an_exception_if_the_length_was_encoded_long_definite_but_could_have_been_encoded_short_definite()
    {
        $this->shouldThrow(new EncoderException('DER must be encoded using the shortest possible length form, but it is not.'))->during('decode', [hex2bin('01810100')]);
    }

    function it_should_not_allow_indefinite_length()
    {
        $this->shouldThrow(new EncoderException('Indefinite length encoding is not currently supported.'))->during('decode', [hex2bin('0180010000')]);
    }

    function it_should_only_allow_primitive_encoding_for_bitstrings()
    {
        $this->shouldThrow(new EncoderException('The bit string must be primitive. It cannot be constructed.'))->during('encode', [(new BitStringType(''))->setIsConstructed(true)]);
    }

    function it_should_only_allow_primitive_decoding_for_bitstrings()
    {
        $this->shouldThrow(new EncoderException('The bit string must be primitive. It cannot be constructed.'))->during('decode', [hex2bin('2304030200ff')]);
    }

    function it_should_only_allow_primitive_encoding_for_octetstrings()
    {
        $this->shouldThrow(new EncoderException('The octet string must be primitive. It cannot be constructed.'))->during('encode', [(new OctetStringType(''))->setIsConstructed(true)]);
    }

    function it_should_only_allow_primitive_decoding_for_octetstrings()
    {
        $this->shouldThrow(new EncoderException('The octet string must be primitive. It cannot be constructed.'))->during('decode', [hex2bin('2403040101')]);
    }

    function it_should_only_allow_primitive_encoding_for_restricted_character_strings()
    {
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new NumericStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new PrintableStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new TeletexStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new VideotexStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new IA5StringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new GraphicStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new VisibleStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new GeneralStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new BmpStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new UniversalStringType(''))->setIsConstructed(true)]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('encode', [(new Utf8StringType(''))->setIsConstructed(true)]);
    }

    function it_should_only_allow_primitive_decoding_for_restricted_character_strings()
    {
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3203120101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3303130101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3403140101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3503150101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3603160101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3903190101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3a031a0101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3b031b0101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3e031e0101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('3c031c0101')]);
        $this->shouldThrow(new EncoderException('Character restricted string types must be primitive.'))->during('decode', [hex2bin('2c030c0101')]);
    }

    function it_should_require_that_generalized_time_has_seconds_or_fractions_when_encoding()
    {
        $this->shouldThrow(new EncoderException('Time must be specified to the seconds, but it is specified to "minutes".'))->during('encode', [new GeneralizedTimeType(new \DateTime(), GeneralizedTimeType::FORMAT_MINUTES)]);
        $this->shouldThrow(new EncoderException('Time must be specified to the seconds, but it is specified to "hours".'))->during('encode', [new GeneralizedTimeType(new \DateTime(), GeneralizedTimeType::FORMAT_HOURS)]);
    }

    function it_should_require_that_generalized_time_has_seconds_or_fractions_when_decoding()
    {
        $this->shouldThrow(new EncoderException('Time must be specified to the seconds, but it is specified to "hours".'))->during('decode', [hex2bin('180b').'2018031801Z']);
        $this->shouldThrow(new EncoderException('Time must be specified to the seconds, but it is specified to "minutes".'))->during('decode', [hex2bin('180d').'201803180101Z']);
    }

    function it_should_enforce_generalized_time_ending_with_a_Z_when_decoding()
    {
        $this->shouldThrow(new EncoderException('Time must end in a Z, but it does not. It is set to "local".'))->during('decode', [hex2bin('180e').'20180318010101']);
        $this->shouldThrow(new EncoderException('Time must end in a Z, but it does not. It is set to "diff".'))->during('decode', [hex2bin('1813').'20180318010101+0500']);
    }

    function it_should_enforce_generalized_time_ending_with_a_Z_when_encoding()
    {
        $this->shouldThrow(new EncoderException('Time must end in a Z, but it does not. It is set to "local".'))->during('encode', [new GeneralizedTimeType(new \DateTime(), GeneralizedTimeType::FORMAT_FRACTIONS, GeneralizedTimeType::TZ_LOCAL)]);
        $this->shouldThrow(new EncoderException('Time must end in a Z, but it does not. It is set to "diff".'))->during('encode', [new GeneralizedTimeType(new \DateTime(), GeneralizedTimeType::FORMAT_FRACTIONS, GeneralizedTimeType::TZ_DIFF)]);
    }

    function it_should_omit_trailing_zeros_in_fractional_seconds_when_encoding()
    {
        $this->encode(new GeneralizedTimeType(\DateTime::createFromFormat('YmdHis.uT', '19851106210627.300Z')))->shouldBeEqualTo(hex2bin('1811').'19851106210627.3Z');
        $this->encode(new GeneralizedTimeType(\DateTime::createFromFormat('YmdHis.uT', '19851106210627.00Z')))->shouldBeEqualTo(hex2bin('180f').'19851106210627Z');
    }

    function it_should_not_allow_trailing_zeros_or_fractional_seconds_that_equate_to_zero_on_decoding()
    {
        $this->shouldThrow(new EncoderException('Trailing zeros must be omitted from Generalized Time types, but it is not.'))->during('decode', [hex2bin('1812').'19851106210627.30Z']);
        $this->shouldThrow(new EncoderException('Trailing zeros must be omitted from Generalized Time types, but it is not.'))->during('decode', [hex2bin('1812').'19851106210627.00Z']);
    }

    function it_should_not_allow_24_as_a_representation_of_midnight_for_generalized_time()
    {
        $this->shouldThrow(new EncoderException('Midnight must only be specified by 00, but got 24.'))->during('decode', [(hex2bin('180f').'20181106240627Z')]);
    }

    function it_should_enforce_that_unused_bits_in_bit_strings_be_set_to_zero()
    {
        $this->shouldThrow(new EncoderException('The last 2 unused bits of the bit string must be 0, but they are not.'))->during('decode',[hex2bin('03020205')]);
    }
}
