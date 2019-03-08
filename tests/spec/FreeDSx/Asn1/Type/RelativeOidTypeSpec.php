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
use FreeDSx\Asn1\Type\RelativeOidType;
use PhpSpec\ObjectBehavior;

class RelativeOidTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('0.1');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(RelativeOidType::class);
    }

    function it_should_set_the_value()
    {
        $this->getValue()->shouldBeEqualTo('0.1');
        $this->setValue('2.4')->getValue()->shouldBeEqualTo('2.4');
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_RELATIVE_OID);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, '1.2.3.4')->shouldBeLike(
            (new RelativeOidType('1.2.3.4'))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }
}
