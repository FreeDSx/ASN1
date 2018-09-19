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

use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\IntegerType;
use PhpSpec\ObjectBehavior;

class IntegerTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(3);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(IntegerType::class);
    }

    function it_should_get_the_value()
    {
        $this->getValue()->shouldBeEqualTo(3);

        $this->setValue(2)->getValue()->shouldBeEqualTo(2);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_INTEGER);
    }

    function it_should_be_constructed_with_a_string_big_int()
    {
        $this->beConstructedWith("99999999999999999999999999999999999999");
        $this->getValue()->shouldBeEqualTo("99999999999999999999999999999999999999");
    }

    function it_should_check_whether_the_value_is_a_bigint_or_not()
    {
        $this->isBigInt()->shouldBeEqualTo(false);
        $this->setValue("99999999999999999999999999999999999999");
        $this->isBigInt()->shouldBeEqualTo(true);
    }

    function it_should_throw_an_error_on_construction_if_the_value_is_not_numeric()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('__construct', ['foo']);
        $this->shouldThrow(InvalidArgumentException::class)->during('__construct', ['1.5']);
    }

    function it_should_throw_an_error_on_set_if_the_value_is_not_numeric()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setValue', ['foo']);
        $this->shouldThrow(InvalidArgumentException::class)->during('setValue', ['1.5']);
    }

    function it_should_throw_an_error_when_setting_the_tag_number_if_it_is_not_numeric()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setTagNumber', ['foo']);
        $this->shouldThrow(InvalidArgumentException::class)->during('setTagNumber', ['1.5']);
        $this->shouldNotThrow(InvalidArgumentException::class)->during('setTagNumber', [null]);
    }

    function it_should_allow_a_numeric_string_as_a_tag_number()
    {
        $this->setTagNumber('99999999999999999');
        $this->getTagNumber()->shouldBeEqualTo('99999999999999999');
    }
}
