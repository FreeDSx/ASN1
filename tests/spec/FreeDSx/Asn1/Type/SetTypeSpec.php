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

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SetType;
use PhpSpec\ObjectBehavior;

class SetTypeSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new IntegerType(1), new OctetStringType('foo'));
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(SetType::class);
    }


    function it_should_be_constructed()
    {
        $this->getIsConstructed()->shouldBeEqualTo(true);
    }

    function it_should_implement_countable()
    {
        $this->shouldImplement('\Countable');
    }

    function it_should_implement_iterator_aggregate()
    {
        $this->shouldImplement('\IteratorAggregate');
    }

    function it_should_set_children()
    {
        $this->setChildren(new IntegerType(1), new IntegerType(2));

        $this->getChildren()->shouldBeLike([
            new IntegerType(1),
            new IntegerType(2)
        ]);
    }

    function it_should_add_a_child()
    {
        $child = new IntegerType(4);
        $this->addChild($child);

        $this->getChildren()->shouldContain($child);
    }

    function it_should_check_if_a_child_exists()
    {
        $this->hasChild(0)->shouldBeEqualTo(true);
        $this->hasChild(3)->shouldBeEqualTo(false);
    }

    function it_should_get_all_children()
    {
        $this->getChildren()->shouldBeLike([
            new IntegerType(1),
            new OctetStringType('foo')
        ]);
    }

    function it_should_get_a_child_if_it_exists()
    {
        $this->getChild(0)->shouldBeAnInstanceOf(IntegerType::class);
    }

    function it_should_be_null_when_getting_a_child_that_does_not_exist()
    {
        $this->getChild(9)->shouldBeNull();
    }

    function it_should_have_a_default_tag_type()
    {
        $this->getTagNumber()->shouldBeEqualTo(AbstractType::TAG_TYPE_SET);
    }

    function it_should_get_a_count()
    {
        $this->count()->shouldBeEqualTo(2);
    }

    function it_should_be_able_to_determine_if_it_is_in_canonical_order()
    {
        $this->isCanonical()->shouldBeEqualTo(true);

        $this->setChildren(
            Asn1::private(2, Asn1::utf8String('foo')),
            Asn1::private(1, Asn1::utf8String('bar')),
            Asn1::utf8String('foo'),
            Asn1::octetString('bar'),
            Asn1::context(1, Asn1::utf8String('foo')),
            Asn1::context(3, Asn1::utf8String('foo')),
            Asn1::application(20, Asn1::null()),
            Asn1::application(18, Asn1::null())
        );

        $this->isCanonical()->shouldBeEqualTo(false);

        $this->setChildren(
            Asn1::octetString('bar'),
            Asn1::utf8String('foo'),
            Asn1::application(18, Asn1::null()),
            Asn1::application(20, Asn1::null()),
            Asn1::context(1, Asn1::utf8String('foo')),
            Asn1::context(3, Asn1::utf8String('foo')),
            Asn1::private(1, Asn1::utf8String('bar')),
            Asn1::private(2, Asn1::utf8String('foo'))
        );

        $this->isCanonical()->shouldBeEqualTo(true);
    }

    function it_should_be_constructed_with_tag_information()
    {
        $this::withTag(1, AbstractType::TAG_CLASS_APPLICATION, [new IntegerType(1)])->shouldBeLike(
            (new SetType(new IntegerType(1)))->setTagNumber(1)->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }
}
