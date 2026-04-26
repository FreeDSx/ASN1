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
use FreeDSx\Asn1\Type\IncompleteType;
use PHPUnit\Framework\TestCase;

final class IncompleteTypeTest extends TestCase
{
    private IncompleteType $subject;

    protected function setUp(): void
    {
        $this->subject = new IncompleteType('foo');
    }

    public function test_it_should_have_no_tag_number_by_default(): void
    {
        self::assertNull($this->subject->getTagNumber());
    }

    public function test_it_should_be_constructed_with_a_tag_number_class_and_whether_its_constructed(): void
    {
        $subject = new IncompleteType(
            'foo',
            1,
            AbstractType::TAG_CLASS_APPLICATION,
            true,
        );

        self::assertSame(
            'foo',
            $subject->getValue(),
        );
        self::assertSame(
            AbstractType::TAG_CLASS_APPLICATION,
            $subject->getTagClass(),
        );
        self::assertSame(
            1,
            $subject->getTagNumber(),
        );
        self::assertTrue($subject->getIsConstructed());
    }
}
