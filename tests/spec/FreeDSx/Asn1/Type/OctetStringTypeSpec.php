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
use FreeDSx\Asn1\Type\OctetStringType;
use PhpSpec\ObjectBehavior;

class OctetStringTypeSpec extends ObjectBehavior
{
    public function let()
    {
        $this->beConstructedWith('foo');
    }

    public function it_is_initializable()
    {
        $this->shouldHaveType(OctetStringType::class);
    }

    public function it_should_get_the_value()
    {
        $this->getValue()->shouldBeEqualTo('foo');

        $this->setValue('bar')->getValue()->shouldBeEqualTo('bar');
    }

    public function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_OCTET_STRING);
    }

    public function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, 'foo')->shouldBeLike(
            (new OctetStringType('foo'))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)->setValue('foo')
        );
    }
}
