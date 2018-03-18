<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Factory;

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Factory\TypeFactory;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\SequenceType;
use PhpSpec\ObjectBehavior;

class TypeFactorySpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(TypeFactory::class);
    }

    function it_should_set_a_tag_type()
    {
        $this->setType(90, BooleanType::class);
        $this->getType(90)->shouldBeEqualTo(BooleanType::class);
    }

    function it_should_get_the_class_for_a_tag_type()
    {
        $this->getType(AbstractType::TAG_TYPE_BOOLEAN)->shouldBeEqualTo(BooleanType::class);
        $this->getType(999)->shouldBeEqualTo(null);
    }

    function it_should_check_if_a_class_is_mapped_to_a_tag_type()
    {
        $this::hasType(AbstractType::TAG_TYPE_BOOLEAN)->shouldBeEqualTo(true);
        $this::hasType(999)->shouldBeEqualTo(false);
    }

    function it_should_create_a_constructed_type_properly()
    {
        $this::create(AbstractType::TAG_TYPE_SEQUENCE, [new BooleanType(true)], true)->shouldBeLike(
            new SequenceType(new BooleanType(true))
        );
    }

    function it_should_create_a_primitive_type_properly()
    {
        $this::create(AbstractType::TAG_TYPE_BOOLEAN, true, false)->shouldBeLike(new BooleanType(true));
    }

    function it_should_throw_an_exception_if_the_class_doesnt_exist_when_setting_the_tag_type()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setType', [AbstractType::TAG_TYPE_BOOLEAN, '\This\Doesnt\Exist']);
    }

    function it_should_throw_an_exception_if_the_class_doesnt_extend_abstract_type_when_setting_the_tag_type()
    {
        $this->shouldThrow(InvalidArgumentException::class)->during('setType', [AbstractType::TAG_TYPE_BOOLEAN, BerEncoder::class]);
    }
}
