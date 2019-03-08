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
use FreeDSx\Asn1\Type\OidType;
use PhpSpec\ObjectBehavior;

class OidTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('1.2.3');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(OidType::class);
    }

    function it_should_get_the_value()
    {
        $this->getValue()->shouldBeEqualTo('1.2.3');

        $this->setValue('1.2.3.4')->getValue()->shouldBeEqualTo('1.2.3.4');
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_OID);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, '1.2.3.4')->shouldBeLike(
            (new OidType('1.2.3.4'))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setValue('1.2.3.4')
        );
    }
}
