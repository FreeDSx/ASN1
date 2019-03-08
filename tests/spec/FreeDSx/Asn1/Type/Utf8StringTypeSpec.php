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
use FreeDSx\Asn1\Type\Utf8StringType;
use PhpSpec\ObjectBehavior;

class Utf8StringTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('foo');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Utf8StringType::class);
    }

    function it_should_set_the_value()
    {
        $this->getValue()->shouldBeEqualTo('foo');
        $this->setValue('bar')->getValue()->shouldBeEqualTo('bar');
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_UTF8_STRING);
    }

    function it_should_be_character_restricted()
    {
        $this->isCharacterRestricted()->shouldBeEqualTo(true);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, 'foo')->shouldBeLike(
            (new Utf8StringType('foo'))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }
}
