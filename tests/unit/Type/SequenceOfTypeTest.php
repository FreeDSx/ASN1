<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\FreeDSx\Asn1\Type;

use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SequenceOfType;
use PHPUnit\Framework\TestCase;

final class SequenceOfTypeTest extends TestCase
{
    private SequenceOfType $subject;

    protected function setUp(): void
    {
        $this->subject = new SequenceOfType(
            new IntegerType(1),
            new OctetStringType('foo'),
        );
    }

    public function test_it_should_be_constructed(): void
    {
        self::assertTrue($this->subject->getIsConstructed());
    }

    public function test_it_should_set_children(): void
    {
        $this->subject->setChildren(
            new IntegerType(1),
            new IntegerType(2),
        );

        self::assertEquals(
            [
                new IntegerType(1),
                new IntegerType(2),
            ],
            $this->subject->getChildren(),
        );
    }

    public function test_it_should_add_a_child(): void
    {
        $child = new IntegerType(4);

        $this->subject->addChild($child);

        self::assertContains($child, $this->subject->getChildren());
    }

    public function test_it_should_check_if_a_child_exists(): void
    {
        self::assertTrue($this->subject->hasChild(0));
        self::assertFalse($this->subject->hasChild(3));
    }

    public function test_it_should_get_all_children(): void
    {
        self::assertEquals(
            [
                new IntegerType(1),
                new OctetStringType('foo'),
            ],
            $this->subject->getChildren(),
        );
    }

    public function test_it_should_get_a_child_if_it_exists(): void
    {
        self::assertInstanceOf(IntegerType::class, $this->subject->getChild(0));
    }

    public function test_it_should_be_null_when_getting_a_child_that_does_not_exist(): void
    {
        self::assertNull($this->subject->getChild(9));
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_SEQUENCE,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_get_a_count(): void
    {
        self::assertCount(2, $this->subject);
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new SequenceOfType(new IntegerType(1)))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION),
            SequenceOfType::withTag(
                1,
                AbstractType::TAG_CLASS_APPLICATION,
                [new IntegerType(1)],
            ),
        );
    }
}
