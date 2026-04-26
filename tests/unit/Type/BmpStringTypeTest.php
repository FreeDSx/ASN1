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
use FreeDSx\Asn1\Type\BmpStringType;
use PHPUnit\Framework\TestCase;

final class BmpStringTypeTest extends TestCase
{
    private BmpStringType $subject;

    protected function setUp(): void
    {
        $this->subject = new BmpStringType('foo');
    }

    public function test_it_should_set_the_value(): void
    {
        self::assertSame(
            'foo',
            $this->subject->getValue(),
        );
        self::assertSame(
            'bar',
            $this->subject->setValue('bar')->getValue(),
        );
    }

    public function test_it_should_have_a_default_tag_type(): void
    {
        self::assertSame(
            AbstractType::TAG_TYPE_BMP_STRING,
            $this->subject->getTagNumber(),
        );
    }

    public function test_it_should_be_character_restricted(): void
    {
        self::assertTrue($this->subject->isCharacterRestricted());
    }

    public function test_it_should_be_constructed_with_tag_information(): void
    {
        self::assertEquals(
            (new BmpStringType('foo'))
                ->setTagNumber(1)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
                ->setValue('foo'),
            BmpStringType::withTag(1, AbstractType::TAG_CLASS_APPLICATION, false, 'foo'),
        );
    }
}
