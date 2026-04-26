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
use FreeDSx\Asn1\Type\EnumeratedType;
use PHPUnit\Framework\TestCase;

final class EnumeratedTypeTest extends TestCase
{
    private EnumeratedType $subject;

    protected function setUp(): void
    {
        $this->subject = new EnumeratedType(1);
    }

    public function test_it_should_get_the_value(): void
    {
        self::assertSame(
            1,
            $this->subject->getValue(),
        );
        self::assertSame(
            2,
            $this->subject->setValue(2)->getValue(),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_ENUMERATED,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_check_whether_the_value_is_a_bigint_or_not(): void
    {
        self::assertFalse($this->subject->isBigInt());

        $this->subject->setValue('99999999999999999999999999999999999999');

        self::assertTrue($this->subject->isBigInt());
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new EnumeratedType(1))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION),
            EnumeratedType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, 1),
        );
    }
}
