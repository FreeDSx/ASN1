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

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, 1)->shouldBeLike(
            (new IntegerType(1))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }
}
