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
use FreeDSx\Asn1\Type\RealType;
use PHPUnit\Framework\TestCase;

final class RealTypeTest extends TestCase
{
    private RealType $subject;

    protected function setUp(): void
    {
        $this->subject = new RealType(1.21);
    }

    public function test_it_should_set_the_value(): void
    {
        self::assertSame(
            1.21,
            $this->subject->getValue(),
        );
        self::assertSame(
            0.0,
            $this->subject->setValue(0)->getValue(),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_REAL,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new RealType(1.1))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setValue(1.1),
            RealType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, 1.1),
        );
    }
}
