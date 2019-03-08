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
use FreeDSx\Asn1\Type\BooleanType;
use PhpSpec\ObjectBehavior;

class BooleanTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(true);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(BooleanType::class);
    }

    function it_should_set_the_value()
    {
        $this->getValue()->shouldBeEqualTo(true);
        $this->setValue(false)->getValue()->shouldBeEqualTo(false);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_BOOLEAN);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, true)->shouldBeLike(
            (new BooleanType(true))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setValue(true)
        );
    }
}
