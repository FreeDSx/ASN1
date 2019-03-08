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
use FreeDSx\Asn1\Type\EnumeratedType;
use PhpSpec\ObjectBehavior;

class EnumeratedTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(1);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(EnumeratedType::class);
    }

    function it_should_get_the_value()
    {
        $this->getValue()->shouldBeEqualTo(1);

        $this->setValue(2)->getValue()->shouldBeEqualTo(2);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_ENUMERATED);
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
            (new EnumeratedType(1))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }
}
