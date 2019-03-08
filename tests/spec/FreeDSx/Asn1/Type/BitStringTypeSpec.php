<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Type;

use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use PhpSpec\ObjectBehavior;

class BitStringTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('011011100101110111000000');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(BitStringType::class);
    }

    function it_should_get_the_integer_value()
    {
        $this->beConstructedWith('11000000');

        $this->toInteger()->shouldBeEqualTo(192);
    }

    function it_should_get_the_integer_value_of_a_bit_string_with_trailing_zeroes()
    {
        $this->beConstructedWith('11000000000000000000000000000000');

        $this->toInteger()->shouldBeEqualTo(192);
    }

    function it_should_get_the_packed_binary_representation()
    {
        $this->toBinary()->shouldBeEqualTo(hex2bin('6e5dc0'));
    }

    function it_should_get_the_bit_string_from_binary()
    {
        $this->beConstructedThrough('fromBinary',[hex2bin('6e5dc0')]);

        $this->getValue()->shouldBeLike('011011100101110111000000');
    }

    function it_should_get_the_bit_string_from_an_integer()
    {
        $this::fromInteger(64)->shouldBeLike(new BitStringType('01000000'));
        $this::fromInteger(64212)->shouldBeLike(new BitStringType('1111101011010100'));
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_BIT_STRING);
    }

    function it_should_adhere_to_a_min_length_on_integer_and_binary_if_specified()
    {
        $this::fromInteger(1, 32)->shouldBeLike(new BitStringType('00000001000000000000000000000000'));
        $this::fromBinary("\x02", 16)->shouldBeLike(new BitStringType('0000001000000000'));
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, '1.2.3.4')->shouldBeLike(
            (new BitStringType('1.2.3.4'))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setValue('1.2.3.4')
        );
    }
}
