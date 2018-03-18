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
use FreeDSx\Asn1\Type\UtcTimeType;
use PhpSpec\ObjectBehavior;

class UtcTimeTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new \DateTime());
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(UtcTimeType::class);
    }

    function it_should_set_the_value()
    {
        $date = new \DateTime();
        $this->getValue()->shouldBeAnInstanceOf(\DateTime::class);
        $this->setValue($date)->getValue()->shouldBeEqualTo($date);
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_UTC_TIME);
    }
}
