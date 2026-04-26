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
use FreeDSx\Asn1\Type\BooleanType;
use PHPUnit\Framework\TestCase;

final class BooleanTypeTest extends TestCase
{
    private BooleanType $subject;

    protected function setUp(): void
    {
        $this->subject = new BooleanType(true);
    }

    public function test_it_should_set_the_value(): void
    {
        self::assertTrue($this->subject->getValue());
        self::assertFalse($this->subject->setValue(false)->getValue());
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_BOOLEAN,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new BooleanType(true))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setValue(true),
            BooleanType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, true),
        );
    }
}
