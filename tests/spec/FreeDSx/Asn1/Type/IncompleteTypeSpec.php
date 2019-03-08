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
use FreeDSx\Asn1\Type\IncompleteType;
use PhpSpec\ObjectBehavior;

class IncompleteTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('foo');
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(IncompleteType::class);
    }

    function it_should_have_no_tag_number_by_default()
    {
        $this->getTagNumber()->shouldBeEqualTo(null);
    }

    function it_should_be_constructed_with_a_tag_number_class_and_whether_its_constructed()
    {
        $this->beConstructedWith('foo', 1, AbstractType::TAG_CLASS_APPLICATION, true);
        $this->getValue()->shouldBeEqualTo('foo');
        $this->getTagClass()->shouldBeEqualTo(AbstractType::TAG_CLASS_APPLICATION);
        $this->getTagNumber()->shouldBeEqualTo(1);
        $this->getIsConstructed()->shouldBeEqualTo(true);
    }
}
