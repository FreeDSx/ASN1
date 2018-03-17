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
        $this->beConstructedThrough('fromInteger', [64]);

        $this->getValue()->shouldBeEqualTo('01000000');
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_BIT_STRING);
    }
}
