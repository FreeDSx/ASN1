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
use FreeDSx\Asn1\Type\RealType;
use PhpSpec\ObjectBehavior;

class RealTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(1.21);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(RealType::class);
    }

    function it_should_set_the_value()
    {
        $this->getValue()->shouldBeEqualTo(1.21);
        $this->setValue(0)->getValue()->shouldBeEqualTo((float) 0);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_REAL);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, 1.1)->shouldBeLike(
            (new RealType(1.1))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setValue(1.1)
        );
    }
}
